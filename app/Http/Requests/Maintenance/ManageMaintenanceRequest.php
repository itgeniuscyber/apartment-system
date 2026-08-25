<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManageMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManagement() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'acknowledged', 'in_progress', 'resolved', 'closed'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'urgent', 'emergency'])],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', ['administrator', 'landlord', 'manager'])],
            'body' => ['nullable', 'string', 'max:4000'],
            'is_internal' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $body = trim((string) $this->input('body'));
        $this->merge(['body' => $body === '' ? null : $body]);
    }
}
