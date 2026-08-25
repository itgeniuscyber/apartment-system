<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->notifiable($request)->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->notifiable($request)->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    private function notifiable(Request $request): User|Tenant
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return $user->isManagement()
            ? $user
            : $user->tenantProfile()->firstOrFail();
    }
}
