<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\StoreTenantRequest;
use App\Http\Requests\Tenancy\UpdateTenantRequest;
use App\Models\Lease;
use App\Models\Tenant;
use App\Services\Tenancy\TenantIdentityStorage;
use App\Services\Tenancy\TenantPortalInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantController extends Controller
{
    public function __construct(
        private TenantIdentityStorage $identityStorage,
        private TenantPortalInvitationService $portalInvitations,
    ) {}

    public function index(): Response
    {
        $tenants = Tenant::query()
            ->with(['user', 'activeLease.unit.section.estate'])
            ->withCount(['leases', 'invoices', 'payments', 'paymentLinks', 'paymentAttempts'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('tenants/Index', [
            'summary' => [
                'total' => $tenants->count(),
                'active' => $tenants->where('status', 'active')->count(),
                'pending' => $tenants->where('status', 'pending')->count(),
                'portal_ready' => $tenants->whereNotNull('portal_activated_at')->count(),
                'portal_invited' => $tenants->whereNull('portal_activated_at')->whereNotNull('portal_invited_at')->count(),
                'identity_captured' => $tenants->filter(fn (Tenant $tenant) => $this->hasIdentity($tenant))->count(),
                'identity_verified' => $tenants->whereNotNull('identity_verified_at')->count(),
            ],
            'tenants' => $tenants->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'first_name' => $tenant->first_name,
                'last_name' => $tenant->last_name,
                'full_name' => $tenant->full_name,
                'initials' => mb_strtoupper(mb_substr($tenant->first_name, 0, 1).mb_substr($tenant->last_name, 0, 1)),
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'alternative_phone' => $tenant->alternative_phone,
                'status' => $tenant->status,
                'preferred_channel' => $tenant->preferred_channel,
                'notes' => $tenant->notes,
                'portal_ready' => $tenant->portal_activated_at !== null,
                'portal_status' => $this->portalStatus($tenant),
                'portal_invited_at' => $tenant->portal_invited_at?->format('d M Y, g:i A'),
                'can_invite' => $tenant->email !== null,
                'can_delete' => $tenant->leases_count === 0
                    && $tenant->invoices_count === 0
                    && $tenant->payments_count === 0
                    && $tenant->payment_links_count === 0
                    && $tenant->payment_attempts_count === 0,
                'lease_count' => $tenant->leases_count,
                'identity' => [
                    'captured' => $this->hasIdentity($tenant),
                    'type' => $tenant->identity_document_type,
                    'type_label' => $this->identityTypeLabel($tenant->identity_document_type),
                    'last_four' => $tenant->identity_document_last_four,
                    'document_uploaded' => $tenant->identity_document_path !== null,
                    'verified' => $tenant->identity_verified_at !== null,
                ],
                'next_of_kin' => $tenant->next_of_kin_name === null ? null : [
                    'name' => $tenant->next_of_kin_name,
                    'relationship' => $tenant->next_of_kin_relationship,
                    'phone' => $tenant->next_of_kin_phone,
                    'email' => $tenant->next_of_kin_email,
                    'address' => $tenant->next_of_kin_address,
                ],
                'active_lease' => $this->leaseData($tenant->activeLease),
            ])->values(),
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $data = Arr::except($validated, ['identity_document']);
        $document = $request->file('identity_document');
        $documentData = [];

        $identifier = preg_replace('/[^\pL\pN]/u', '', (string) ($data['identity_document_number'] ?? '')) ?: null;
        $data['identity_document_last_four'] = $identifier === null ? null : Str::substr($identifier, -4);

        try {
            if ($document !== null) {
                $documentData = $this->identityStorage->store($document);
                $data = [...$data, ...$documentData];
            }

            $tenant = DB::transaction(fn (): Tenant => Tenant::query()->create([
                ...$data,
                'status' => 'pending',
            ]));
        } catch (\Throwable $exception) {
            $this->identityStorage->delete($documentData['identity_document_path'] ?? null);

            throw $exception;
        }

        if ($tenant->email !== null) {
            $this->portalInvitations->send($tenant);

            return to_route('tenants.index')->with('success', 'Tenant profile created and a secure portal activation email was sent.');
        }

        return to_route('tenants.index')->with('success', 'Tenant profile created. Add an email address before inviting them to the portal.');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $data = Arr::except($validated, ['identity_document']);
        $newDocument = $request->file('identity_document');
        $newDocumentData = [];
        $oldDocumentPath = $tenant->identity_document_path;
        $identityNumberSupplied = ($data['identity_document_number'] ?? null) !== null;

        if ($identityNumberSupplied) {
            $identifier = preg_replace('/[^\pL\pN]/u', '', (string) $data['identity_document_number']) ?: null;
            $data['identity_document_last_four'] = $identifier === null ? null : Str::substr($identifier, -4);
        } else {
            unset($data['identity_document_number']);
        }

        try {
            if ($newDocument !== null) {
                $newDocumentData = $this->identityStorage->store($newDocument);
                $data = [...$data, ...$newDocumentData];
            }

            DB::transaction(function () use ($tenant, $data, $identityNumberSupplied, $newDocument): void {
                $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
                $identityChanged = $identityNumberSupplied
                    || $newDocument !== null
                    || $lockedTenant->identity_document_type !== $data['identity_document_type'];
                $emailChanged = $lockedTenant->email !== $data['email'];

                if ($identityChanged) {
                    $data['identity_verified_at'] = null;
                    $data['identity_verified_by'] = null;
                }

                $lockedTenant->update($data);

                $portalUser = $lockedTenant->user()->lockForUpdate()->first();

                if ($portalUser !== null) {
                    $portalUser->update([
                        'name' => $lockedTenant->full_name,
                        'email' => $lockedTenant->email,
                    ]);

                    if ($emailChanged) {
                        Password::broker()->deleteToken($portalUser);

                        if ($lockedTenant->portal_activated_at === null) {
                            $lockedTenant->update(['portal_invited_at' => null]);
                        }
                    }
                }
            });
        } catch (\Throwable $exception) {
            $this->identityStorage->delete($newDocumentData['identity_document_path'] ?? null);

            throw $exception;
        }

        if ($newDocument !== null && $oldDocumentPath !== null) {
            $this->identityStorage->delete($oldDocumentPath);
        }

        return to_route('tenants.index')->with('success', 'Tenant contact, security, and next-of-kin details updated.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        DB::transaction(function () use ($tenant): void {
            $lockedTenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $hasHistory = $lockedTenant->leases()->exists()
                || $lockedTenant->invoices()->exists()
                || $lockedTenant->payments()->exists()
                || $lockedTenant->paymentLinks()->exists()
                || $lockedTenant->paymentAttempts()->exists()
                || $lockedTenant->maintenanceRequests()->exists()
                || $lockedTenant->noticeRecipients()->exists()
                || $lockedTenant->messageThreads()->exists()
                || $lockedTenant->communicationDeliveries()->exists()
                || $lockedTenant->notificationEvents()->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'tenant' => 'This tenant has lease, financial, maintenance, or communication history and cannot be deleted.',
                ]);
            }

            $portalUser = $lockedTenant->user()->lockForUpdate()->first();
            $lockedTenant->delete();
            $portalUser?->delete();
        });

        return to_route('tenants.index')->with('success', 'Empty tenant profile and its unused portal login deleted.');
    }

    public function invite(Tenant $tenant): RedirectResponse
    {
        $this->portalInvitations->send($tenant);

        return to_route('tenants.index')->with('success', 'A fresh secure portal activation email was sent.');
    }

    public function downloadIdentityDocument(Tenant $tenant): StreamedResponse
    {
        if ($tenant->identity_document_path === null || ! Storage::disk('local')->exists($tenant->identity_document_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $tenant->identity_document_path,
            $tenant->identity_document_original_name ?? 'tenant-identity-document',
        );
    }

    public function verifyIdentity(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! $this->hasIdentity($tenant)) {
            throw ValidationException::withMessages([
                'identity' => 'Record an identity number or upload a document before verification.',
            ]);
        }

        $tenant->update([
            'identity_verified_at' => now(),
            'identity_verified_by' => $request->user()->id,
        ]);

        return to_route('tenants.index')->with('success', 'Tenant identity marked as verified.');
    }

    private function hasIdentity(Tenant $tenant): bool
    {
        return $tenant->identity_document_number !== null || $tenant->identity_document_path !== null;
    }

    private function identityTypeLabel(?string $type): ?string
    {
        return match ($type) {
            'national_id' => 'National ID',
            'passport' => 'Passport',
            'driving_permit' => 'Driving permit',
            'refugee_id' => 'Refugee ID',
            'other' => 'Other document',
            default => null,
        };
    }

    private function portalStatus(Tenant $tenant): string
    {
        if ($tenant->portal_activated_at !== null) {
            return 'active';
        }

        if ($tenant->portal_invited_at !== null) {
            return 'invited';
        }

        return $tenant->email === null ? 'email_required' : 'not_invited';
    }

    /** @return array<string, mixed>|null */
    private function leaseData(?Lease $lease): ?array
    {
        if ($lease === null) {
            return null;
        }

        return [
            'id' => $lease->id,
            'lease_number' => $lease->lease_number,
            'unit_code' => $lease->unit->code,
            'unit_number' => $lease->unit->number,
            'section' => $lease->unit->section->name,
            'estate' => $lease->unit->section->estate->name,
            'rent_amount' => $lease->rent_amount,
            'due_day' => $lease->due_day,
        ];
    }
}
