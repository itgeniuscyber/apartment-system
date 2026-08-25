<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\ManageMaintenanceRequest;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Notifications\TenantEventNotification;
use App\Services\Communication\TenantNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function index(): Response
    {
        $requests = MaintenanceRequest::query()
            ->with(['tenant', 'unit.section.estate', 'assignee', 'updates.user'])
            ->latest()
            ->get();

        return Inertia::render('maintenance/Index', [
            'summary' => [
                'open' => $requests->whereIn('status', ['open', 'acknowledged'])->count(),
                'in_progress' => $requests->where('status', 'in_progress')->count(),
                'urgent' => $requests->whereIn('priority', ['urgent', 'emergency'])->whereNotIn('status', ['resolved', 'closed'])->count(),
                'resolved' => $requests->whereIn('status', ['resolved', 'closed'])->count(),
            ],
            'requests' => $requests->map(fn (MaintenanceRequest $item): array => $this->requestData($item))->values(),
            'assignees' => User::query()
                ->whereIn('role', ['administrator', 'landlord', 'manager'])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(
        ManageMaintenanceRequest $request,
        MaintenanceRequest $maintenanceRequest,
        TenantNotifier $notifier,
    ): RedirectResponse {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $previousStatus = $maintenanceRequest->status;

        DB::transaction(function () use ($maintenanceRequest, $data, $request, $previousStatus): void {
            $timestamps = [];

            if ($data['status'] === 'acknowledged' && $maintenanceRequest->acknowledged_at === null) {
                $timestamps['acknowledged_at'] = now();
            }

            if (in_array($data['status'], ['resolved', 'closed'], true)) {
                $timestamps['resolved_at'] = $maintenanceRequest->resolved_at ?? now();
            } elseif ($maintenanceRequest->resolved_at !== null) {
                $timestamps['resolved_at'] = null;
            }

            $maintenanceRequest->update([
                'status' => $data['status'],
                'priority' => $data['priority'],
                'assigned_to' => $data['assigned_to'],
                ...$timestamps,
            ]);

            if ($data['body'] !== null || $data['status'] !== $previousStatus) {
                $maintenanceRequest->updates()->create([
                    'user_id' => $request->user()?->id,
                    'body' => $data['body'] ?? 'Status changed to '.str($data['status'])->replace('_', ' ')->title().'.',
                    'status' => $data['status'],
                    'is_internal' => $data['is_internal'],
                ]);
            }
        });

        if (! $data['is_internal'] && ($data['body'] !== null || $previousStatus !== $data['status'])) {
            $tenant = $maintenanceRequest->tenant;
            $channels = array_values(array_unique(['database', 'email', $tenant->preferred_channel]));
            $notifier->send($tenant, new TenantEventNotification(
                eventType: 'maintenance_updated',
                title: 'Maintenance update · '.$maintenanceRequest->reference,
                message: (string) ($data['body'] ?? 'Status changed to '.str($data['status'])->replace('_', ' ')->title().'.'),
                reference: $maintenanceRequest->reference,
                actionUrl: route('tenant.requests'),
                actionLabel: 'View request update',
                severity: in_array($data['status'], ['resolved', 'closed'], true) ? 'success' : 'info',
            ), $channels);
        }

        return to_route('maintenance.index')->with('success', 'Maintenance request updated and visible communications delivered.');
    }

    /** @return array<string, mixed> */
    private function requestData(MaintenanceRequest $item): array
    {
        return [
            'id' => $item->id,
            'reference' => $item->reference,
            'tenant' => ['id' => $item->tenant->id, 'full_name' => $item->tenant->full_name, 'phone' => $item->tenant->phone],
            'category' => $item->category,
            'title' => $item->title,
            'description' => $item->description,
            'priority' => $item->priority,
            'status' => $item->status,
            'access_permission' => $item->access_permission,
            'preferred_visit_at' => $item->preferred_visit_at?->format('d M Y, g:i A'),
            'evidence_url' => $item->evidence_path === null ? null : route('maintenance.evidence', $item),
            'assigned_to' => $item->assigned_to,
            'assignee' => $item->assigned_to === null ? null : $item->assignee->name,
            'apartment' => $item->unit === null ? null : [
                'code' => $item->unit->code,
                'number' => $item->unit->number,
                'section' => $item->unit->section->name,
                'estate' => $item->unit->section->estate->name,
            ],
            'created_at' => $item->created_at?->format('d M Y, g:i A'),
            'updates' => $item->updates->map(fn ($update): array => [
                'id' => $update->id,
                'body' => $update->body,
                'status' => $update->status,
                'internal' => $update->is_internal,
                'sender' => $update->user_id === null ? 'System' : $update->user->name,
                'created_at' => $update->created_at?->format('d M Y, g:i A'),
            ])->values(),
        ];
    }
}
