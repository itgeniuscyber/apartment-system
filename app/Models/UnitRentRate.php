<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $unit_id
 * @property int $amount
 * @property string $effective_from
 * @property string|null $effective_to
 * @property string $source
 * @property string|null $notes
 * @property int|null $created_by
 */
class UnitRentRate extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'unit_id',
        'amount',
        'effective_from',
        'effective_to',
        'source',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
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
}
