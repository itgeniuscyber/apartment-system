<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $message_thread_id
 * @property int|null $sender_user_id
 * @property string $sender_role
 * @property string $body
 * @property Carbon|null $read_at
 * @property-read MessageThread $thread
 * @property-read User|null $sender
 */
class ThreadMessage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'message_thread_id',
        'sender_user_id',
        'sender_role',
        'body',
        'read_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return BelongsTo<MessageThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
