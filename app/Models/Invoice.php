<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lease_id
 * @property int $tenant_id
 * @property int $unit_id
 * @property int $settlement_account_id
 * @property string $invoice_number
 * @property Carbon $billing_period_start
 * @property Carbon $billing_period_end
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property string $description
 * @property int $total_amount
 * @property int $amount_paid
 * @property int $balance_due
 * @property string $currency
 * @property string $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $paid_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Lease $lease
 * @property-read Tenant $tenant
 * @property-read Unit $unit
 * @property-read SettlementAccount $settlementAccount
 * @property-read User|null $creator
 * @property-read Collection<int, PaymentAllocation> $allocations
 * @property-read Collection<int, PaymentLink> $paymentLinks
 * @property-read Collection<int, PaymentAttempt> $paymentAttempts
 */
class Invoice extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'lease_id',
        'tenant_id',
        'unit_id',
        'settlement_account_id',
        'invoice_number',
        'billing_period_start',
        'billing_period_end',
        'issue_date',
        'due_date',
        'description',
        'total_amount',
        'amount_paid',
        'balance_due',
        'currency',
        'status',
        'sent_at',
        'paid_at',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'total_amount' => 'integer',
            'amount_paid' => 'integer',
            'balance_due' => 'integer',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
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

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasMany<PaymentLink, $this> */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class)->latest();
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->latest();
    }

    public function effectiveStatus(): string
    {
        if (in_array($this->status, ['paid', 'void'], true)) {
            return $this->status;
        }

        if ($this->balance_due > 0 && $this->due_date->isBefore(today())) {
            return 'overdue';
        }

        if ($this->amount_paid > 0) {
            return 'partially_paid';
        }

        return $this->status;
    }
}
