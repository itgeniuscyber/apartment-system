<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $notification_id
 * @property int|null $tenant_id
 * @property string $event_type
 * @property string|null $reference
 * @property string $channel
 * @property string|null $provider
 * @property string|null $recipient_masked
 * @property string $status
 * @property int $attempts
 * @property string|null $provider_message_id
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property-read Tenant|null $tenant
 */
class CommunicationDelivery extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'notification_id',
        'tenant_id',
        'event_type',
        'reference',
        'channel',
        'provider',
        'recipient_masked',
        'status',
        'attempts',
        'provider_message_id',
        'error_message',
        'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
