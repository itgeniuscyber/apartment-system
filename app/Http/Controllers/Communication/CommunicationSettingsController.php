<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\UpdateCommunicationSettingsRequest;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommunicationSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $settings = CommunicationSetting::current();
        $deliveries = CommunicationDelivery::query()
            ->with('tenant')
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('communication/Settings', [
            'settings' => [
                'in_app_enabled' => $settings->in_app_enabled,
                'email_enabled' => $settings->email_enabled,
                'email_from_name' => $settings->email_from_name ?? config('mail.from.name'),
                'email_from_address' => $settings->email_from_address ?? config('mail.from.address'),
                'email_ready' => filled(config('mail.mailers.'.config('mail.default').'.host')),
                'email_provider' => (string) config('mail.default'),
                'sms_enabled' => $settings->sms_enabled,
                'sms_provider' => $settings->sms_provider,
                'sms_sender_id' => $settings->sms_sender_id,
                'sms_username' => $settings->sms_username,
                'sms_api_key_saved' => filled($settings->sms_api_key),
                'sms_webhook_url' => $settings->sms_webhook_url,
                'sms_webhook_token_saved' => filled($settings->sms_webhook_token),
                'sms_ready' => $settings->smsReady(),
                'whatsapp_enabled' => $settings->whatsapp_enabled,
                'whatsapp_provider' => $settings->whatsapp_provider,
                'whatsapp_phone_number_id' => $settings->whatsapp_phone_number_id,
                'whatsapp_access_token_saved' => filled($settings->whatsapp_access_token),
                'whatsapp_api_version' => $settings->whatsapp_api_version,
                'whatsapp_notice_template' => $settings->whatsapp_notice_template,
                'whatsapp_invoice_template' => $settings->whatsapp_invoice_template,
                'whatsapp_receipt_template' => $settings->whatsapp_receipt_template,
                'whatsapp_reminder_template' => $settings->whatsapp_reminder_template,
                'whatsapp_ready' => $settings->whatsappReady(),
                'invoice_channels' => $settings->invoice_channels,
                'receipt_channels' => $settings->receipt_channels,
                'reminder_channels' => $settings->reminder_channels,
                'reminder_days_before' => $settings->reminder_days_before,
                'overdue_repeat_days' => $settings->overdue_repeat_days,
            ],
            'delivery_summary' => [
                'queued' => CommunicationDelivery::query()->where('status', 'queued')->count(),
                'sent' => CommunicationDelivery::query()->where('status', 'sent')->count(),
                'failed' => CommunicationDelivery::query()->where('status', 'failed')->count(),
                'skipped' => CommunicationDelivery::query()->where('status', 'skipped')->count(),
            ],
            'deliveries' => $deliveries->map(fn (CommunicationDelivery $delivery): array => [
                'id' => $delivery->id,
                'tenant' => $delivery->tenant?->full_name,
                'event_type' => $delivery->event_type,
                'reference' => $delivery->reference,
                'channel' => $delivery->channel,
                'provider' => $delivery->provider,
                'recipient' => $delivery->recipient_masked,
                'status' => $delivery->status,
                'attempts' => $delivery->attempts,
                'error' => $delivery->error_message,
                'sent_at' => $delivery->sent_at?->format('d M Y, g:i A'),
                'created_at' => $delivery->created_at?->format('d M Y, g:i A'),
            ])->values(),
        ]);
    }

    public function update(UpdateCommunicationSettingsRequest $request): RedirectResponse
    {
        $settings = CommunicationSetting::current();
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        foreach (['sms_api_key', 'sms_webhook_token', 'whatsapp_access_token'] as $secret) {
            if (($data[$secret] ?? null) === null) {
                unset($data[$secret]);
            }
        }

        $settings->update([...$data, 'updated_by' => $request->user()?->id]);

        return to_route('communication.settings.index')->with('success', 'Communication providers and automatic delivery rules updated.');
    }
}
