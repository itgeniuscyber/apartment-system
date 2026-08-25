<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $tenant = $this->tenant($request);
        $tenant->loadMissing('activeLease.unit.section.estate');
        $lease = $tenant->activeLease;

        return Inertia::render('tenant/Profile', [
            'tenant' => [
                'full_name' => $tenant->full_name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'alternative_phone' => $tenant->alternative_phone,
                'preferred_channel' => $tenant->preferred_channel,
                'status' => $tenant->status,
                'portal_activated_at' => $tenant->portal_activated_at?->format('d M Y'),
                'identity' => [
                    'captured' => $tenant->identity_document_number !== null || $tenant->identity_document_path !== null,
                    'type' => $this->identityTypeLabel($tenant->identity_document_type),
                    'last_four' => $tenant->identity_document_last_four,
                    'document_uploaded' => $tenant->identity_document_path !== null,
                    'uploaded_at' => $tenant->identity_document_uploaded_at?->format('d M Y'),
                    'verified' => $tenant->identity_verified_at !== null,
                    'verified_at' => $tenant->identity_verified_at?->format('d M Y'),
                ],
                'next_of_kin' => [
                    'name' => $tenant->next_of_kin_name,
                    'relationship' => $tenant->next_of_kin_relationship,
                    'phone' => $tenant->next_of_kin_phone,
                    'email' => $tenant->next_of_kin_email,
                    'address' => $tenant->next_of_kin_address,
                ],
                'lease' => $lease === null ? null : [
                    'lease_number' => $lease->lease_number,
                    'estate' => $lease->unit->section->estate->name,
                    'section' => $lease->unit->section->name,
                    'unit_code' => $lease->unit->code,
                ],
            ],
        ]);
    }

    public function downloadIdentity(Request $request): StreamedResponse
    {
        $tenant = $this->tenant($request);

        if ($tenant->identity_document_path === null || ! Storage::disk('local')->exists($tenant->identity_document_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $tenant->identity_document_path,
            $tenant->identity_document_original_name ?? 'my-identity-document',
        );
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenantProfile;

        abort_unless($tenant !== null, 403);

        return $tenant;
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
}
