<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tenantProfile !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['plumbing', 'electrical', 'security', 'appliance', 'structural', 'cleaning', 'other'])],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['normal', 'urgent'])],
            'access_permission' => ['required', 'boolean'],
            'preferred_visit_at' => ['nullable', 'date', 'after:now'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => trim((string) $this->input('description')),
        ]);
    }
}
