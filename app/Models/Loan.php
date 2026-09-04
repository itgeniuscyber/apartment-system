<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string $direction
 * @property string $name
 * @property string $counterparty_name
 * @property string|null $counterparty_phone
 * @property string|null $counterparty_email
 * @property int|null $tenant_id
 * @property int $principal_amount
 * @property int $total_payable
 * @property string|null $interest_rate
 * @property int|null $installment_amount
 * @property string $currency
 * @property string $repayment_frequency
 * @property Carbon $start_date
 * @property Carbon|null $next_due_date
 * @property Carbon|null $maturity_date
 * @property int $reminder_days_before
 * @property Carbon|null $last_reminded_on
 * @property string $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Collection<int, LoanPayment> $payments
 * @property-read Tenant|null $tenant
 * @property-read User|null $creator
 */
#[Fillable([
    'reference', 'direction', 'name', 'counterparty_name', 'counterparty_phone',
    'counterparty_email', 'tenant_id', 'principal_amount', 'total_payable',
    'interest_rate', 'installment_amount', 'currency', 'repayment_frequency',
    'start_date', 'next_due_date', 'maturity_date', 'reminder_days_before', 'last_reminded_on',
    'status', 'notes', 'created_by',
])]
class Loan extends Model
{
    protected function casts(): array
    {
        return [
            'principal_amount' => 'integer',
            'total_payable' => 'integer',
            'installment_amount' => 'integer',
            'interest_rate' => 'decimal:3',
            'start_date' => 'date',
            'next_due_date' => 'date',
            'maturity_date' => 'date',
            'reminder_days_before' => 'integer',
            'last_reminded_on' => 'date',
        ];
    }

    /** @return HasMany<LoanPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function amountPaid(): int
    {
        return (int) $this->payments->sum('amount');
    }

    public function balance(): int
    {
        return max(0, $this->total_payable - $this->amountPaid());
    }
}
