<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $estate_id
 * @property string $name
 * @property string $code
 * @property int $sort_order
 * @property int $default_rent
 * @property bool $is_active
 * @property-read Estate $estate
 * @property-read Collection<int, Unit> $units
 */
class Section extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'estate_id',
        'name',
        'code',
        'sort_order',
        'default_rent',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_rent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Estate, $this> */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('number');
    }
}
