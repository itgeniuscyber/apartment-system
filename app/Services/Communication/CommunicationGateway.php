<?php

namespace App\Services\Communication;

use App\Models\CommunicationSetting;
use App\Models\Tenant;
use App\Notifications\TenantEventNotification;
use Illuminate\Support\Facades\Http;

class CommunicationGateway
{
    public function sendSms(Tenant $tenant, TenantEventNotification $notification): ?string
    {
        $settings = CommunicationSetting::current();
        $phone = $tenant->phone ?? $tenant->alternative_phone;

        if (! $settings->smsReady() || $phone === null) {
            throw new \RuntimeException('SMS is disabled, incomplete, or the tenant has no phone number.');
        }

        if ($settings->sms_provider === 'generic_webhook') {
            $request = Http::acceptJson()->timeout(12);

            if (filled($settings->sms_webhook_token)) {
                $request = $request->withToken((string) $settings->sms_webhook_token);
            }

            $response = $request->post((string) $settings->sms_webhook_url, [
                'to' => $phone,
                'sender_id' => $settings->sms_sender_id,
                'message' => $notification->message,
                'event' => $notification->eventType,
                'reference' => $notification->reference,
            ])->throw();

            return $this->messageId($response->json());
        }

        $payload = [
            'username' => $settings->sms_username,
            'to' => $phone,
            'message' => $notification->message,
        ];

        if (filled($settings->sms_sender_id)) {
            $payload['from'] = $settings->sms_sender_id;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->withHeaders(['apiKey' => (string) $settings->sms_api_key])
            ->timeout(12)
            ->post('https://api.africastalking.com/version1/messaging', $payload)
            ->throw();

        return $this->messageId($response->json());
    }

    public function sendWhatsapp(Tenant $tenant, TenantEventNotification $notification): ?string
    {
        $settings = CommunicationSetting::current();
        $phone = $tenant->phone ?? $tenant->alternative_phone;

        if (! $settings->whatsappReady() || $phone === null) {
            throw new \RuntimeException('WhatsApp is disabled, incomplete, or the tenant has no phone number.');
        }

        $template = match ($notification->eventType) {
            'invoice_issued' => $settings->whatsapp_invoice_template,
            'payment_receipt' => $settings->whatsapp_receipt_template,
            'rent_reminder', 'rent_overdue' => $settings->whatsapp_reminder_template,
            default => $settings->whatsapp_notice_template,
        };

        if (! filled($template)) {
            throw new \RuntimeException('No approved WhatsApp template is configured for this message type.');
        }

        $parameters = collect([
            $tenant->first_name,
            $notification->title,
            $notification->reference ?? 'K&R Estates',
            str($notification->message)->limit(240)->toString(),
        ])->map(fn (string $value): array => ['type' => 'text', 'text' => $value])->all();

        $response = Http::acceptJson()
            ->withToken((string) $settings->whatsapp_access_token)
            ->timeout(12)
            ->post(sprintf(
                'https://graph.facebook.com/%s/%s/messages',
                $settings->whatsapp_api_version,
                $settings->whatsapp_phone_number_id,
            ), [
                'messaging_product' => 'whatsapp',
                'to' => preg_replace('/\D+/', '', $phone),
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => 'en_US'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => $parameters,
                    ]],
                ],
            ])->throw();

        return $this->messageId($response->json());
    }

    private function messageId(mixed $response): ?string
    {
        if (! is_array($response)) {
            return null;
        }

        $id = data_get($response, 'messages.0.id')
            ?? data_get($response, 'SMSMessageData.Recipients.0.messageId')
            ?? data_get($response, 'id');

        return is_scalar($id) ? (string) $id : null;
    }
}
