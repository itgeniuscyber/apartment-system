<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $section_id
 * @property string $code
 * @property string $number
 * @property int $rent_amount
 * @property string $status
 * @property int|null $bedrooms
 * @property float|null $bathrooms
 * @property string|null $floor
 * @property string|null $notes
 * @property bool $is_active
 * @property-read Section $section
 * @property-read Collection<int, UnitRentRate> $rentRates
 * @property-read Collection<int, Lease> $leases
 * @property-read Lease|null $activeLease
 * @property-read Collection<int, Invoice> $invoices
 */
class Unit extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'section_id',
        'code',
        'number',
        'rent_amount',
        'status',
        'bedrooms',
        'bathrooms',
        'floor',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rent_amount' => 'integer',
            'bathrooms' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return HasMany<UnitRentRate, $this> */
    public function rentRates(): HasMany
    {
        return $this->hasMany(UnitRentRate::class)->orderByDesc('effective_from');
    }

    /** @return HasMany<Lease, $this> */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class)->orderByDesc('start_date');
    }

    /** @return HasOne<Lease, $this> */
    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)->where('status', 'active')->latestOfMany('start_date');
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('due_date');
    }
}
