<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $subject
 * @property string $category
 * @property string $status
 * @property Carbon $last_message_at
 * @property int|null $created_by
 * @property-read Tenant $tenant
 * @property-read User|null $creator
 * @property-read Collection<int, ThreadMessage> $messages
 */
class MessageThread extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'subject',
        'category',
        'status',
        'last_message_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ThreadMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ThreadMessage::class)->oldest();
    }
}
