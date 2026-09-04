<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $unit_id
 * @property string $lease_number
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property int $rent_amount
 * @property int|null $initial_rent_amount
 * @property int $deposit_amount
 * @property int $due_day
 * @property int $grace_days
 * @property string $billing_frequency
 * @property string $status
 * @property Carbon|null $signed_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property int $invoices_count
 * @property-read Tenant $tenant
 * @property-read Unit $unit
 * @property-read User|null $creator
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, LeaseRentRevision> $rentRevisions
 */
class Lease extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'unit_id',
        'lease_number',
        'start_date',
        'end_date',
        'rent_amount',
        'initial_rent_amount',
        'deposit_amount',
        'due_day',
        'grace_days',
        'billing_frequency',
        'status',
        'signed_at',
        'notes',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'rent_amount' => 'integer',
            'initial_rent_amount' => 'integer',
            'deposit_amount' => 'integer',
            'due_day' => 'integer',
            'grace_days' => 'integer',
            'signed_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('billing_period_start');
    }

    /** @return HasMany<LeaseRentRevision, $this> */
    public function rentRevisions(): HasMany
    {
        return $this->hasMany(LeaseRentRevision::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    public function rentForDate(CarbonInterface $date): int
    {
        $revision = $this->relationLoaded('rentRevisions')
            ? $this->rentRevisions
                ->where('status', 'approved')
                ->filter(fn (LeaseRentRevision $item): bool => $item->effective_from->lte($date))
                ->sortByDesc('effective_from')
                ->first()
            : $this->rentRevisions()
                ->where('status', 'approved')
                ->whereDate('effective_from', '<=', $date)
                ->first();

        if ($revision instanceof LeaseRentRevision) {
            return $revision->amount;
        }

        return $this->initial_rent_amount ?? $this->rent_amount;
    }
}
