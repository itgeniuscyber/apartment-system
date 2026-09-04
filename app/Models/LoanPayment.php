<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $loan_id
 * @property int $amount
 * @property Carbon $paid_on
 * @property string $payment_method
 * @property string|null $reference
 * @property Carbon|null $next_due_date
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property-read Loan $loan
 * @property-read User|null $recorder
 */
#[Fillable(['loan_id', 'amount', 'paid_on', 'payment_method', 'reference', 'next_due_date', 'notes', 'recorded_by'])]
class LoanPayment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => 'date',
            'next_due_date' => 'date',
        ];
    }

    /** @return BelongsTo<Loan, $this> */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
