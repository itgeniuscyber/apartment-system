<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $payload_hash
 * @property array<string, mixed> $payload
 * @property string $status
 * @property Carbon|null $processed_at
 * @property string|null $error_message
 */
class GatewayWebhookEvent extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'provider',
        'event_id',
        'payload_hash',
        'payload',
        'status',
        'processed_at',
        'error_message',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'processed_at' => 'datetime',
        ];
    }
}
