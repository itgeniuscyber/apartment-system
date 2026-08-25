<?php

namespace App\Jobs;

use App\Models\CommunicationDelivery;
use App\Models\Tenant;
use App\Notifications\TenantEventNotification;
use App\Services\Communication\CommunicationGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class DeliverTenantCommunication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, scalar|null>  $notificationPayload
     */
    public function __construct(
        public int $deliveryId,
        public array $notificationPayload,
    ) {}

    public function handle(CommunicationGateway $gateway): void
    {
        $delivery = CommunicationDelivery::query()->findOrFail($this->deliveryId);

        if ($delivery->status === 'sent') {
            return;
        }

        $tenant = Tenant::query()->findOrFail($delivery->tenant_id);
        $notification = TenantEventNotification::fromPayload($this->notificationPayload);
        $delivery->increment('attempts');

        try {
            $providerMessageId = match ($delivery->channel) {
                'email' => $this->sendEmail($tenant, $notification),
                'sms' => $gateway->sendSms($tenant, $notification),
                'whatsapp' => $gateway->sendWhatsapp($tenant, $notification),
                default => throw new \RuntimeException('Unsupported communication channel.'),
            };

            $delivery->update([
                'status' => 'sent',
                'provider_message_id' => $providerMessageId,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => $this->attempts() >= $this->tries ? 'failed' : 'queued',
                'error_message' => str($exception->getMessage())->limit(1800),
            ]);

            throw $exception;
        }
    }

    private function sendEmail(Tenant $tenant, TenantEventNotification $notification): null
    {
        Notification::sendNow($tenant, $notification, ['mail']);

        return null;
    }
}
