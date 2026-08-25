<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\SettlementAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    public function __invoke(): Response
    {
        $today = CarbonImmutable::today();
        $currentPeriod = $today->startOfMonth();

        $invoices = Invoice::query()
            ->with(['tenant', 'lease', 'unit.section.estate', 'settlementAccount', 'paymentLinks'])
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->get();

        $payments = Payment::query()
            ->with([
                'tenant',
                'settlementAccount',
                'allocations.invoice.unit.section.estate',
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $activeLeases = Lease::query()
            ->with(['unit.section.estate'])
            ->where('status', 'active')
            ->get();

        $outstanding = $invoices->filter(fn (Invoice $invoice): bool => $invoice->status !== 'void' && $invoice->balance_due > 0);
        $overdue = $outstanding->filter(fn (Invoice $invoice): bool => $invoice->effectiveStatus() === 'overdue');
        $invoiced = $invoices->where('status', '!=', 'void')->sum('total_amount');
        $collected = $payments->where('status', 'confirmed')->sum('amount');

        $accounts = SettlementAccount::query()
            ->with('estates')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return Inertia::render('finance/Index', [
            'summary' => [
                'scheduled_monthly' => $activeLeases->sum('rent_amount'),
                'invoiced' => $invoiced,
                'collected' => $collected,
                'outstanding' => $outstanding->sum('balance_due'),
                'overdue' => $overdue->sum('balance_due'),
                'collection_rate' => $invoiced > 0 ? (int) round(($collected / $invoiced) * 100) : 0,
                'active_leases' => $activeLeases->count(),
                'unbilled_current' => $activeLeases->filter(function (Lease $lease) use ($invoices, $currentPeriod): bool {
                    return ! $invoices->contains(fn (Invoice $invoice): bool => $invoice->lease_id === $lease->id
                        && $invoice->billing_period_start->isSameDay($currentPeriod));
                })->count(),
            ],
            'aging' => $this->agingData($outstanding, $today),
            'accounts' => $accounts->map(function (SettlementAccount $account) use ($activeLeases, $invoices, $payments): array {
                $accountInvoices = $invoices->where('settlement_account_id', $account->id)->where('status', '!=', 'void');
                $accountPayments = $payments->where('settlement_account_id', $account->id)->where('status', 'confirmed');

                return [
                    'id' => $account->id,
                    'label' => $account->label,
                    'owner_name' => $account->owner_name,
                    'bank_name' => $account->bank_name,
                    'account_last_four' => $account->account_last_four,
                    'currency' => $account->currency,
                    'estates' => $account->estates->pluck('name')->values(),
                    'scheduled_monthly' => $activeLeases->filter(fn (Lease $lease): bool => $lease->unit->section->estate->settlement_account_id === $account->id)->sum('rent_amount'),
                    'invoiced' => $accountInvoices->sum('total_amount'),
                    'collected' => $accountPayments->sum('amount'),
                    'outstanding' => $accountInvoices->sum('balance_due'),
                    'overdue' => $accountInvoices->filter(fn (Invoice $invoice): bool => $invoice->effectiveStatus() === 'overdue')->sum('balance_due'),
                    'transaction_count' => $accountPayments->count(),
                ];
            })->values(),
            'invoices' => $invoices->map(fn (Invoice $invoice): array => $this->invoiceData($invoice, $today))->values(),
            'payments' => $payments->map(fn (Payment $payment): array => $this->paymentData($payment))->values(),
            'trend' => collect(range(5, 0))->map(function (int $monthsAgo) use ($currentPeriod, $invoices, $payments): array {
                $month = $currentPeriod->subMonths($monthsAgo);

                return [
                    'key' => $month->format('Y-m'),
                    'label' => $month->format('M'),
                    'invoiced' => $invoices->filter(fn (Invoice $invoice): bool => $invoice->status !== 'void'
                        && $invoice->billing_period_start->isSameMonth($month))->sum('total_amount'),
                    'collected' => $payments->filter(fn (Payment $payment): bool => $payment->status === 'confirmed'
                        && $payment->paid_at->isSameMonth($month))->sum('amount'),
                ];
            })->values(),
            'billing_periods' => collect(range(-12, 3))->map(function (int $offset) use ($currentPeriod): array {
                $month = $currentPeriod->addMonths($offset);

                return [
                    'value' => $month->format('Y-m'),
                    'label' => $month->format('F Y'),
                ];
            })->reverse()->values(),
            'current_billing_month' => $currentPeriod->format('Y-m'),
            'today' => $today->toDateString(),
        ]);
    }

    /**
     * @param  Collection<int, Invoice>  $outstanding
     * @return list<array{key: string, label: string, amount: int, count: int}>
     */
    private function agingData(Collection $outstanding, CarbonImmutable $today): array
    {
        $buckets = [
            ['key' => 'current', 'label' => 'Current / not due', 'min' => null, 'max' => 0],
            ['key' => '1_30', 'label' => '1–30 days', 'min' => 1, 'max' => 30],
            ['key' => '31_60', 'label' => '31–60 days', 'min' => 31, 'max' => 60],
            ['key' => '61_90', 'label' => '61–90 days', 'min' => 61, 'max' => 90],
            ['key' => '90_plus', 'label' => 'Over 90 days', 'min' => 91, 'max' => null],
        ];

        return array_map(function (array $bucket) use ($outstanding, $today): array {
            $items = $outstanding->filter(function (Invoice $invoice) use ($bucket, $today): bool {
                $age = $this->ageInDays($invoice, $today);

                if ($bucket['min'] === null) {
                    return $age === 0;
                }

                if ($bucket['max'] === null) {
                    return $age >= $bucket['min'];
                }

                return $age >= $bucket['min'] && $age <= $bucket['max'];
            });

            return [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'amount' => $items->sum('balance_due'),
                'count' => $items->count(),
            ];
        }, $buckets);
    }

    /** @return array<string, mixed> */
    private function invoiceData(Invoice $invoice, CarbonImmutable $today): array
    {
        $paymentLink = $invoice->paymentLinks->first(
            fn ($link): bool => $link->status === 'active'
                && ($link->expires_at === null || $link->expires_at->isFuture()),
        );

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'description' => $invoice->description,
            'billing_month' => $invoice->billing_period_start->format('M Y'),
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'due_date_label' => $invoice->due_date->format('d M Y'),
            'age_days' => $this->ageInDays($invoice, $today),
            'status' => $invoice->effectiveStatus(),
            'total_amount' => $invoice->total_amount,
            'amount_paid' => $invoice->amount_paid,
            'balance_due' => $invoice->balance_due,
            'pdf_url' => route('finance.invoices.pdf', $invoice),
            'payment_link' => $paymentLink === null ? null : [
                'id' => $paymentLink->id,
                'url' => route('payment-links.show', ['token' => $paymentLink->token]),
                'expires_at' => $paymentLink->expires_at?->format('d M Y'),
                'token_last_eight' => $paymentLink->token_last_eight,
            ],
            'tenant' => [
                'id' => $invoice->tenant->id,
                'full_name' => $invoice->tenant->full_name,
                'phone' => $invoice->tenant->phone,
                'email' => $invoice->tenant->email,
            ],
            'unit' => [
                'id' => $invoice->unit->id,
                'code' => $invoice->unit->code,
                'number' => $invoice->unit->number,
                'section' => $invoice->unit->section->name,
                'estate' => $invoice->unit->section->estate->name,
            ],
            'settlement_account' => [
                'id' => $invoice->settlementAccount->id,
                'label' => $invoice->settlementAccount->label,
                'owner_name' => $invoice->settlementAccount->owner_name,
                'account_last_four' => $invoice->settlementAccount->account_last_four,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function paymentData(Payment $payment): array
    {
        $allocation = $payment->allocations->first();
        $invoice = $allocation?->invoice;

        return [
            'id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'receipt_number' => $payment->receipt_number,
            'payment_method' => $payment->payment_method,
            'payment_method_label' => $this->paymentMethodLabel($payment->payment_method, $payment->card_brand),
            'provider_reference' => $payment->provider_reference,
            'amount' => $payment->amount,
            'paid_at' => $payment->paid_at->toDateString(),
            'paid_at_label' => $payment->paid_at->format('d M Y'),
            'status' => $payment->status,
            'receipt_url' => route('finance.payments.receipt', $payment),
            'tenant' => [
                'id' => $payment->tenant->id,
                'full_name' => $payment->tenant->full_name,
                'email' => $payment->tenant->email,
            ],
            'invoice_number' => $invoice?->invoice_number,
            'estate' => $invoice?->unit->section->estate->name,
            'unit_code' => $invoice?->unit->code,
            'settlement_account' => [
                'id' => $payment->settlementAccount->id,
                'label' => $payment->settlementAccount->label,
                'account_last_four' => $payment->settlementAccount->account_last_four,
            ],
        ];
    }

    private function ageInDays(Invoice $invoice, CarbonImmutable $today): int
    {
        return $invoice->due_date->isBefore($today)
            ? (int) $invoice->due_date->diffInDays($today)
            : 0;
    }

    private function paymentMethodLabel(string $method, ?string $cardBrand): string
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
