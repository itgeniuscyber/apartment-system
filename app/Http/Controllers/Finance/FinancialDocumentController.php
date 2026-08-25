<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Communication\FinancialNotifier;
use App\Services\Documents\FinancialPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FinancialDocumentController extends Controller
{
    public function invoice(Request $request, Invoice $invoice, FinancialPdf $pdf): Response
    {
        $this->authorizeTenantRecord($request, $invoice->tenant_id);
        $invoice->loadMissing(['tenant', 'lease', 'unit.section.estate', 'settlementAccount', 'allocations.payment']);

        return $pdf->download('pdf.invoice', [
            'invoice' => $invoice,
            'generatedAt' => now(),
        ], $invoice->invoice_number.'.pdf');
    }

    public function receipt(Request $request, Payment $payment, FinancialPdf $pdf): Response
    {
        $this->authorizeTenantRecord($request, $payment->tenant_id);
        $payment->loadMissing([
            'tenant',
            'settlementAccount',
            'allocations.invoice.unit.section.estate',
        ]);

        return $pdf->download('pdf.receipt', [
            'payment' => $payment,
            'generatedAt' => now(),
        ], $payment->receipt_number.'.pdf');
    }

    public function emailInvoice(Invoice $invoice, FinancialNotifier $notifier): RedirectResponse
    {
        $notifier->invoiceIssued($invoice, ['database', 'email']);

        return to_route('finance.index')->with('success', 'Invoice email queued for '.$invoice->tenant->full_name.'.');
    }

    public function emailReceipt(Payment $payment, FinancialNotifier $notifier): RedirectResponse
    {
        $notifier->paymentReceipt($payment, ['database', 'email']);

        return to_route('finance.index')->with('success', 'Receipt email queued for '.$payment->tenant->full_name.'.');
    }

    private function authorizeTenantRecord(Request $request, int $tenantId): void
    {
        $user = $request->user();

        abort_unless($user !== null && ($user->isManagement() || $user->tenantProfile?->id === $tenantId), 403);
    }
}
