<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Services\Communication\FinancialNotifier;
use App\Services\Finance\PaymentRecorder;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        PaymentRecorder $recorder,
        FinancialNotifier $notifier,
    ): RedirectResponse {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $payment = $recorder->record((int) $data['invoice_id'], [
            'amount' => (int) $data['amount'],
            'payment_method' => (string) $data['payment_method'],
            'provider_reference' => $this->nullableString($data['provider_reference'] ?? null),
            'paid_at' => $data['paid_at'],
            'payer_phone' => $this->nullableString($data['payer_phone'] ?? null),
            'payer_email' => $this->nullableString($data['payer_email'] ?? null),
            'card_brand' => $this->nullableString($data['card_brand'] ?? null),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ], $request->user()?->id);
        $notifier->paymentReceipt($payment);

        return to_route('finance.index')->with('success', 'Payment recorded and allocated to the correct settlement account.');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
