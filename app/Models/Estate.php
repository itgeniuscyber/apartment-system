<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $settlement_account_id
 * @property string $name
 * @property string $code
 * @property string|null $location
 * @property string|null $address
 * @property string|null $description
 * @property string $color
 * @property bool $is_active
 * @property-read SettlementAccount|null $settlementAccount
 * @property-read Collection<int, Section> $sections
 * @property-read Collection<int, Unit> $units
 */
class Estate extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'settlement_account_id',
        'name',
        'code',
        'location',
        'address',
        'description',
        'color',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<SettlementAccount, $this> */
    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(SettlementAccount::class);
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasManyThrough<Unit, Section, $this> */
    public function units(): HasManyThrough
    {
        return $this->hasManyThrough(Unit::class, Section::class);
    }
}
