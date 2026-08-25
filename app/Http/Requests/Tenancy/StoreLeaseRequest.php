<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManagement() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'id')],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'rent_amount' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'deposit_amount' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:30'],
            'status' => ['required', Rule::in(['draft', 'active'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));

        $this->merge(['notes' => $notes === '' ? null : $notes]);
    }
}
