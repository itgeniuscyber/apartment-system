<?php

namespace App\Http\Controllers;

use App\Models\Estate;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MessageThread;
use App\Models\Payment;
use App\Models\Section;
use App\Models\SettlementAccount;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! $request->user()?->isManagement()) {
            return $this->tenantDashboard($request);
        }

        if (! $request->user()->isSuperAdmin()) {
            return $this->managementDashboard($request);
        }

        $estates = Estate::query()
            ->with('sections.units')
            ->orderBy('id')
            ->get();

        $units = $estates->flatMap(
            fn (Estate $estate) => $estate->sections->flatMap(
                fn (Section $section) => $section->units,
            ),
        );

        $accounts = SettlementAccount::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function (SettlementAccount $account) use ($estates): array {
                $accountEstates = $estates->where('settlement_account_id', $account->id);
                $accountUnits = $accountEstates->flatMap(
                    fn (Estate $estate) => $estate->sections->flatMap(
                        fn (Section $section) => $section->units,
                    ),
                );

                return [
                    'id' => $account->id,
                    'owner_name' => $account->owner_name,
                    'bank_name' => $account->bank_name,
                    'account_last_four' => $account->account_last_four,
                    'estate_names' => $accountEstates->pluck('name')->values(),
                    'scheduled_rent' => $accountUnits->where('is_active', true)->sum('rent_amount'),
                ];
            });

        return Inertia::render('Dashboard', [
            'portfolio' => [
                'estate_count' => $estates->count(),
                'unit_count' => $units->count(),
                'monthly_rent' => $units->where('is_active', true)->sum('rent_amount'),
                'setup_required' => $units->where('status', 'setup_required')->count(),
            ],
            'estatePerformance' => $estates->map(function (Estate $estate): array {
                $estateUnits = $estate->sections->flatMap(fn (Section $section) => $section->units);

                return [
                    'id' => $estate->id,
                    'name' => $estate->name,
                    'units' => $estateUnits->count(),
                    'sections' => $estate->sections->count(),
                    'scheduled_rent' => $estateUnits->where('is_active', true)->sum('rent_amount'),
                    'color' => $estate->color,
                ];
            })->values(),
            'settlementAccounts' => $accounts->values(),
        ]);
    }

    private function managementDashboard(Request $request): Response
    {
        $permissions = $request->user()->permissions ?? User::ADMIN_PERMISSIONS;

        return Inertia::render('ManagementDashboard', [
            'staffName' => $request->user()?->name,
            'permissions' => $permissions,
            'summary' => [
                'estates' => Estate::query()->count(),
                'units' => Unit::query()->count(),
                'tenants' => Tenant::query()->count(),
                'active_leases' => Lease::query()->where('status', 'active')->count(),
                'open_maintenance' => MaintenanceRequest::query()->whereNotIn('status', ['resolved', 'closed'])->count(),
                'unread_messages' => MessageThread::query()->where('status', 'open')->count(),
            ],
        ]);
    }

    private function tenantDashboard(Request $request): Response
    {
        $tenant = $request->user()?->tenantProfile()
            ->with('activeLease.unit.section.estate')
            ->first();
        $lease = $tenant?->activeLease;
        $invoices = $tenant === null
            ? collect()
            : Invoice::query()->where('tenant_id', $tenant->id)->orderByDesc('due_date')->get();
        $payments = $tenant === null
            ? collect()
            : Payment::query()->where('tenant_id', $tenant->id)->where('status', 'confirmed')->orderByDesc('paid_at')->limit(4)->get();
        $outstanding = $invoices->filter(fn (Invoice $invoice): bool => $invoice->status !== 'void' && $invoice->balance_due > 0);

        return Inertia::render('tenant/Dashboard', [
            'tenant' => $tenant === null ? null : [
                'id' => $tenant->id,
                'full_name' => $tenant->full_name,
                'status' => $tenant->status,
                'phone' => $tenant->phone,
                'preferred_channel' => $tenant->preferred_channel,
            ],
            'lease' => $lease === null ? null : [
                'id' => $lease->id,
                'lease_number' => $lease->lease_number,
                'rent_amount' => $lease->rent_amount,
                'deposit_amount' => $lease->deposit_amount,
                'due_day' => $lease->due_day,
                'grace_days' => $lease->grace_days,
                'start_date' => $lease->start_date->toDateString(),
                'end_date' => $lease->end_date?->toDateString(),
                'next_due_date' => $this->nextDueDate($lease->due_day)->format('M j, Y'),
                'unit' => [
                    'code' => $lease->unit->code,
                    'number' => $lease->unit->number,
                    'section' => $lease->unit->section->name,
                    'estate' => $lease->unit->section->estate->name,
                ],
            ],
            'finance' => [
                'outstanding' => $outstanding->sum('balance_due'),
                'overdue' => $outstanding->filter(fn (Invoice $invoice): bool => $invoice->effectiveStatus() === 'overdue')->sum('balance_due'),
                'open_invoices' => $outstanding->count(),
                'recent_payments' => $payments->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'amount' => $payment->amount,
                    'paid_at' => $payment->paid_at->format('d M Y'),
                    'method' => ucwords(str_replace('_', ' ', $payment->payment_method)),
                ])->values(),
            ],
        ]);
    }

    private function nextDueDate(int $dueDay): CarbonInterface
    {
        $today = now()->startOfDay();
        $dueDate = $today->copy()->day(min($dueDay, $today->daysInMonth));

        if ($dueDate->isBefore($today)) {
            $dueDate = $today->copy()->addMonthNoOverflow()->startOfMonth();
            $dueDate->day(min($dueDay, $dueDate->daysInMonth));
        }

        return $dueDate;
    }
}
