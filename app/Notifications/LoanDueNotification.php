<?php

namespace App\Notifications;

use App\Models\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoanDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Loan $loan, public bool $overdue) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $action = $this->loan->direction === 'borrowed' ? 'payment is due' : 'collection is due';
        $subject = $this->overdue ? 'Overdue loan action' : 'Upcoming loan reminder';

        return (new MailMessage)
            ->subject("K&R Estates · {$subject}")
            ->greeting('Hello,')
            ->line("{$this->loan->name} ({$this->loan->reference}) {$action} with {$this->loan->counterparty_name}.")
            ->line('Due date: '.$this->loan->next_due_date?->format('d M Y'))
            ->line('Remaining balance: '.$this->loan->currency.' '.number_format($this->loan->balance()))
            ->action('Review loan', route('loans.index'))
            ->line('This reminder was generated from your private Super Admin loan register.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => $this->overdue ? 'loan_overdue' : 'loan_due_soon',
            'title' => $this->overdue ? 'Loan action overdue' : 'Loan action due soon',
            'message' => $this->loan->name.' · '.$this->loan->currency.' '.number_format($this->loan->balance()).' remaining · due '.$this->loan->next_due_date?->format('d M Y'),
            'reference' => $this->loan->reference,
            'action_url' => route('loans.index'),
            'action_label' => 'Review loan',
            'severity' => $this->overdue ? 'danger' : 'warning',
        ];
    }
}
