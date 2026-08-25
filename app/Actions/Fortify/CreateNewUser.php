<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantIdentityStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private TenantIdentityStorage $identityStorage) {}

    /**
     * Validate and create a newly registered resident.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $input = $this->normalise($input);

        Validator::make($input, [
            ...$this->profileRules(),
            'phone' => ['required', 'string', 'max:30'],
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
            'password' => $this->passwordRules(),
        ])->validate();

        $document = ($input['identity_document'] ?? null) instanceof UploadedFile
            ? $input['identity_document']
            : null;
        $existingTenant = Tenant::query()->where('email', (string) $input['email'])->first();
        $documentData = [];

        if ($existingTenant !== null) {
            throw ValidationException::withMessages([
                'email' => $existingTenant->user_id === null
                    ? 'Management has already created a resident profile for this email. Ask management to send your secure activation link.'
                    : 'This resident profile already has portal access. Use sign in or reset the password.',
            ]);
        }

        if ($document !== null) {
            $documentData = $this->identityStorage->store($document);
        }

        try {
            return DB::transaction(function () use ($input, $documentData): User {
                $user = User::create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => $input['password'],
                    'role' => 'tenant',
                ]);

                $nameParts = preg_split('/\s+/', trim((string) $input['name']), 2) ?: [];
                $identifier = preg_replace('/[^\pL\pN]/u', '', (string) ($input['identity_document_number'] ?? '')) ?: null;
                $profileData = [
                    'user_id' => $user->id,
                    'first_name' => $nameParts[0] ?? (string) $input['name'],
                    'last_name' => $nameParts[1] ?? '',
                    'email' => $user->email,
                    'phone' => $input['phone'],
                    'alternative_phone' => $input['alternative_phone'] ?? null,
                    'identity_document_type' => $input['identity_document_type'],
                    'identity_document_number' => $input['identity_document_number'] ?? null,
                    'identity_document_last_four' => $identifier === null ? null : Str::substr($identifier, -4),
                    ...$documentData,
                    'next_of_kin_name' => $input['next_of_kin_name'],
                    'next_of_kin_relationship' => $input['next_of_kin_relationship'],
                    'next_of_kin_phone' => $input['next_of_kin_phone'],
                    'next_of_kin_email' => $input['next_of_kin_email'] ?? null,
                    'next_of_kin_address' => $input['next_of_kin_address'] ?? null,
                    'preferred_channel' => $input['preferred_channel'],
                    'portal_activated_at' => now(),
                ];

                Tenant::query()->create([
                    ...$profileData,
                    'status' => 'pending',
                ]);

                return $user;
            });
        } catch (\Throwable $exception) {
            $this->identityStorage->delete($documentData['identity_document_path'] ?? null);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalise(array $input): array
    {
        foreach ([
            'name',
            'phone',
            'alternative_phone',
            'identity_document_number',
            'next_of_kin_name',
            'next_of_kin_relationship',
            'next_of_kin_phone',
            'next_of_kin_address',
        ] as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $value = trim($input[$key]);
                $input[$key] = $value === '' ? null : $value;
            }
        }

        foreach (['email', 'next_of_kin_email'] as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $value = strtolower(trim($input[$key]));
                $input[$key] = $value === '' ? null : $value;
            }
        }

        return $input;
    }
}
