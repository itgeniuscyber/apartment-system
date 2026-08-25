<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $notice_id
 * @property int $tenant_id
 * @property Carbon|null $read_at
 * @property-read Notice $notice
 * @property-read Tenant $tenant
 */
class NoticeRecipient extends Model
{
    /** @var list<string> */
    protected $fillable = ['notice_id', 'tenant_id', 'read_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return BelongsTo<Notice, $this> */
    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
