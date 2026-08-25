<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantNotificationEvent extends Model
{
    /** @var list<string> */
    protected $fillable = ['tenant_id', 'event_type', 'reference', 'scheduled_for'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['scheduled_for' => 'date'];
    }
}
