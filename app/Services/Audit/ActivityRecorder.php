<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(Request $request, string $action, string $description, ?Model $subject = null, array $metadata = []): void
    {
        ActivityLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request->ip(),
        ]);
    }
}
