<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\User;
use App\Notifications\LoanDueNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendLoanReminders extends Command
{
    protected $signature = 'loans:send-reminders {--date= : Date to evaluate in YYYY-MM-DD format}';

    protected $description = 'Email and display due and overdue private loan reminders for Super Admins';

    public function handle(): int
    {
        $today = $this->option('date') === null
            ? CarbonImmutable::today()
            : CarbonImmutable::parse((string) $this->option('date'))->startOfDay();
        $recipients = User::query()->where('role', 'super_admin')->where('is_active', true)->get();
        $sent = 0;

        if ($recipients->isEmpty()) {
            $this->warn('No active Super Admin recipients were found.');

            return self::SUCCESS;
        }

        Loan::query()
            ->with('payments')
            ->where('status', 'active')
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', $today->addDays(60))
            ->orderBy('next_due_date')
            ->each(function (Loan $loan) use ($today, $recipients, &$sent): void {
                $daysUntil = (int) $today->diffInDays($loan->next_due_date, false);

                if ($daysUntil > $loan->reminder_days_before || $loan->last_reminded_on?->isSameDay($today)) {
                    return;
                }

                Notification::send($recipients, new LoanDueNotification($loan, $daysUntil < 0));
                $loan->update(['last_reminded_on' => $today]);
                $sent++;
            });

        $this->info("Queued {$sent} loan reminders for {$today->toDateString()}.");

        return self::SUCCESS;
    }
}
