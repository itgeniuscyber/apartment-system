<?php

namespace App\Console\Commands;

use App\Models\CommunicationSetting;
use App\Models\Invoice;
use App\Models\TenantNotificationEvent;
use App\Services\Communication\FinancialNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendRentReminders extends Command
{
    protected $signature = 'rent:send-reminders {--date= : Date to evaluate in YYYY-MM-DD format}';

    protected $description = 'Send deduplicated upcoming and overdue rent notifications';

    public function handle(FinancialNotifier $notifier): int
    {
        $today = $this->option('date') === null
            ? CarbonImmutable::today()
            : CarbonImmutable::parse((string) $this->option('date'))->startOfDay();
        $settings = CommunicationSetting::current();
        $daysBefore = $settings->reminder_days_before;
        $repeatDays = max(1, $settings->overdue_repeat_days);
        $sent = 0;

        Invoice::query()
            ->with('tenant')
            ->whereNotIn('status', ['paid', 'void'])
            ->where('balance_due', '>', 0)
            ->orderBy('due_date')
            ->each(function (Invoice $invoice) use ($today, $daysBefore, $repeatDays, $notifier, &$sent): void {
                $daysUntilDue = (int) $today->diffInDays($invoice->due_date, false);
                $overdue = $daysUntilDue < 0;

                if ((! $overdue && ! in_array($daysUntilDue, $daysBefore, true))
                    || ($overdue && abs($daysUntilDue) % $repeatDays !== 0)) {
                    return;
                }

                $eventType = $overdue ? 'rent_overdue' : 'rent_reminder';
                $event = TenantNotificationEvent::query()->firstOrCreate([
                    'tenant_id' => $invoice->tenant_id,
                    'event_type' => $eventType,
                    'reference' => $invoice->invoice_number,
                    'scheduled_for' => $today,
                ]);

                if (! $event->wasRecentlyCreated) {
                    return;
                }

                $notifier->rentReminder($invoice, $overdue);
                $sent++;
            });

        $this->info("Queued {$sent} rent reminders for {$today->toDateString()}.");

        return self::SUCCESS;
    }
}
