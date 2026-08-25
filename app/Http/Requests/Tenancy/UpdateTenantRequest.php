<?php

namespace App\Http\Requests\Tenancy;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManagement() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'nullable',
                'required_without:phone',
                'email',
                'max:255',
                Rule::unique('tenants', 'email')->ignore($tenant->id),
                Rule::unique('users', 'email')->ignore($tenant->user_id),
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
            'identity_document_number' => ['nullable', 'string', 'max:100'],
            'identity_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'next_of_kin_name' => ['required', 'string', 'max:150'],
            'next_of_kin_relationship' => ['required', 'string', 'max:100'],
            'next_of_kin_phone' => ['required', 'string', 'max:30'],
            'next_of_kin_email' => ['nullable', 'email', 'max:255'],
            'next_of_kin_address' => ['nullable', 'string', 'max:500'],
            'preferred_channel' => ['required', Rule::in(['whatsapp', 'sms', 'email'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Tenant $tenant */
                $tenant = $this->route('tenant');
                $hasExistingIdentity = $tenant->identity_document_number !== null
                    || $tenant->identity_document_path !== null;

                if (! $hasExistingIdentity
                    && $this->input('identity_document_number') === null
                    && ! $this->hasFile('identity_document')) {
                    $validator->errors()->add(
                        'identity_document_number',
                        'Enter an identity number or upload an identity document.',
                    );
                }

                if ($tenant->user_id !== null && $this->input('email') === null) {
                    $validator->errors()->add(
                        'email',
                        'This tenant already has portal access, so a login email is required.',
                    );
                }
            },
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
