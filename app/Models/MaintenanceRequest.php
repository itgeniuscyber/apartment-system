<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $tenant_id
 * @property int|null $lease_id
 * @property int|null $unit_id
 * @property string $category
 * @property string $title
 * @property string $description
 * @property string $priority
 * @property string $status
 * @property bool $access_permission
 * @property Carbon|null $preferred_visit_at
 * @property string|null $evidence_path
 * @property string|null $evidence_original_name
 * @property int|null $assigned_to
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property-read Tenant $tenant
 * @property-read Lease|null $lease
 * @property-read Unit|null $unit
 * @property-read User|null $assignee
 * @property-read Collection<int, MaintenanceUpdate> $updates
 */
class MaintenanceRequest extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'reference',
        'tenant_id',
        'lease_id',
        'unit_id',
        'category',
        'title',
        'description',
        'priority',
        'status',
        'access_permission',
        'preferred_visit_at',
        'evidence_path',
        'evidence_original_name',
        'evidence_mime',
        'evidence_size',
        'assigned_to',
        'acknowledged_at',
        'resolved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'access_permission' => 'boolean',
            'preferred_visit_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Lease, $this> */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<MaintenanceUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(MaintenanceUpdate::class)->oldest();
    }
}
