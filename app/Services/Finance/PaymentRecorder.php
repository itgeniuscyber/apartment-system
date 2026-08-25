<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRecorder
{
    /**
     * @param  array{
     *     amount: int,
     *     payment_method: string,
     *     provider_reference?: string|null,
     *     paid_at: mixed,
     *     payer_phone?: string|null,
     *     payer_email?: string|null,
     *     card_brand?: string|null,
     *     notes?: string|null,
     *     idempotency_key?: string|null
     * }  $data
     */
    public function record(int $invoiceId, array $data, ?int $recordedBy = null, ?PaymentAttempt $attempt = null): Payment
    {
        return DB::transaction(function () use ($invoiceId, $data, $recordedBy, $attempt): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoiceId);
            $amount = (int) $data['amount'];
            $providerReference = $data['provider_reference'] ?? null;

            if ($attempt !== null) {
                $existingPayment = Payment::query()->where('payment_attempt_id', $attempt->id)->first();

                if ($existingPayment !== null) {
                    return $existingPayment;
                }
            }

            if ($providerReference !== null && Payment::query()
                ->where('payment_method', $data['payment_method'])
                ->where('provider_reference', $providerReference)
                ->exists()) {
                throw ValidationException::withMessages([
                    'provider_reference' => 'This provider reference has already been recorded for the selected payment method.',
                ]);
            }

            if (in_array($invoice->status, ['paid', 'void'], true) || $invoice->balance_due === 0) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'This invoice no longer has an outstanding balance.',
                ]);
            }

            if ($amount < 1 || $amount > $invoice->balance_due) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment must be between UGX 1 and the invoice balance of UGX '.number_format($invoice->balance_due).'.',
                ]);
            }

            if ($attempt !== null && ($attempt->invoice_id !== $invoice->id
                || $attempt->tenant_id !== $invoice->tenant_id
                || $attempt->settlement_account_id !== $invoice->settlement_account_id
                || $attempt->amount !== $amount
                || $attempt->currency !== $invoice->currency)) {
                throw ValidationException::withMessages([
                    'payment_attempt' => 'The gateway payment does not match this invoice.',
                ]);
            }

            $nextId = ((int) Payment::query()->max('id')) + 1;
            $payment = Payment::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'settlement_account_id' => $invoice->settlement_account_id,
                'payment_attempt_id' => $attempt?->id,
                'payment_number' => sprintf('PAY-%s-%06d', now()->format('Y'), $nextId),
                'receipt_number' => sprintf('RCT-%s-%06d', now()->format('Y'), $nextId),
                'payment_method' => $data['payment_method'],
                'provider_reference' => $providerReference,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'amount' => $amount,
                'currency' => $invoice->currency,
                'paid_at' => $data['paid_at'],
                'status' => 'confirmed',
                'payer_phone' => $data['payer_phone'] ?? null,
                'payer_email' => $data['payer_email'] ?? null,
                'card_brand' => $data['card_brand'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $recordedBy,
            ]);

            $payment->allocations()->create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
            ]);

            $newAmountPaid = $invoice->amount_paid + $amount;
            $newBalance = $invoice->total_amount - $newAmountPaid;

            $invoice->update([
                'amount_paid' => $newAmountPaid,
                'balance_due' => $newBalance,
                'status' => $newBalance === 0 ? 'paid' : 'partially_paid',
                'paid_at' => $newBalance === 0 ? now() : null,
            ]);

            return $payment;
        });
    }
}
