<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Z0-9-]+$/', Rule::unique('estates', 'code')],
            'location' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
            'settlement_account_id' => ['nullable', 'integer', Rule::exists('settlement_accounts', 'id')],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => $this->normaliseCode((string) $this->input('code')),
            'location' => $this->trimNullable('location'),
            'address' => $this->trimNullable('address'),
        ]);
    }

    private function normaliseCode(string $value): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', '-', strtoupper($value)), '-');
    }

    private function trimNullable(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
