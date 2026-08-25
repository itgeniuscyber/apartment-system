<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManagement() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in([
                'mobile_money',
                'bank_transfer',
                'card',
                'cash',
                'payment_link',
                'other',
            ])],
            'provider_reference' => [
                'nullable',
                Rule::requiredIf(fn (): bool => in_array($this->input('payment_method'), [
                    'mobile_money',
                    'bank_transfer',
                    'card',
                    'payment_link',
                ], true)),
                'string',
                'max:120',
            ],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payer_phone' => ['nullable', 'string', 'max:30'],
            'payer_email' => ['nullable', 'email', 'max:255'],
            'card_brand' => ['nullable', 'required_if:payment_method,card', Rule::in(['visa', 'mastercard'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider_reference' => $this->nullableTrimmed('provider_reference'),
            'payer_phone' => $this->nullableTrimmed('payer_phone'),
            'payer_email' => $this->nullableLowercase('payer_email'),
            'card_brand' => $this->nullableTrimmed('card_brand'),
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
