<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $maintenance_request_id
 * @property int|null $user_id
 * @property string $body
 * @property string|null $status
 * @property bool $is_internal
 * @property-read MaintenanceRequest $maintenanceRequest
 * @property-read User|null $user
 */
class MaintenanceUpdate extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'maintenance_request_id',
        'user_id',
        'body',
        'status',
        'is_internal',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    /** @return BelongsTo<MaintenanceRequest, $this> */
    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
