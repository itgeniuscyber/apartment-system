<?php

use App\Http\Controllers\Communication\CommunicationSettingsController;
use App\Http\Controllers\Communication\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\CashflowController;
use App\Http\Controllers\Finance\FinanceController;
use App\Http\Controllers\Finance\FinancialDocumentController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\LoanController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\PaymentLinkController;
use App\Http\Controllers\Maintenance\MaintenanceController;
use App\Http\Controllers\Messaging\MessageController;
use App\Http\Controllers\PaymentLinkCheckoutController;
use App\Http\Controllers\Portfolio\EstateController;
use App\Http\Controllers\Portfolio\SectionController;
use App\Http\Controllers\Portfolio\UnitRateController;
use App\Http\Controllers\Security\StaffController;
use App\Http\Controllers\Tenancy\LeaseController;
use App\Http\Controllers\Tenancy\LeaseRentRevisionController;
use App\Http\Controllers\Tenancy\TenantController;
use App\Http\Controllers\Tenant\TenantMaintenanceController;
use App\Http\Controllers\Tenant\TenantMessageController;
use App\Http\Controllers\Tenant\TenantPaymentController;
use App\Http\Controllers\Tenant\TenantProfileController;
use App\Http\Controllers\Webhooks\GatewayWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::get('pay/{token}', [PaymentLinkCheckoutController::class, 'show'])->name('payment-links.show');
Route::post('webhooks/payments/{provider}', GatewayWebhookController::class)->name('webhooks.payments');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::get('maintenance/{maintenanceRequest}/evidence', [TenantMaintenanceController::class, 'evidence'])
        ->name('maintenance.evidence');

    Route::prefix('tenant')->name('tenant.')->group(function () {
        Route::get('profile', [TenantProfileController::class, 'show'])->name('profile');
        Route::get('profile/identity-document', [TenantProfileController::class, 'downloadIdentity'])
            ->name('profile.identity-document');
        Route::get('payments', [TenantPaymentController::class, 'index'])->name('payments');
        Route::get('payments/invoices/{invoice}/pdf', [FinancialDocumentController::class, 'invoice'])
            ->name('payments.invoices.pdf');
        Route::get('payments/receipts/{payment}/pdf', [FinancialDocumentController::class, 'receipt'])
            ->name('payments.receipts.pdf');
        Route::get('requests', [TenantMaintenanceController::class, 'index'])->name('requests');
        Route::post('requests', [TenantMaintenanceController::class, 'store'])->name('requests.store');
        Route::post('requests/{maintenanceRequest}/reply', [TenantMaintenanceController::class, 'reply'])
            ->name('requests.reply');
        Route::get('messages', [TenantMessageController::class, 'index'])->name('messages');
        Route::post('messages', [TenantMessageController::class, 'store'])->name('messages.store');
        Route::post('messages/{messageThread}/reply', [TenantMessageController::class, 'reply'])
            ->name('messages.reply');
        Route::inertia('documents', 'tenant/ModulePlaceholder', [
            'title' => 'Lease documents',
            'description' => 'Signed agreements, house rules, receipts, and shared documents will be kept here.',
        ])->name('documents');
    });

    Route::middleware('management')->group(function () {
        Route::middleware('permission:portfolio.manage')->group(function () {
            Route::get('properties', [EstateController::class, 'index'])->name('properties.index');
            Route::post('properties', [EstateController::class, 'store'])->name('properties.store');
            Route::post('properties/sections', [SectionController::class, 'store'])->name('properties.sections.store');
            Route::post('properties/units/{unit}/rent-rates', [UnitRateController::class, 'store'])
                ->middleware('super_admin')
                ->name('properties.units.rent-rates.store');
        });

        Route::middleware('permission:tenants.manage')->group(function () {
            Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
            Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
            Route::patch('tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
            Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
            Route::post('tenants/{tenant}/portal-invitation', [TenantController::class, 'invite'])->name('tenants.portal-invitation');
            Route::get('tenants/{tenant}/identity-document', [TenantController::class, 'downloadIdentityDocument'])->name('tenants.identity-document.download');
            Route::patch('tenants/{tenant}/identity/verify', [TenantController::class, 'verifyIdentity'])->name('tenants.identity.verify');
        });

        Route::middleware('permission:leases.manage')->group(function () {
            Route::get('leases', [LeaseController::class, 'index'])->name('leases.index');
            Route::post('leases', [LeaseController::class, 'store'])->name('leases.store');
            Route::patch('leases/{lease}', [LeaseController::class, 'update'])->name('leases.update');
            Route::patch('leases/{lease}/activate', [LeaseController::class, 'activate'])->name('leases.activate');
            Route::patch('leases/{lease}/end', [LeaseController::class, 'end'])->name('leases.end');
            Route::delete('leases/{lease}', [LeaseController::class, 'destroy'])->name('leases.destroy');
            Route::post('leases/{lease}/rent-revisions', [LeaseRentRevisionController::class, 'store'])->name('leases.rent-revisions.store');
            Route::delete('leases/{lease}/rent-revisions/{rentRevision}', [LeaseRentRevisionController::class, 'destroy'])->name('leases.rent-revisions.destroy');
            Route::patch('leases/{lease}/rent-revisions/{rentRevision}/approve', [LeaseRentRevisionController::class, 'approve'])
                ->middleware('super_admin')
                ->name('leases.rent-revisions.approve');
            Route::patch('leases/{lease}/rent-revisions/{rentRevision}/reject', [LeaseRentRevisionController::class, 'reject'])
                ->middleware('super_admin')
                ->name('leases.rent-revisions.reject');
        });

        Route::middleware('permission:finance.operations')->group(function () {
            Route::get('finance', FinanceController::class)->name('finance.index');
            Route::post('finance/invoices/generate', [InvoiceController::class, 'generate'])->name('finance.invoices.generate');
            Route::post('finance/payments', [PaymentController::class, 'store'])->name('finance.payments.store');
            Route::get('finance/invoices/{invoice}/pdf', [FinancialDocumentController::class, 'invoice'])->name('finance.invoices.pdf');
            Route::post('finance/invoices/{invoice}/email', [FinancialDocumentController::class, 'emailInvoice'])->name('finance.invoices.email');
            Route::get('finance/payments/{payment}/receipt', [FinancialDocumentController::class, 'receipt'])->name('finance.payments.receipt');
            Route::post('finance/payments/{payment}/email-receipt', [FinancialDocumentController::class, 'emailReceipt'])->name('finance.payments.email-receipt');
            Route::post('finance/invoices/{invoice}/payment-links', [PaymentLinkController::class, 'store'])->name('finance.payment-links.store');
            Route::patch('finance/invoices/{invoice}/payment-links/{paymentLink}/revoke', [PaymentLinkController::class, 'revoke'])->name('finance.payment-links.revoke');
        });

        Route::middleware('permission:cashflow.manage')->group(function () {
            Route::get('cashflow', [CashflowController::class, 'index'])->name('cashflow.index');
            Route::post('cashflow', [CashflowController::class, 'store'])->name('cashflow.store');
            Route::patch('cashflow/{cashflowEntry}', [CashflowController::class, 'update'])->name('cashflow.update');
            Route::delete('cashflow/{cashflowEntry}', [CashflowController::class, 'destroy'])->name('cashflow.destroy');
            Route::get('cashflow/{cashflowEntry}/receipt', [CashflowController::class, 'receipt'])->name('cashflow.receipt');
        });

        Route::middleware('permission:maintenance.manage')->group(function () {
            Route::get('maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
            Route::patch('maintenance/{maintenanceRequest}', [MaintenanceController::class, 'update'])->name('maintenance.update');
        });

        Route::middleware('permission:messages.manage')->group(function () {
            Route::get('messages', [MessageController::class, 'index'])->name('messages.index');
            Route::post('messages/notices', [MessageController::class, 'publish'])->name('messages.notices.publish');
            Route::patch('messages/threads/{messageThread}/read', [MessageController::class, 'read'])->name('messages.threads.read');
            Route::post('messages/threads/{messageThread}/reply', [MessageController::class, 'reply'])->name('messages.threads.reply');
        });

        Route::middleware('permission:reports.view')->group(function () {
            Route::inertia('reports', 'ModulePlaceholder', [
                'title' => 'Reports & analytics',
                'description' => 'Explore occupancy, collection performance, arrears aging, expenses, and portfolio trends according to your access level.',
            ])->name('reports.index');
        });

        Route::middleware('super_admin')->group(function () {
            Route::get('loans', [LoanController::class, 'index'])->name('loans.index');
            Route::post('loans', [LoanController::class, 'store'])->name('loans.store');
            Route::patch('loans/{loan}', [LoanController::class, 'update'])->name('loans.update');
            Route::delete('loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');
            Route::post('loans/{loan}/payments', [LoanController::class, 'recordPayment'])->name('loans.payments.store');
            Route::delete('loans/{loan}/payments/{loanPayment}', [LoanController::class, 'destroyPayment'])->name('loans.payments.destroy');

            Route::get('security', [StaffController::class, 'index'])->name('security.index');
            Route::post('security/staff', [StaffController::class, 'store'])->name('security.staff.store');
            Route::patch('security/staff/{staff}', [StaffController::class, 'update'])->name('security.staff.update');
            Route::patch('security/staff/{staff}/status', [StaffController::class, 'status'])->name('security.staff.status');
            Route::post('security/staff/{staff}/invitation', [StaffController::class, 'resendInvitation'])->name('security.staff.invitation');
            Route::delete('security/staff/{staff}', [StaffController::class, 'destroy'])->name('security.staff.destroy');

            Route::get('communication-settings', [CommunicationSettingsController::class, 'index'])->name('communication.settings.index');
            Route::patch('communication-settings', [CommunicationSettingsController::class, 'update'])->name('communication.settings.update');
            Route::inertia('smart-access', 'ModulePlaceholder', [
                'title' => 'Smart access',
                'description' => 'Future-ready controls for door health, battery status, access credentials, tamper events, and reviewed remote commands.',
            ])->name('smart-access.index');
        });
    });
});

require __DIR__.'/settings.php';
