<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()?->tenantProfile;

        if ($tenant === null) {
            return Inertia::render('tenant/Payments', [
                'tenant' => null,
                'summary' => $this->emptySummary(),
                'invoices' => [],
                'payments' => [],
                'gateway' => $this->gatewayData(),
            ]);
        }

        $invoices = Invoice::query()
            ->with(['unit.section.estate', 'settlementAccount'])
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get();

        $payments = Payment::query()
            ->with(['settlementAccount', 'allocations.invoice.unit.section.estate'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'confirmed')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $outstanding = $invoices->filter(fn (Invoice $invoice): bool => $invoice->status !== 'void' && $invoice->balance_due > 0);
        $overdue = $outstanding->filter(fn (Invoice $invoice): bool => $invoice->effectiveStatus() === 'overdue');
        $nextDue = $outstanding->sortBy('due_date')->first();

        return Inertia::render('tenant/Payments', [
            'tenant' => [
                'id' => $tenant->id,
                'full_name' => $tenant->full_name,
            ],
            'summary' => [
                'outstanding' => $outstanding->sum('balance_due'),
                'overdue' => $overdue->sum('balance_due'),
                'paid_total' => $payments->sum('amount'),
                'next_due_date' => $nextDue?->due_date->format('d M Y'),
                'next_due_amount' => $nextDue->balance_due ?? 0,
                'open_invoices' => $outstanding->count(),
            ],
            'invoices' => $invoices->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'description' => $invoice->description,
                'billing_month' => $invoice->billing_period_start->format('F Y'),
                'issue_date' => $invoice->issue_date->format('d M Y'),
                'due_date' => $invoice->due_date->format('d M Y'),
                'status' => $invoice->effectiveStatus(),
                'total_amount' => $invoice->total_amount,
                'amount_paid' => $invoice->amount_paid,
                'balance_due' => $invoice->balance_due,
                'pdf_url' => route('tenant.payments.invoices.pdf', $invoice),
                'unit' => [
                    'code' => $invoice->unit->code,
                    'number' => $invoice->unit->number,
                    'section' => $invoice->unit->section->name,
                    'estate' => $invoice->unit->section->estate->name,
                ],
                'settlement_account' => [
                    'owner_name' => $invoice->settlementAccount->owner_name,
                    'bank_name' => $invoice->settlementAccount->bank_name,
                    'account_last_four' => $invoice->settlementAccount->account_last_four,
                ],
            ])->values(),
            'payments' => $payments->map(function (Payment $payment): array {
                $invoice = $payment->allocations->first()?->invoice;

                return [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'payment_number' => $payment->payment_number,
                    'payment_method' => $payment->payment_method,
                    'payment_method_label' => $this->methodLabel($payment->payment_method, $payment->card_brand),
                    'provider_reference' => $payment->provider_reference,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at->format('d M Y'),
                    'invoice_number' => $invoice?->invoice_number,
                    'estate' => $invoice?->unit->section->estate->name,
                    'unit_code' => $invoice?->unit->code,
                    'settlement_account_last_four' => $payment->settlementAccount->account_last_four,
                    'receipt_url' => route('tenant.payments.receipts.pdf', $payment),
                ];
            })->values(),
            'gateway' => $this->gatewayData(),
        ]);
    }

    /** @return array{outstanding: int, overdue: int, paid_total: int, next_due_date: null, next_due_amount: int, open_invoices: int} */
    private function emptySummary(): array
    {
        return [
            'outstanding' => 0,
            'overdue' => 0,
            'paid_total' => 0,
            'next_due_date' => null,
            'next_due_amount' => 0,
            'open_invoices' => 0,
        ];
    }

    /** @return array{ready: bool, name: string, methods: mixed} */
    private function gatewayData(): array
    {
        return [
            'ready' => config('payments.driver') !== null && config('payments.checkout_ready') === true,
            'name' => (string) config('payments.display_name'),
            'methods' => config('payments.methods'),
        ];
    }

    private function methodLabel(string $method, ?string $cardBrand): string
    {
        return match ($method) {
            'mobile_money' => 'Mobile money',
            'bank_transfer' => 'Bank transfer',
            'card' => $cardBrand === null ? 'Card' : ucfirst($cardBrand).' card',
            'cash' => 'Cash',
            'payment_link' => 'Payment link',
            default => 'Other',
        };
    }
}
