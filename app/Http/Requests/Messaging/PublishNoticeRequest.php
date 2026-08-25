<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManagement() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(['info', 'success', 'warning', 'urgent'])],
            'audience_type' => ['required', Rule::in(['all', 'estate', 'tenant'])],
            'estate_id' => ['nullable', 'required_if:audience_type,estate', 'integer', Rule::exists('estates', 'id')],
            'tenant_id' => ['nullable', 'required_if:audience_type,tenant', 'integer', Rule::exists('tenants', 'id')],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['database', 'email', 'sms', 'whatsapp'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => trim((string) $this->input('subject')),
            'body' => trim((string) $this->input('body')),
        ]);
    }
}
