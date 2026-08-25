<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManagement() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }
}
