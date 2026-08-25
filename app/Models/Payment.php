<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $settlement_account_id
 * @property int|null $payment_attempt_id
 * @property string $payment_number
 * @property string $receipt_number
 * @property string $payment_method
 * @property string|null $provider_reference
 * @property string|null $idempotency_key
 * @property int $amount
 * @property string $currency
 * @property Carbon $paid_at
 * @property string $status
 * @property string|null $payer_phone
 * @property string|null $payer_email
 * @property string|null $card_brand
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property-read Tenant $tenant
 * @property-read SettlementAccount $settlementAccount
 * @property-read User|null $recorder
 * @property-read PaymentAttempt|null $paymentAttempt
 * @property-read Collection<int, PaymentAllocation> $allocations
 */
class Payment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'settlement_account_id',
        'payment_attempt_id',
        'payment_number',
        'receipt_number',
        'payment_method',
        'provider_reference',
        'idempotency_key',
        'amount',
        'currency',
        'paid_at',
        'status',
        'payer_phone',
        'payer_email',
        'card_brand',
        'notes',
        'recorded_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
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
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return BelongsTo<PaymentAttempt, $this> */
    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
