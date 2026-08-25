<?php

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estate_id' => ['required', 'integer', Rule::exists('estates', 'id')],
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required',
                'string',
                'max:24',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('sections', 'code')->where('estate_id', $this->integer('estate_id')),
            ],
            'unit_count' => ['required', 'integer', 'min:1', 'max:200'],
            'unit_prefix' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/'],
            'start_number' => ['required', 'integer', 'min:1', 'max:9999'],
            'default_rent' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $prefix = $this->normaliseCode((string) $this->input('unit_prefix'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => $this->normaliseCode((string) $this->input('code')),
            'unit_prefix' => $prefix === '' ? null : $prefix,
        ]);
    }

    private function normaliseCode(string $value): string
    {
        return trim((string) preg_replace('/[^A-Z0-9]+/', '-', strtoupper($value)), '-');
    }
}
