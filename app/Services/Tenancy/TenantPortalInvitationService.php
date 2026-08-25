<?php

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantPortalInvitationService
{
    public function send(Tenant $tenant): void
    {
        if ($tenant->email === null) {
            throw ValidationException::withMessages([
                'portal' => 'Add an email address before sending a portal invitation.',
            ]);
        }

        $user = DB::transaction(function () use ($tenant): User {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $existingUser = $lockedTenant->user;

            if ($existingUser !== null) {
                if ($existingUser->isManagement()) {
                    throw ValidationException::withMessages([
                        'portal' => 'A management account cannot be used as a resident portal account.',
                    ]);
                }

                return $existingUser;
            }

            if (User::query()->where('email', $lockedTenant->email)->exists()) {
                throw ValidationException::withMessages([
                    'portal' => 'This email already belongs to another account.',
                ]);
            }

            $user = User::query()->create([
                'name' => $lockedTenant->full_name,
                'email' => $lockedTenant->email,
                'password' => Str::password(64),
                'role' => 'tenant',
            ]);
            $lockedTenant->update(['user_id' => $user->id]);

            return $user;
        });

        Password::broker()->deleteToken($user);
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'portal' => trans($status),
            ]);
        }

        $tenant->update(['portal_invited_at' => now()]);
    }
}
