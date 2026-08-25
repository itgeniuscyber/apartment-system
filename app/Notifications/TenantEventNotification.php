<?php

namespace App\Notifications;

use App\Models\CommunicationSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Documents\FinancialPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantEventNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, scalar|null>  $templateData
     */
    public function __construct(
        public string $eventType,
        public string $title,
        public string $message,
        public ?string $reference = null,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public ?string $documentType = null,
        public ?int $documentId = null,
        public string $severity = 'info',
        public array $templateData = [],
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
            'action_label' => $this->actionLabel,
            'severity' => $this->severity,
        ];
    }

    public function toMail(Tenant $notifiable): MailMessage
    {
        $settings = CommunicationSetting::current();
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.$notifiable->first_name.',')
            ->line($this->message);

        if (filled($settings->email_from_address)) {
            $mail->from(
                (string) $settings->email_from_address,
                filled($settings->email_from_name) ? (string) $settings->email_from_name : null,
            );
        }

        if ($this->actionUrl !== null && $this->actionLabel !== null) {
            $mail->action($this->actionLabel, $this->actionUrl);
        }

        $this->attachFinancialDocument($mail);

        return $mail
            ->line('This message is also available in your secure K&R Estates resident dashboard.')
            ->salutation('K&R Estates Management');
    }

    /** @return array<string, scalar|null> */
    public function payload(): array
    {
        return [
            'event_type' => $this->eventType,
            'title' => $this->title,
            'message' => $this->message,
            'reference' => $this->reference,
            'action_url' => $this->actionUrl,
            'action_label' => $this->actionLabel,
            'document_type' => $this->documentType,
            'document_id' => $this->documentId,
            'severity' => $this->severity,
            'template_data' => json_encode($this->templateData),
        ];
    }

    /** @param array<string, scalar|null> $payload */
    public static function fromPayload(array $payload): self
    {
        $templateData = json_decode((string) ($payload['template_data'] ?? '{}'), true);

        return new self(
            eventType: (string) $payload['event_type'],
            title: (string) $payload['title'],
            message: (string) $payload['message'],
            reference: is_string($payload['reference'] ?? null) ? $payload['reference'] : null,
            actionUrl: is_string($payload['action_url'] ?? null) ? $payload['action_url'] : null,
            actionLabel: is_string($payload['action_label'] ?? null) ? $payload['action_label'] : null,
            documentType: is_string($payload['document_type'] ?? null) ? $payload['document_type'] : null,
            documentId: is_numeric($payload['document_id'] ?? null) ? (int) $payload['document_id'] : null,
            severity: (string) ($payload['severity'] ?? 'info'),
            templateData: is_array($templateData) ? $templateData : [],
        );
    }

    private function attachFinancialDocument(MailMessage $mail): void
    {
        $pdf = app(FinancialPdf::class);

        if ($this->documentType === 'invoice' && $this->documentId !== null) {
            $invoice = Invoice::query()
                ->with(['tenant', 'lease', 'unit.section.estate', 'settlementAccount', 'allocations.payment'])
                ->find($this->documentId);

            if ($invoice !== null) {
                $mail->attachData($pdf->render('pdf.invoice', [
                    'invoice' => $invoice,
                    'generatedAt' => now(),
                ]), $invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
            }
        }

        if ($this->documentType === 'receipt' && $this->documentId !== null) {
            $payment = Payment::query()
                ->with(['tenant', 'settlementAccount', 'allocations.invoice.unit.section.estate'])
                ->find($this->documentId);

            if ($payment !== null) {
                $mail->attachData($pdf->render('pdf.receipt', [
                    'payment' => $payment,
                    'generatedAt' => now(),
                ]), $payment->receipt_number.'.pdf', ['mime' => 'application/pdf']);
            }
        }
    }
}
