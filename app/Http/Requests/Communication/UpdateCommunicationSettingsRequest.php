<?php

namespace App\Http\Requests\Communication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunicationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $channels = ['database', 'email', 'sms', 'whatsapp'];

        return [
            'in_app_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'email_from_name' => ['nullable', 'string', 'max:120'],
            'email_from_address' => ['nullable', 'email', 'max:255'],
            'sms_enabled' => ['required', 'boolean'],
            'sms_provider' => ['required', Rule::in(['africas_talking', 'generic_webhook'])],
            'sms_sender_id' => ['nullable', 'string', 'max:30'],
            'sms_username' => ['nullable', 'string', 'max:255'],
            'sms_api_key' => ['nullable', 'string', 'max:2000'],
            'sms_webhook_url' => ['nullable', 'url:http,https', 'max:2000'],
            'sms_webhook_token' => ['nullable', 'string', 'max:2000'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'whatsapp_provider' => ['required', Rule::in(['meta_cloud'])],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:255'],
            'whatsapp_access_token' => ['nullable', 'string', 'max:5000'],
            'whatsapp_api_version' => ['required', 'regex:/^v\d{1,3}\.\d{1,2}$/'],
            'whatsapp_notice_template' => ['nullable', 'string', 'max:255'],
            'whatsapp_invoice_template' => ['nullable', 'string', 'max:255'],
            'whatsapp_receipt_template' => ['nullable', 'string', 'max:255'],
            'whatsapp_reminder_template' => ['nullable', 'string', 'max:255'],
            'invoice_channels' => ['required', 'array', 'min:1'],
            'invoice_channels.*' => [Rule::in($channels)],
            'receipt_channels' => ['required', 'array', 'min:1'],
            'receipt_channels.*' => [Rule::in($channels)],
            'reminder_channels' => ['required', 'array', 'min:1'],
            'reminder_channels.*' => [Rule::in($channels)],
            'reminder_days_before' => ['required', 'array', 'min:1'],
            'reminder_days_before.*' => ['integer', 'min:0', 'max:30', 'distinct'],
            'overdue_repeat_days' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'email_from_name',
            'email_from_address',
            'sms_sender_id',
            'sms_username',
            'sms_api_key',
            'sms_webhook_url',
            'sms_webhook_token',
            'whatsapp_phone_number_id',
            'whatsapp_access_token',
            'whatsapp_notice_template',
            'whatsapp_invoice_template',
            'whatsapp_receipt_template',
            'whatsapp_reminder_template',
        ] as $key) {
            $value = trim((string) $this->input($key));
            $this->merge([$key => $value === '' ? null : $value]);
        }
    }
}
