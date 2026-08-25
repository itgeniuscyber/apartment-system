<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $tenant_id
 * @property int $settlement_account_id
 * @property string $token
 * @property string $token_hash
 * @property string $token_last_eight
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $used_at
 * @property int|null $created_by
 * @property-read Invoice $invoice
 * @property-read Tenant $tenant
 * @property-read SettlementAccount $settlementAccount
 * @property-read User|null $creator
 * @property-read Collection<int, PaymentAttempt> $attempts
 */
class PaymentLink extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'invoice_id',
        'tenant_id',
        'settlement_account_id',
        'token',
        'token_hash',
        'token_last_eight',
        'amount',
        'currency',
        'status',
        'expires_at',
        'used_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'amount' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->invoice->balance_due > 0
            && ! in_array($this->invoice->status, ['paid', 'void'], true);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<SettlementAccount, $this> */
    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(SettlementAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
