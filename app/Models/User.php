<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\TenantPortalInvitation;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property bool $is_active
 * @property list<string>|null $permissions
 * @property Carbon|null $deactivated_at
 * @property int|null $deactivated_by
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenantProfile
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'permissions', 'deactivated_at', 'deactivated_by'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    public const ADMIN_PERMISSIONS = [
        'portfolio.manage',
        'tenants.manage',
        'leases.manage',
        'finance.operations',
        'maintenance.manage',
        'messages.manage',
        'cashflow.manage',
        'reports.view',
    ];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'permissions' => 'array',
            'deactivated_at' => 'datetime',
        ];
    }

    public function isManagement(): bool
    {
        return in_array($this->role, ['super_admin', 'administrator', 'landlord', 'manager'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'administrator';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->isManagement() || ! $this->is_active) {
            return false;
        }

        // Legacy management users retain operational access until a Super Admin customizes it.
        return in_array($permission, $this->permissions ?? self::ADMIN_PERMISSIONS, true);
    }

    public function sendPasswordResetNotification($token): void
    {
        if ($this->tenantProfile !== null && $this->tenantProfile->portal_activated_at === null) {
            $this->notify(new TenantPortalInvitation((string) $token));

            return;
        }

        $this->notify(new ResetPassword((string) $token));
    }

    /** @return HasOne<Tenant, $this> */
    public function tenantProfile(): HasOne
    {
        return $this->hasOne(Tenant::class);
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
