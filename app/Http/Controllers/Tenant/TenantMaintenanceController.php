<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceRequest;
use App\Models\MaintenanceRequest;
use App\Notifications\ManagementEventNotification;
use App\Notifications\TenantEventNotification;
use App\Services\Communication\ManagementNotifier;
use App\Services\Communication\TenantNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantMaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $request->user()?->tenantProfile;
        $requests = $tenant === null
            ? collect()
            : MaintenanceRequest::query()
                ->with(['unit.section.estate', 'assignee', 'updates.user'])
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->get();

        return Inertia::render('tenant/Requests', [
            'tenant' => $tenant === null ? null : [
                'id' => $tenant->id,
                'full_name' => $tenant->full_name,
                'has_active_lease' => $tenant->activeLease()->exists(),
            ],
            'summary' => [
                'open' => $requests->whereIn('status', ['open', 'acknowledged'])->count(),
                'in_progress' => $requests->where('status', 'in_progress')->count(),
                'resolved' => $requests->whereIn('status', ['resolved', 'closed'])->count(),
            ],
            'requests' => $requests->map(fn (MaintenanceRequest $item): array => $this->requestData($item))->values(),
        ]);
    }

    public function store(
        StoreMaintenanceRequest $request,
        TenantNotifier $tenantNotifier,
        ManagementNotifier $managementNotifier,
    ): RedirectResponse {
        $tenant = $request->user()?->tenantProfile;
        abort_if($tenant === null, 403);
        $lease = $tenant->activeLease()->first();
        $evidence = $request->file('evidence');
        $evidenceData = [];

        if ($evidence !== null) {
            $path = $evidence->store('maintenance-evidence/'.now()->format('Y/m'), 'local');
            abort_if($path === false, 500, 'Evidence could not be stored.');
            $evidenceData = [
                'evidence_path' => $path,
                'evidence_original_name' => $evidence->getClientOriginalName(),
                'evidence_mime' => $evidence->getMimeType(),
                'evidence_size' => $evidence->getSize(),
            ];
        }

        try {
            $maintenance = DB::transaction(function () use ($request, $tenant, $lease, $evidenceData): MaintenanceRequest {
                $next = ((int) MaintenanceRequest::query()->max('id')) + 1;

                return MaintenanceRequest::query()->create([
                    ...$request->safe()->except('evidence'),
                    ...$evidenceData,
                    'reference' => sprintf('MNT-%s-%05d', now()->format('Y'), $next),
                    'tenant_id' => $tenant->id,
                    'lease_id' => $lease?->id,
                    'unit_id' => $lease?->unit_id,
                    'status' => 'open',
                ]);
            });
        } catch (\Throwable $exception) {
            if (isset($evidenceData['evidence_path'])) {
                Storage::disk('local')->delete($evidenceData['evidence_path']);
            }

            throw $exception;
        }

        $channels = array_values(array_unique(['database', 'email', $tenant->preferred_channel]));
        $tenantNotifier->send($tenant, new TenantEventNotification(
            eventType: 'maintenance_created',
            title: 'Maintenance request received',
            message: "Your request {$maintenance->reference} has been recorded. Management will update its status from the maintenance desk.",
            reference: $maintenance->reference,
            actionUrl: route('tenant.requests'),
            actionLabel: 'Track request',
            severity: $maintenance->priority === 'urgent' ? 'warning' : 'info',
        ), $channels);
        $managementNotifier->send(new ManagementEventNotification(
            eventType: 'maintenance_created',
            title: 'New maintenance request',
            message: "{$tenant->full_name} submitted {$maintenance->reference}: {$maintenance->title}",
            reference: $maintenance->reference,
            actionUrl: route('maintenance.index'),
            severity: $maintenance->priority === 'urgent' ? 'warning' : 'info',
        ));

        return to_route('tenant.requests')->with('success', 'Maintenance request submitted and management notified.');
    }

    public function reply(Request $request, MaintenanceRequest $maintenanceRequest, ManagementNotifier $notifier): RedirectResponse
    {
        $tenant = $request->user()?->tenantProfile;
        abort_unless($tenant !== null && $maintenanceRequest->tenant_id === $tenant->id, 403);
        $validated = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        DB::transaction(function () use ($maintenanceRequest, $request, $validated): void {
            $maintenanceRequest->updates()->create([
                'user_id' => $request->user()->id,
                'body' => trim((string) $validated['body']),
                'is_internal' => false,
            ]);
        });

        $notifier->send(new ManagementEventNotification(
            eventType: 'maintenance_reply',
            title: 'Tenant replied to '.$maintenanceRequest->reference,
            message: trim((string) $validated['body']),
            reference: $maintenanceRequest->reference,
            actionUrl: route('maintenance.index'),
        ));

        return to_route('tenant.requests')->with('success', 'Your update was sent to management.');
    }

    public function evidence(Request $request, MaintenanceRequest $maintenanceRequest): StreamedResponse
    {
        $tenant = $request->user()?->tenantProfile;
        abort_unless($request->user()->isManagement() || ($tenant !== null && $maintenanceRequest->tenant_id === $tenant->id), 403);
        abort_if($maintenanceRequest->evidence_path === null || ! Storage::disk('local')->exists($maintenanceRequest->evidence_path), 404);

        return Storage::disk('local')->download(
            $maintenanceRequest->evidence_path,
            $maintenanceRequest->evidence_original_name ?? 'maintenance-evidence',
        );
    }

    /** @return array<string, mixed> */
    private function requestData(MaintenanceRequest $item): array
    {
        return [
            'id' => $item->id,
            'reference' => $item->reference,
            'category' => $item->category,
            'title' => $item->title,
            'description' => $item->description,
            'priority' => $item->priority,
            'status' => $item->status,
            'access_permission' => $item->access_permission,
            'preferred_visit_at' => $item->preferred_visit_at?->format('d M Y, g:i A'),
            'evidence_url' => $item->evidence_path === null ? null : route('maintenance.evidence', $item),
            'assignee' => $item->assigned_to === null ? null : $item->assignee->name,
            'apartment' => $item->unit === null ? null : [
                'code' => $item->unit->code,
                'number' => $item->unit->number,
                'estate' => $item->unit->section->estate->name,
            ],
            'created_at' => $item->created_at?->format('d M Y, g:i A'),
            'updates' => $item->updates
                ->where('is_internal', false)
                ->map(fn ($update): array => [
                    'id' => $update->id,
                    'body' => $update->body,
                    'status' => $update->status,
                    'sender' => $update->user_id === null ? 'System' : $update->user->name,
                    'sender_role' => $update->user_id !== null && $update->user->isManagement() ? 'management' : 'tenant',
                    'created_at' => $update->created_at?->format('d M Y, g:i A'),
                ])->values(),
        ];
    }
}
