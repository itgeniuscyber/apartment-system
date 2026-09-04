<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lease_id
 * @property int $previous_amount
 * @property int $amount
 * @property Carbon $effective_from
 * @property string $reason
 * @property string|null $notes
 * @property string $status
 * @property bool $notify_tenant
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $notified_at
 * @property-read Lease $lease
 * @property-read User|null $creator
 * @property-read User|null $approver
 */
#[Fillable([
    'lease_id', 'previous_amount', 'amount', 'effective_from', 'reason', 'notes',
    'status', 'notify_tenant', 'created_by', 'approved_by', 'approved_at', 'notified_at',
])]
class LeaseRentRevision extends Model
{
    protected function casts(): array
    {
        return [
            'previous_amount' => 'integer',
            'amount' => 'integer',
            'effective_from' => 'date',
            'notify_tenant' => 'boolean',
            'approved_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
