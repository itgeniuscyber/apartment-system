<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\GatewayWebhookEvent;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Services\Communication\FinancialNotifier;
use App\Services\Finance\PaymentRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GatewayWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        PaymentRecorder $recorder,
        FinancialNotifier $notifier,
    ): JsonResponse {
        $configuredProvider = config('payments.driver');
        $secret = config('payments.webhook_secret');

        if (! is_string($configuredProvider) || $configuredProvider === '' || ! hash_equals($configuredProvider, $provider)) {
            return response()->json(['message' => 'Unknown payment provider.'], 404);
        }

        if (! is_string($secret) || $secret === '') {
            return response()->json(['message' => 'Payment callbacks are not configured.'], 503);
        }

        $content = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $content, $secret);
        $providedSignature = (string) $request->header('X-KR-Signature');

        if ($providedSignature === '' || ! hash_equals($expectedSignature, $providedSignature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = Validator::make($request->json()->all(), [
            'event_id' => ['required', 'string', 'max:160'],
            'attempt_id' => ['required', 'uuid'],
            'status' => ['required', Rule::in(['confirmed', 'failed'])],
            'provider_reference' => ['nullable', 'required_if:status,confirmed', 'string', 'max:120'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'failure_code' => ['nullable', 'string', 'max:80'],
            'failure_message' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $payloadHash = hash('sha256', $content);
        $event = GatewayWebhookEvent::query()->firstOrCreate(
            ['provider' => $provider, 'event_id' => (string) $payload['event_id']],
            ['payload_hash' => $payloadHash, 'payload' => $payload, 'status' => 'received'],
        );

        if (! hash_equals($event->payload_hash, $payloadHash)) {
            return response()->json(['message' => 'Webhook event ID was reused with different data.'], 409);
        }

        if ($event->status === 'processed') {
            return response()->json(['message' => 'Webhook already processed.']);
        }

        $recordedPaymentId = null;

        DB::transaction(function () use ($event, $payload, $provider, $recorder, &$recordedPaymentId): void {
            $lockedEvent = GatewayWebhookEvent::query()->lockForUpdate()->findOrFail($event->id);

            if ($lockedEvent->status === 'processed') {
                return;
            }

            $attempt = PaymentAttempt::query()
                ->where('public_id', (string) $payload['attempt_id'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($attempt->provider === $provider, 422, 'Payment provider mismatch.');
            abort_unless($attempt->amount === (int) $payload['amount'], 422, 'Payment amount mismatch.');
            abort_unless($attempt->currency === strtoupper((string) $payload['currency']), 422, 'Payment currency mismatch.');

            if ($attempt->status === 'confirmed') {
                abort_unless(
                    $payload['status'] === 'confirmed'
                        && $attempt->provider_reference === (string) $payload['provider_reference'],
                    409,
                    'A confirmed payment attempt cannot be changed.',
                );

                $lockedEvent->update(['status' => 'processed', 'processed_at' => now()]);

                return;
            }

            if ($payload['status'] === 'confirmed') {
                $payment = $recorder->record($attempt->invoice_id, [
                    'amount' => $attempt->amount,
                    'payment_method' => $attempt->payment_method,
                    'provider_reference' => (string) $payload['provider_reference'],
                    'paid_at' => now(),
                    'notes' => 'Confirmed by signed '.$provider.' gateway callback.',
                    'idempotency_key' => 'gateway:'.$provider.':'.$payload['event_id'],
                ], null, $attempt);
                $recordedPaymentId = $payment->id;

                $attempt->update([
                    'status' => 'confirmed',
                    'provider_reference' => (string) $payload['provider_reference'],
                    'confirmed_at' => now(),
                ]);
                $attempt->paymentLink?->update(['status' => 'used', 'used_at' => now()]);
            } else {
                $attempt->update([
                    'status' => 'failed',
                    'failure_code' => $payload['failure_code'] ?? null,
                    'failure_message' => $payload['failure_message'] ?? null,
                ]);
            }

            $lockedEvent->update(['status' => 'processed', 'processed_at' => now()]);
        });

        if ($recordedPaymentId !== null) {
            $payment = Payment::query()->with('tenant')->findOrFail($recordedPaymentId);
            $notifier->paymentReceipt($payment);
        }

        return response()->json(['message' => 'Webhook processed.']);
    }
}
