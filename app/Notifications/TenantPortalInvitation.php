<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantPortalInvitation extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject('Activate your K&R Estates resident portal')
            ->greeting('Welcome to K&R Estates')
            ->line('Management has created your secure resident profile.')
            ->line('Use the button below to choose your private password and activate access to your apartment, rent, receipts, requests, and notices.')
            ->action('Activate resident portal', $url)
            ->line('This activation link expires in '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
            ->line('If you were not expecting this invitation, contact property management and do not use the link.');
    }
}
