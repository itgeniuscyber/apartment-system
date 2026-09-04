<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\LeaseRentRevision;
use App\Notifications\TenantEventNotification;
use App\Services\Audit\ActivityRecorder;
use App\Services\Communication\TenantNotifier;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeaseRentRevisionController extends Controller
{
    public function store(Request $request, Lease $lease, ActivityRecorder $audit, TenantNotifier $notifier): RedirectResponse
    {
        abort_unless($lease->status === 'active', 422, 'Rent changes can only be scheduled for an active lease.');
        $data = $request->validate($this->rules());
        $effectiveFrom = $this->validatedEffectiveDate($lease, (string) $data['effective_from']);

        $this->ensureDateIsAvailable($lease, $effectiveFrom);
        $isSuperAdmin = $request->user()->isSuperAdmin();
        $revision = $lease->rentRevisions()->create([
            'previous_amount' => $lease->rentForDate($effectiveFrom->subDay()),
            'amount' => $data['amount'],
            'effective_from' => $effectiveFrom,
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? null,
            'status' => $isSuperAdmin ? 'approved' : 'pending',
            'notify_tenant' => $data['notify_tenant'],
            'created_by' => $request->user()?->id,
            'approved_by' => $isSuperAdmin ? $request->user()?->id : null,
            'approved_at' => $isSuperAdmin ? now() : null,
        ]);

        $audit->record($request, 'lease.rent_change_created', "Created a rent change for {$lease->lease_number} effective {$effectiveFrom->format('d M Y')}.", $revision, [
            'previous_amount' => $revision->previous_amount,
            'amount' => $revision->amount,
            'status' => $revision->status,
        ]);

        if ($revision->status === 'approved') {
            $this->applyCurrentRent($lease);
            $this->notifyTenant($revision, $notifier);
        }

        return to_route('leases.index')->with('success', $isSuperAdmin
            ? 'Rent change approved and scheduled. Future invoices will use it from the effective month.'
            : 'Rent change submitted for Super Admin approval.');
    }

    public function approve(Request $request, Lease $lease, LeaseRentRevision $rentRevision, ActivityRecorder $audit, TenantNotifier $notifier): RedirectResponse
    {
        $this->ensureRevisionBelongsToLease($lease, $rentRevision);

        if ($rentRevision->status !== 'pending') {
            throw ValidationException::withMessages(['rent_revision' => 'Only a pending rent change can be approved.']);
        }

        if ($lease->invoices()->whereDate('billing_period_start', $rentRevision->effective_from)->exists()) {
            throw ValidationException::withMessages([
                'rent_revision' => 'An invoice now exists for this month. Cancel this request and choose a later effective month.',
            ]);
        }

        $this->ensureDateIsAvailable($lease, $rentRevision->effective_from, $rentRevision->id);
        $rentRevision->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        $this->applyCurrentRent($lease);
        $audit->record($request, 'lease.rent_change_approved', "Approved the rent change for {$lease->lease_number}.", $rentRevision);
        $this->notifyTenant($rentRevision, $notifier);

        return to_route('leases.index')->with('success', 'Rent change approved and tenant communication processed.');
    }

    public function reject(Request $request, Lease $lease, LeaseRentRevision $rentRevision, ActivityRecorder $audit): RedirectResponse
    {
        $this->ensureRevisionBelongsToLease($lease, $rentRevision);

        if ($rentRevision->status !== 'pending') {
            throw ValidationException::withMessages(['rent_revision' => 'Only a pending rent change can be rejected.']);
        }

        $rentRevision->update(['status' => 'rejected']);
        $audit->record($request, 'lease.rent_change_rejected', "Rejected the rent change for {$lease->lease_number}.", $rentRevision);

        return to_route('leases.index')->with('success', 'Pending rent change rejected.');
    }

    public function destroy(Request $request, Lease $lease, LeaseRentRevision $rentRevision, ActivityRecorder $audit): RedirectResponse
    {
        $this->ensureRevisionBelongsToLease($lease, $rentRevision);

        if ($rentRevision->effective_from->isBefore(now()->startOfMonth())) {
            throw ValidationException::withMessages(['rent_revision' => 'Historical rent changes cannot be removed.']);
        }

        if (! $request->user()?->isSuperAdmin() && ($rentRevision->status !== 'pending' || $rentRevision->created_by !== $request->user()?->id)) {
            abort(403);
        }

        $rentRevision->update(['status' => 'cancelled']);
        $this->applyCurrentRent($lease);
        $audit->record($request, 'lease.rent_change_cancelled', "Cancelled the rent change for {$lease->lease_number}.", $rentRevision);

        return to_route('leases.index')->with('success', 'Scheduled rent change cancelled; its history was preserved.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'effective_from' => ['required', 'date', 'after_or_equal:'.now()->startOfMonth()->toDateString()],
            'reason' => ['required', Rule::in(['annual_review', 'discount', 'negotiation', 'renovation', 'market_adjustment', 'correction', 'other'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'notify_tenant' => ['required', 'boolean'],
        ];
    }

    private function validatedEffectiveDate(Lease $lease, string $date): CarbonImmutable
    {
        $effectiveFrom = CarbonImmutable::parse($date)->startOfDay();

        if ($effectiveFrom->day !== 1) {
            throw ValidationException::withMessages(['effective_from' => 'Rent changes must start on the first day of a month to avoid partial-month billing ambiguity.']);
        }

        if ($effectiveFrom->isBefore($lease->start_date->startOfMonth())) {
            throw ValidationException::withMessages(['effective_from' => 'The effective date cannot be before the lease began.']);
        }

        if ($lease->end_date !== null && $effectiveFrom->isAfter($lease->end_date)) {
            throw ValidationException::withMessages(['effective_from' => 'The effective date must fall within the lease period.']);
        }

        if ($lease->invoices()->whereDate('billing_period_start', $effectiveFrom)->exists()) {
            throw ValidationException::withMessages(['effective_from' => 'An invoice already exists for this month. Choose a later month so issued invoices remain unchanged.']);
        }

        return $effectiveFrom;
    }

    private function ensureDateIsAvailable(Lease $lease, CarbonInterface $effectiveFrom, ?int $exceptId = null): void
    {
        $query = $lease->rentRevisions()
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('effective_from', $effectiveFrom);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['effective_from' => 'Another pending or approved rent change already starts in this month.']);
        }
    }

    private function ensureRevisionBelongsToLease(Lease $lease, LeaseRentRevision $revision): void
    {
        abort_unless($revision->lease_id === $lease->id, 404);
    }

    private function applyCurrentRent(Lease $lease): void
    {
        $lease->unsetRelation('rentRevisions');
        $lease->update(['rent_amount' => $lease->rentForDate(now()->startOfMonth())]);
    }

    private function notifyTenant(LeaseRentRevision $revision, TenantNotifier $notifier): void
    {
        if (! $revision->notify_tenant || $revision->notified_at !== null) {
            return;
        }

        $revision->loadMissing('lease.tenant');
        $tenant = $revision->lease->tenant;
        $notifier->send($tenant, new TenantEventNotification(
            eventType: 'rent_change_approved',
            title: 'Rent schedule update',
            message: 'Your monthly rent will change from UGX '.number_format($revision->previous_amount).' to UGX '.number_format($revision->amount).' from '.$revision->effective_from->format('d M Y').'.',
            reference: $revision->lease->lease_number,
            actionUrl: route('tenant.payments'),
            actionLabel: 'View rent account',
            severity: 'info',
        ), array_values(array_unique(['database', 'email', $tenant->preferred_channel])));
        $revision->update(['notified_at' => now()]);
    }
}
