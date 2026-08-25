<?php

namespace App\Services\Communication;

use App\Models\User;
use App\Notifications\ManagementEventNotification;
use Illuminate\Support\Facades\Notification;

class ManagementNotifier
{
    public function send(ManagementEventNotification $notification): void
    {
        $recipients = User::query()
            ->whereIn('role', ['administrator', 'landlord', 'manager'])
            ->get();

        Notification::sendNow($recipients, $notification, ['database']);
    }
}
