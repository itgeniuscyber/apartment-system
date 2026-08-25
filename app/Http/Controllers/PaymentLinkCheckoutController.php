<?php

namespace App\Http\Controllers;

use App\Models\PaymentLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentLinkCheckoutController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $paymentLink = PaymentLink::query()
            ->with(['invoice.unit.section.estate', 'tenant', 'settlementAccount'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        if ($paymentLink->status === 'active' && $paymentLink->expires_at?->isPast()) {
            $paymentLink->update(['status' => 'expired']);
        }

        $invoice = $paymentLink->invoice;
        $isUsable = $paymentLink->fresh()?->isUsable() === true;

        return Inertia::render('public/PaymentCheckout', [
            'paymentLink' => [
                'status' => $paymentLink->fresh()?->status,
                'usable' => $isUsable,
                'expires_at' => $paymentLink->expires_at?->format('d M Y, g:i A'),
                'amount' => min($paymentLink->amount, $invoice->balance_due),
                'currency' => $paymentLink->currency,
            ],
            'invoice' => [
                'invoice_number' => $invoice->invoice_number,
                'description' => $invoice->description,
                'billing_month' => $invoice->billing_period_start->format('F Y'),
                'due_date' => $invoice->due_date->format('d M Y'),
                'status' => $invoice->effectiveStatus(),
                'total_amount' => $invoice->total_amount,
                'amount_paid' => $invoice->amount_paid,
                'balance_due' => $invoice->balance_due,
                'tenant_first_name' => $paymentLink->tenant->first_name,
                'unit' => [
                    'code' => $invoice->unit->code,
                    'number' => $invoice->unit->number,
                    'estate' => $invoice->unit->section->estate->name,
                ],
                'settlement_account' => [
                    'owner_name' => $paymentLink->settlementAccount->owner_name,
                    'bank_name' => $paymentLink->settlementAccount->bank_name,
                    'account_last_four' => $paymentLink->settlementAccount->account_last_four,
                ],
            ],
            'gateway' => [
                'ready' => config('payments.driver') !== null && config('payments.checkout_ready') === true,
                'name' => (string) config('payments.display_name'),
                'methods' => config('payments.methods'),
            ],
            'support' => [
                'email' => (string) config('mail.from.address'),
            ],
        ]);
    }
}
