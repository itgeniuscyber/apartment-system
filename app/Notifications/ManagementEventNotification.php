<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ManagementEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $eventType,
        public string $title,
        public string $message,
        public ?string $reference = null,
        public ?string $actionUrl = null,
        public string $severity = 'info',
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => $this->eventType,
            'title' => $this->title,
            'message' => $this->message,
            'reference' => $this->reference,
            'action_url' => $this->actionUrl,
            'action_label' => 'Review',
            'severity' => $this->severity,
        ];
    }
}
