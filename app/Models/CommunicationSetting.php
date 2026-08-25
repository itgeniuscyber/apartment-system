<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property bool $in_app_enabled
 * @property bool $email_enabled
 * @property bool $sms_enabled
 * @property string $sms_provider
 * @property string|null $sms_username
 * @property string|null $sms_api_key
 * @property string|null $sms_webhook_url
 * @property string|null $sms_webhook_token
 * @property bool $whatsapp_enabled
 * @property string $whatsapp_provider
 * @property string|null $whatsapp_phone_number_id
 * @property string|null $whatsapp_access_token
 * @property string $whatsapp_api_version
 * @property string|null $whatsapp_notice_template
 * @property string|null $whatsapp_invoice_template
 * @property string|null $whatsapp_receipt_template
 * @property string|null $whatsapp_reminder_template
 * @property list<string> $invoice_channels
 * @property list<string> $receipt_channels
 * @property list<string> $reminder_channels
 * @property list<int> $reminder_days_before
 * @property int $overdue_repeat_days
 */
class CommunicationSetting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'in_app_enabled',
        'email_enabled',
        'email_from_name',
        'email_from_address',
        'sms_enabled',
        'sms_provider',
        'sms_sender_id',
        'sms_username',
        'sms_api_key',
        'sms_webhook_url',
        'sms_webhook_token',
        'whatsapp_enabled',
        'whatsapp_provider',
        'whatsapp_phone_number_id',
        'whatsapp_access_token',
        'whatsapp_api_version',
        'whatsapp_notice_template',
        'whatsapp_invoice_template',
        'whatsapp_receipt_template',
        'whatsapp_reminder_template',
        'invoice_channels',
        'receipt_channels',
        'reminder_channels',
        'reminder_days_before',
        'overdue_repeat_days',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'in_app_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'sms_enabled' => 'boolean',
            'sms_api_key' => 'encrypted',
            'sms_webhook_token' => 'encrypted',
            'whatsapp_enabled' => 'boolean',
            'whatsapp_access_token' => 'encrypted',
            'invoice_channels' => 'array',
            'receipt_channels' => 'array',
            'reminder_channels' => 'array',
            'reminder_days_before' => 'array',
            'overdue_repeat_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'invoice_channels' => ['database', 'email'],
            'receipt_channels' => ['database', 'email'],
            'reminder_channels' => ['database', 'email'],
            'reminder_days_before' => [7, 3, 0],
        ]);
    }

    public function smsReady(): bool
    {
        if (! $this->sms_enabled) {
            return false;
        }

        return $this->sms_provider === 'generic_webhook'
            ? filled($this->sms_webhook_url)
            : filled($this->sms_username) && filled($this->sms_api_key);
    }

    public function whatsappReady(): bool
    {
        return $this->whatsapp_enabled
            && filled($this->whatsapp_phone_number_id)
            && filled($this->whatsapp_access_token);
    }
}
