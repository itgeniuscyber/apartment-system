<?php

namespace App\Services\Communication;

use App\Jobs\DeliverTenantCommunication;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationSetting;
use App\Models\Tenant;
use App\Notifications\TenantEventNotification;
use Illuminate\Support\Facades\Notification;

class TenantNotifier
{
    /**
     * @param  list<string>  $channels
     */
    public function send(Tenant $tenant, TenantEventNotification $notification, array $channels): void
    {
        $settings = CommunicationSetting::current();
        $channels = array_values(array_unique($channels));

        if ($settings->in_app_enabled && in_array('database', $channels, true)) {
            Notification::sendNow($tenant, $notification, ['database']);
        }

        foreach (['email', 'sms', 'whatsapp'] as $channel) {
            if (! in_array($channel, $channels, true)) {
                continue;
            }

            $target = $channel === 'email'
                ? $tenant->email
                : ($tenant->phone ?? $tenant->alternative_phone);
            $enabled = match ($channel) {
                'email' => $settings->email_enabled,
                'sms' => $settings->sms_enabled,
                'whatsapp' => $settings->whatsapp_enabled,
            };
            $provider = match ($channel) {
                'email' => (string) config('mail.default'),
                'sms' => $settings->sms_provider,
                'whatsapp' => $settings->whatsapp_provider,
            };
            $skipReason = ! $enabled
                ? ucfirst($channel).' is disabled in communication settings.'
                : ($target === null ? 'Tenant has no destination for this channel.' : null);

            $delivery = CommunicationDelivery::query()->create([
                'notification_id' => $notification->id,
                'tenant_id' => $tenant->id,
                'event_type' => $notification->eventType,
                'reference' => $notification->reference,
                'channel' => $channel,
                'provider' => $provider,
                'recipient_masked' => $this->mask($target),
                'status' => $skipReason === null ? 'queued' : 'skipped',
                'error_message' => $skipReason,
            ]);

            if ($skipReason === null) {
                DeliverTenantCommunication::dispatch($delivery->id, $notification->payload())
                    ->onConnection('deferred');
            }
        }
    }

    private function mask(?string $target): ?string
    {
        if ($target === null) {
            return null;
        }

        if (str_contains($target, '@')) {
            [$name, $domain] = explode('@', $target, 2);

            return mb_substr($name, 0, 2).'•••@'.$domain;
        }

        return '••••'.substr(preg_replace('/\D+/', '', $target) ?? '', -4);
    }
}
