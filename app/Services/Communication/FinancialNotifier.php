<?php

namespace App\Services\Communication;

use App\Models\CommunicationSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\TenantEventNotification;

class FinancialNotifier
{
    public function __construct(private TenantNotifier $notifier) {}

    /** @param list<string>|null $channels */
    public function invoiceIssued(Invoice $invoice, ?array $channels = null): void
    {
        $invoice->loadMissing('tenant');
        $settings = CommunicationSetting::current();

        $this->notifier->send($invoice->tenant, new TenantEventNotification(
            eventType: 'invoice_issued',
            title: 'New rent invoice '.$invoice->invoice_number,
            message: sprintf(
                '%s has been issued for UGX %s and is due on %s.',
                $invoice->description,
                number_format($invoice->total_amount),
                $invoice->due_date->format('d M Y'),
            ),
            reference: $invoice->invoice_number,
            actionUrl: route('tenant.payments'),
            actionLabel: 'View invoice and payment options',
            documentType: 'invoice',
            documentId: $invoice->id,
            severity: 'info',
        ), $channels ?? $this->stringList($settings->invoice_channels));
    }

    /** @param list<string>|null $channels */
    public function paymentReceipt(Payment $payment, ?array $channels = null): void
    {
        $payment->loadMissing('tenant');
        $settings = CommunicationSetting::current();

        $this->notifier->send($payment->tenant, new TenantEventNotification(
            eventType: 'payment_receipt',
            title: 'Payment received · '.$payment->receipt_number,
            message: sprintf(
                'We received UGX %s on %s. Your official receipt is attached.',
                number_format($payment->amount),
                $payment->paid_at->format('d M Y'),
            ),
            reference: $payment->receipt_number,
            actionUrl: route('tenant.payments'),
            actionLabel: 'View payment and receipt',
            documentType: 'receipt',
            documentId: $payment->id,
            severity: 'success',
        ), $channels ?? $this->stringList($settings->receipt_channels));
    }

    /** @param list<string>|null $channels */
    public function rentReminder(Invoice $invoice, bool $overdue, ?array $channels = null): void
    {
        $invoice->loadMissing('tenant');
        $settings = CommunicationSetting::current();
        $title = $overdue
            ? 'Rent overdue · '.$invoice->invoice_number
            : 'Rent reminder · '.$invoice->invoice_number;
        $message = $overdue
            ? sprintf(
                'UGX %s remains overdue from %s. Please pay or contact management if you need assistance.',
                number_format($invoice->balance_due),
                $invoice->due_date->format('d M Y'),
            )
            : sprintf(
                'UGX %s is due on %s. Your dashboard has the invoice and available payment options.',
                number_format($invoice->balance_due),
                $invoice->due_date->format('d M Y'),
            );

        $this->notifier->send($invoice->tenant, new TenantEventNotification(
            eventType: $overdue ? 'rent_overdue' : 'rent_reminder',
            title: $title,
            message: $message,
            reference: $invoice->invoice_number,
            actionUrl: route('tenant.payments'),
            actionLabel: 'Review rent balance',
            documentType: 'invoice',
            documentId: $invoice->id,
            severity: $overdue ? 'warning' : 'info',
        ), $channels ?? $this->stringList($settings->reminder_channels));
    }

    /** @return list<string> */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return ['database', 'email'];
        }

        return array_values(array_filter($values, 'is_string'));
    }
}
