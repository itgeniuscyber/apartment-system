<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $subject
 * @property string $body
 * @property string $severity
 * @property string $audience_type
 * @property int|null $estate_id
 * @property int|null $tenant_id
 * @property list<string> $channels
 * @property int $recipient_count
 * @property int|null $created_by
 * @property Carbon $published_at
 * @property-read Estate|null $estate
 * @property-read Tenant|null $tenant
 * @property-read User|null $creator
 * @property-read Collection<int, NoticeRecipient> $recipients
 */
class Notice extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'subject',
        'body',
        'severity',
        'audience_type',
        'estate_id',
        'tenant_id',
        'channels',
        'recipient_count',
        'created_by',
        'published_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'recipient_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Estate, $this> */
    public function estate(): BelongsTo
    {
        return $this->belongsTo(Estate::class);
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

    /** @return HasMany<NoticeRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(NoticeRecipient::class);
    }
}
