<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreatePaymentLinkRequest;
use App\Models\Invoice;
use App\Models\PaymentLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentLinkController extends Controller
{
    public function store(CreatePaymentLinkRequest $request, Invoice $invoice): RedirectResponse
    {
        DB::transaction(function () use ($request, $invoice): void {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($lockedInvoice->balance_due === 0 || in_array($lockedInvoice->status, ['paid', 'void'], true)) {
                throw ValidationException::withMessages([
                    'invoice' => 'A payment link cannot be created for a paid or void invoice.',
                ]);
            }

            PaymentLink::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);

            $token = bin2hex(random_bytes(32));
            $expiryDays = (int) ($request->validated('expires_in_days') ?? config('payments.payment_link_expiry_days'));

            PaymentLink::query()->create([
                'invoice_id' => $lockedInvoice->id,
                'tenant_id' => $lockedInvoice->tenant_id,
                'settlement_account_id' => $lockedInvoice->settlement_account_id,
                'token' => $token,
                'token_hash' => hash('sha256', $token),
                'token_last_eight' => substr($token, -8),
                'amount' => $lockedInvoice->balance_due,
                'currency' => $lockedInvoice->currency,
                'status' => 'active',
                'expires_at' => now()->addDays($expiryDays),
                'created_by' => $request->user()?->id,
            ]);
        });

        return to_route('finance.index')->with('success', 'A secure payment link was created for this invoice.');
    }

    public function revoke(Invoice $invoice, PaymentLink $paymentLink): RedirectResponse
    {
        abort_unless($paymentLink->invoice_id === $invoice->id, 404);

        if ($paymentLink->status === 'active') {
            $paymentLink->update(['status' => 'revoked']);
        }

        return to_route('finance.index')->with('success', 'The payment link was revoked.');
    }
}
