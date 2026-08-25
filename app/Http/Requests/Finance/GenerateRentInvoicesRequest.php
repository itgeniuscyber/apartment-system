<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class GenerateRentInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManagement() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'billing_month' => ['required', 'date_format:Y-m'],
        ];
    }
}
