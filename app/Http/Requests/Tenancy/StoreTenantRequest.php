<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManagement() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'nullable',
                'required_without:phone',
                'email',
                'max:255',
                Rule::unique('tenants', 'email'),
                Rule::unique('users', 'email'),
            ],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30'],
            'alternative_phone' => ['nullable', 'string', 'max:30'],
            'identity_document_type' => ['required', Rule::in([
                'national_id',
                'passport',
                'driving_permit',
                'refugee_id',
                'other',
            ])],
            'identity_document_number' => ['nullable', 'required_without:identity_document', 'string', 'max:100'],
            'identity_document' => ['nullable', 'required_without:identity_document_number', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'next_of_kin_name' => ['required', 'string', 'max:150'],
            'next_of_kin_relationship' => ['required', 'string', 'max:100'],
            'next_of_kin_phone' => ['required', 'string', 'max:30'],
            'next_of_kin_email' => ['nullable', 'email', 'max:255'],
            'next_of_kin_address' => ['nullable', 'string', 'max:500'],
            'preferred_channel' => ['required', Rule::in(['whatsapp', 'sms', 'email'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'email' => $this->nullableLowercase('email'),
            'phone' => $this->nullableTrimmed('phone'),
            'alternative_phone' => $this->nullableTrimmed('alternative_phone'),
            'identity_document_number' => $this->nullableTrimmed('identity_document_number'),
            'next_of_kin_name' => $this->nullableTrimmed('next_of_kin_name'),
            'next_of_kin_relationship' => $this->nullableTrimmed('next_of_kin_relationship'),
            'next_of_kin_phone' => $this->nullableTrimmed('next_of_kin_phone'),
            'next_of_kin_email' => $this->nullableLowercase('next_of_kin_email'),
            'next_of_kin_address' => $this->nullableTrimmed('next_of_kin_address'),
            'notes' => $this->nullableTrimmed('notes'),
        ]);
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }

    private function nullableLowercase(string $key): ?string
    {
        $value = $this->nullableTrimmed($key);

        return $value === null ? null : strtolower($value);
    }
}
