<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\StoreLeaseRequest;
use App\Http\Requests\Tenancy\UpdateLeaseRequest;
use App\Models\Lease;
use App\Models\LeaseRentRevision;
use App\Models\Tenant;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaseController extends Controller
{
    public function index(): Response
    {
        $leases = Lease::query()
            ->with(['tenant', 'unit.section.estate', 'rentRevisions.creator', 'rentRevisions.approver'])
            ->withCount('invoices')
            ->orderByDesc('start_date')
            ->get();

        $units = Unit::query()
            ->with(['section.estate', 'activeLease'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return Inertia::render('leases/Index', [
            'summary' => [
                'total' => $leases->count(),
                'active' => $leases->where('status', 'active')->count(),
                'draft' => $leases->where('status', 'draft')->count(),
                'monthly_rent' => $leases->where('status', 'active')->sum(fn (Lease $lease): int => $lease->rentForDate(now()->startOfMonth())),
                'deposits' => $leases->where('status', 'active')->sum('deposit_amount'),
            ],
            'leases' => $leases->map(fn (Lease $lease) => $this->leaseData($lease))->values(),
            'tenants' => Tenant::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Tenant $tenant) => [
                    'id' => $tenant->id,
                    'full_name' => $tenant->full_name,
                    'status' => $tenant->status,
                ]),
            'units' => $units->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'code' => $unit->code,
                'number' => $unit->number,
                'rent_amount' => $unit->rent_amount,
                'status' => $unit->status,
                'available' => $unit->activeLease === null,
                'section' => $unit->section->name,
                'estate' => $unit->section->estate->name,
            ])->values(),
        ]);
    }

    public function store(StoreLeaseRequest $request): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $status = (string) $data['status'];

        if ($status === 'active' && CarbonImmutable::parse((string) $data['start_date'])->isFuture()) {
            throw ValidationException::withMessages([
                'status' => 'Future leases should remain Draft until their start date.',
            ]);
        }

        DB::transaction(function () use ($data, $status, $request): void {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail((int) $data['tenant_id']);
            $unit = Unit::query()->lockForUpdate()->findOrFail((int) $data['unit_id']);

            if ($status === 'active') {
                $this->ensureCanActivate($tenant, $unit);
            }

            Lease::query()->create([
                ...$data,
                'initial_rent_amount' => $data['rent_amount'],
                'lease_number' => $this->nextLeaseNumber(),
                'billing_frequency' => 'monthly',
                'signed_at' => $status === 'active' ? now() : null,
                'created_by' => $request->user()?->id,
            ]);

            if ($status === 'active') {
                $unit->update(['status' => 'occupied']);
                $tenant->update(['status' => 'active']);
            }
        });

        return to_route('leases.index')->with('success', 'Lease created and due-date tracking configured.');
    }

    public function update(UpdateLeaseRequest $request, Lease $lease): RedirectResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        DB::transaction(function () use ($lease, $data): void {
            $lockedLease = Lease::query()->lockForUpdate()->findOrFail($lease->id);

            if ($lockedLease->status === 'ended') {
                throw ValidationException::withMessages([
                    'lease' => 'Ended leases are read-only so their history remains reliable.',
                ]);
            }

            if ($lockedLease->status === 'active') {
                if ((int) $data['rent_amount'] !== $lockedLease->rentForDate(now()->startOfMonth())) {
                    throw ValidationException::withMessages([
                        'rent_amount' => 'Use Change rent to schedule an approved tenant-specific rent revision with history.',
                    ]);
                }

                if ((int) $data['tenant_id'] !== $lockedLease->tenant_id) {
                    throw ValidationException::withMessages([
                        'tenant_id' => 'End this lease before assigning it to another tenant.',
                    ]);
                }

                if ((int) $data['unit_id'] !== $lockedLease->unit_id) {
                    throw ValidationException::withMessages([
                        'unit_id' => 'End this lease before moving it to another unit.',
                    ]);
                }

                if (CarbonImmutable::parse((string) $data['start_date'])->isFuture()) {
                    throw ValidationException::withMessages([
                        'start_date' => 'An active lease cannot have a future start date.',
                    ]);
                }

                if (($data['end_date'] ?? null) !== null
                    && CarbonImmutable::parse((string) $data['end_date'])->isPast()
                    && ! CarbonImmutable::parse((string) $data['end_date'])->isToday()) {
                    throw ValidationException::withMessages([
                        'end_date' => 'Use End lease to record a completed tenancy.',
                    ]);
                }
            }

            $lockedLease->update([
                ...$data,
                ...($lockedLease->status === 'draft' ? ['initial_rent_amount' => $data['rent_amount']] : []),
            ]);
        });

        return to_route('leases.index')->with('success', 'Lease terms updated. Existing invoices were not changed.');
    }

    public function activate(Lease $lease): RedirectResponse
    {
        DB::transaction(function () use ($lease): void {
            $lockedLease = Lease::query()->lockForUpdate()->findOrFail($lease->id);

            if ($lockedLease->status !== 'draft') {
                throw ValidationException::withMessages([
                    'lease' => 'Only a draft lease can be activated.',
                ]);
            }

            if ($lockedLease->start_date->isFuture()) {
                throw ValidationException::withMessages([
                    'lease' => 'This lease starts in the future. Activate it on or after the start date.',
                ]);
            }

            if ($lockedLease->end_date !== null && $lockedLease->end_date->isPast()) {
                throw ValidationException::withMessages([
                    'lease' => 'This draft has already passed its end date. Edit the dates before activation.',
                ]);
            }

            $tenant = Tenant::query()->lockForUpdate()->findOrFail($lockedLease->tenant_id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($lockedLease->unit_id);
            $this->ensureCanActivate($tenant, $unit, $lockedLease->id);

            $lockedLease->update([
                'status' => 'active',
                'signed_at' => now(),
            ]);
            $unit->update(['status' => 'occupied']);
            $tenant->update(['status' => 'active']);
        });

        return to_route('leases.index')->with('success', 'Draft activated. The tenant and apartment are now marked active.');
    }

    public function end(Request $request, Lease $lease): RedirectResponse
    {
        $validated = $request->validate([
            'end_date' => [
                'required',
                'date',
                'after_or_equal:'.$lease->start_date->toDateString(),
                'before_or_equal:today',
            ],
        ]);

        DB::transaction(function () use ($lease, $validated): void {
            $lockedLease = Lease::query()->lockForUpdate()->findOrFail($lease->id);

            if ($lockedLease->status !== 'active') {
                throw ValidationException::withMessages([
                    'lease' => 'Only an active lease can be ended.',
                ]);
            }

            $tenant = Tenant::query()->lockForUpdate()->findOrFail($lockedLease->tenant_id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($lockedLease->unit_id);

            $lockedLease->update([
                'status' => 'ended',
                'end_date' => $validated['end_date'],
            ]);

            if (! $unit->leases()->where('status', 'active')->exists() && $unit->status === 'occupied') {
                $unit->update(['status' => 'available']);
            }

            if (! $tenant->leases()->where('status', 'active')->exists()) {
                $tenant->update(['status' => 'former']);
            }
        });

        return to_route('leases.index')->with('success', 'Lease ended. Its invoices and payment history remain available.');
    }

    public function destroy(Lease $lease): RedirectResponse
    {
        DB::transaction(function () use ($lease): void {
            $lockedLease = Lease::query()->lockForUpdate()->findOrFail($lease->id);

            if ($lockedLease->status !== 'draft') {
                throw ValidationException::withMessages([
                    'lease' => 'Only draft leases can be deleted. End active leases to preserve their history.',
                ]);
            }

            if ($lockedLease->invoices()->exists()) {
                throw ValidationException::withMessages([
                    'lease' => 'This lease has financial history and cannot be deleted.',
                ]);
            }

            $lockedLease->delete();
        });

        return to_route('leases.index')->with('success', 'Draft lease deleted.');
    }

    private function nextLeaseNumber(): string
    {
        $nextId = ((int) Lease::withTrashed()->max('id')) + 1;

        return sprintf('KR-%s-%04d', now()->format('Y'), $nextId);
    }

    /** @return array<string, mixed> */
    private function leaseData(Lease $lease): array
    {
        $currentRent = $lease->rentForDate(now()->startOfMonth());
        $upcomingRevision = $lease->rentRevisions
            ->where('status', 'approved')
            ->filter(fn (LeaseRentRevision $revision): bool => $revision->effective_from->isAfter(now()->startOfMonth()))
            ->sortBy('effective_from')
            ->first();

        return [
            'id' => $lease->id,
            'lease_number' => $lease->lease_number,
            'tenant' => [
                'id' => $lease->tenant->id,
                'full_name' => $lease->tenant->full_name,
            ],
            'unit' => [
                'id' => $lease->unit->id,
                'code' => $lease->unit->code,
                'number' => $lease->unit->number,
                'section' => $lease->unit->section->name,
                'estate' => $lease->unit->section->estate->name,
            ],
            'start_date' => $lease->start_date->toDateString(),
            'end_date' => $lease->end_date?->toDateString(),
            'rent_amount' => $currentRent,
            'standard_unit_rent' => $lease->unit->rent_amount,
            'deposit_amount' => $lease->deposit_amount,
            'due_day' => $lease->due_day,
            'grace_days' => $lease->grace_days,
            'status' => $lease->status,
            'notes' => $lease->notes,
            'can_edit' => $lease->status !== 'ended',
            'can_edit_assignment' => $lease->status === 'draft',
            'can_activate' => $lease->status === 'draft',
            'can_end' => $lease->status === 'active',
            'can_delete' => $lease->status === 'draft' && $lease->invoices_count === 0,
            'has_financial_history' => $lease->invoices_count > 0,
            'next_due_date' => $lease->status === 'active'
                ? $this->nextDueDate($lease->due_day)->format('M j, Y')
                : null,
            'upcoming_rent' => $upcomingRevision === null ? null : [
                'amount' => $upcomingRevision->amount,
                'effective_from' => $upcomingRevision->effective_from->toDateString(),
                'effective_from_label' => $upcomingRevision->effective_from->format('d M Y'),
            ],
            'rent_revisions' => $lease->rentRevisions->map(fn (LeaseRentRevision $revision): array => [
                'id' => $revision->id,
                'previous_amount' => $revision->previous_amount,
                'amount' => $revision->amount,
                'effective_from' => $revision->effective_from->toDateString(),
                'effective_from_label' => $revision->effective_from->format('d M Y'),
                'reason' => $revision->reason,
                'notes' => $revision->notes,
                'status' => $revision->status,
                'notify_tenant' => $revision->notify_tenant,
                'notified' => $revision->notified_at !== null,
                'created_by' => $revision->created_by === null ? 'System' : $revision->creator->name,
                'approved_by' => $revision->approved_by === null ? null : $revision->approver->name,
                'created_at' => $revision->created_at?->format('d M Y, g:i A'),
                'can_cancel' => ! $revision->effective_from->isBefore(now()->startOfMonth())
                    && (auth()->user()?->isSuperAdmin() === true
                        || ($revision->status === 'pending' && $revision->created_by === auth()->id())),
            ])->values(),
        ];
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

    private function ensureCanActivate(Tenant $tenant, Unit $unit, ?int $exceptLeaseId = null): void
    {
        if (! $unit->is_active) {
            throw ValidationException::withMessages([
                'unit_id' => 'This apartment is inactive and cannot receive a lease.',
            ]);
        }

        $unitLease = $unit->leases()->where('status', 'active');
        $tenantLease = $tenant->leases()->where('status', 'active');

        if ($exceptLeaseId !== null) {
            $unitLease->whereKeyNot($exceptLeaseId);
            $tenantLease->whereKeyNot($exceptLeaseId);
        }

        if ($unitLease->exists()) {
            throw ValidationException::withMessages([
                'unit_id' => 'This unit already has an active lease.',
            ]);
        }

        if ($tenantLease->exists()) {
            throw ValidationException::withMessages([
                'tenant_id' => 'This tenant already has an active lease.',
            ]);
        }
    }
}
