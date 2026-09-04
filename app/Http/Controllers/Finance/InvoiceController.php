<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\GenerateRentInvoicesRequest;
use App\Models\Invoice;
use App\Models\Lease;
use App\Services\Communication\FinancialNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function generate(GenerateRentInvoicesRequest $request, FinancialNotifier $notifier): RedirectResponse
    {
        $periodStart = CarbonImmutable::createFromFormat('Y-m', (string) $request->validated('billing_month'))->startOfMonth();
        $periodEnd = $periodStart->endOfMonth()->startOfDay();

        $leases = Lease::query()
            ->with(['unit.section.estate.settlementAccount', 'rentRevisions'])
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $periodStart);
            })
            ->orderBy('id')
            ->get();

        $unroutedLease = $leases->first(fn (Lease $lease) => $lease->unit->section->estate->settlement_account_id === null);

        if ($unroutedLease !== null) {
            throw ValidationException::withMessages([
                'billing_month' => "{$unroutedLease->unit->section->estate->name} must have a settlement account before invoices are generated.",
            ]);
        }

        $created = 0;
        $skipped = 0;
        $createdInvoiceIds = [];

        DB::transaction(function () use ($leases, $periodStart, $periodEnd, $request, &$created, &$skipped, &$createdInvoiceIds): void {
            $nextId = ((int) Invoice::query()->max('id')) + 1;

            foreach ($leases as $lease) {
                $exists = Invoice::query()
                    ->where('lease_id', $lease->id)
                    ->whereDate('billing_period_start', $periodStart)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $dueDate = $periodStart->day(min($lease->due_day, $periodStart->daysInMonth));
                $estate = $lease->unit->section->estate;
                $rentAmount = $lease->rentForDate($periodStart);

                $invoice = Invoice::query()->create([
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'unit_id' => $lease->unit_id,
                    'settlement_account_id' => $estate->settlement_account_id,
                    'invoice_number' => sprintf('INV-%06d', $nextId++),
                    'billing_period_start' => $periodStart,
                    'billing_period_end' => $periodEnd,
                    'issue_date' => $dueDate->subDays(7),
                    'due_date' => $dueDate,
                    'description' => 'Rent for '.$periodStart->format('F Y'),
                    'total_amount' => $rentAmount,
                    'amount_paid' => 0,
                    'balance_due' => $rentAmount,
                    'currency' => 'UGX',
                    'status' => 'sent',
                    'sent_at' => now(),
                    'created_by' => $request->user()?->id,
                ]);

                $created++;
                $createdInvoiceIds[] = $invoice->id;
            }
        });

        Invoice::query()
            ->whereKey($createdInvoiceIds)
            ->with('tenant')
            ->each(fn (Invoice $invoice) => $notifier->invoiceIssued($invoice));

        $message = $created === 0
            ? "No new {$periodStart->format('F Y')} invoices were needed."
            : "Generated {$created} {$periodStart->format('F Y')} rent ".str('invoice')->plural($created).'.';

        if ($skipped > 0) {
            $message .= " {$skipped} existing ".str('invoice')->plural($skipped).' skipped.';
        }

        return to_route('finance.index')->with('success', $message);
    }
}
