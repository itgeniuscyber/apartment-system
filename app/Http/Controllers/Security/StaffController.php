<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Audit\ActivityRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('security/Index', [
            'staff' => User::query()
                ->whereIn('role', ['super_admin', 'administrator', 'landlord', 'manager'])
                ->orderByRaw("case when role = 'super_admin' then 0 else 1 end")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                    'permissions' => $user->permissions ?? User::ADMIN_PERMISSIONS,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'deactivated_at' => $user->deactivated_at?->format('d M Y, g:i A'),
                    'created_at' => $user->created_at?->format('d M Y'),
                    'is_current_user' => $user->is($request->user()),
                ])->values(),
            'permissionOptions' => $this->permissionOptions(),
            'activity' => ActivityLog::query()
                ->with('user:id,name')
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (ActivityLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'actor' => $log->user_id === null ? 'System' : $log->user->name,
                    'created_at' => $log->created_at?->diffForHumans(),
                ]),
        ]);
    }

    public function store(Request $request, ActivityRecorder $audit): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $staff = User::query()->create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'role' => $data['role'],
            'permissions' => $data['role'] === 'administrator' ? $data['permissions'] : null,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::password(40)),
        ]);

        $audit->record($request, 'staff.created', "Created {$staff->name}'s {$this->roleLabel($staff->role)} account.", $staff);
        $status = $this->sendSetupLink($staff);

        $message = $status === Password::RESET_LINK_SENT
            ? 'Staff account created and a secure setup link was emailed.'
            : 'Staff account created successfully, but Gmail could not deliver the setup email. Use the mail button beside the account to try again.';

        return to_route('security.index')->with($status === Password::RESET_LINK_SENT ? 'success' : 'warning', $message);
    }

    public function update(Request $request, User $staff, ActivityRecorder $audit): RedirectResponse
    {
        $this->ensureStaffAccount($staff);
        $data = $request->validate($this->rules($staff));

        if ($staff->is($request->user()) && $data['role'] !== 'super_admin') {
            return back()->withErrors(['role' => 'A Super Admin cannot remove their own Super Admin access.']);
        }

        $staff->update([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'role' => $data['role'],
            'permissions' => $data['role'] === 'administrator' ? $data['permissions'] : null,
        ]);

        $audit->record($request, 'staff.updated', "Updated access for {$staff->name}.", $staff, [
            'role' => $staff->role,
            'permissions' => $staff->permissions,
        ]);

        return to_route('security.index')->with('success', 'Staff account and permissions updated.');
    }

    public function status(Request $request, User $staff, ActivityRecorder $audit): RedirectResponse
    {
        $this->ensureStaffAccount($staff);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        if ($staff->is($request->user()) && ! $data['is_active']) {
            return back()->withErrors(['staff' => 'You cannot deactivate your own Super Admin account.']);
        }

        $staff->update([
            'is_active' => $data['is_active'],
            'deactivated_at' => $data['is_active'] ? null : now(),
            'deactivated_by' => $data['is_active'] ? null : $request->user()?->id,
        ]);

        $verb = $data['is_active'] ? 'reactivated' : 'deactivated';
        $audit->record($request, "staff.{$verb}", ucfirst($verb)." {$staff->name}'s account.", $staff);

        return to_route('security.index')->with('success', "Staff account {$verb}.");
    }

    public function resendInvitation(Request $request, User $staff, ActivityRecorder $audit): RedirectResponse
    {
        $this->ensureStaffAccount($staff);
        abort_unless($staff->is_active, 422, 'Reactivate this account before sending access details.');

        $status = $this->sendSetupLink($staff);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->withErrors(['email' => 'Gmail could not deliver the setup email. The staff account is safe; please try again shortly.']);
        }

        $audit->record($request, 'staff.invitation_resent', "Resent a secure account setup link to {$staff->name}.", $staff);

        return back()->with('success', 'A fresh secure setup link was emailed.');
    }

    public function destroy(Request $request, User $staff, ActivityRecorder $audit): RedirectResponse
    {
        $this->ensureStaffAccount($staff);

        if ($staff->is($request->user())) {
            return back()->withErrors(['staff' => 'Super Admins cannot delete their own accounts.']);
        }

        $name = $staff->name;
        $audit->record($request, 'staff.deleted', "Deleted {$name}'s staff account.", $staff, ['email' => $staff->email]);
        $staff->delete();

        return to_route('security.index')->with('success', "{$name}'s staff account was removed.");
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(?User $staff = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($staff)],
            'role' => ['required', Rule::in(['super_admin', 'administrator'])],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(User::ADMIN_PERMISSIONS)],
        ];
    }

    private function ensureStaffAccount(User $staff): void
    {
        abort_unless($staff->isManagement(), 404);
    }

    /** @return list<array{key: string, label: string, description: string}> */
    private function permissionOptions(): array
    {
        return [
            ['key' => 'portfolio.manage', 'label' => 'Estates & units', 'description' => 'Add and maintain properties, sections, units, and rent rates.'],
            ['key' => 'tenants.manage', 'label' => 'Tenant records', 'description' => 'Create, edit, verify, invite, and manage tenant profiles.'],
            ['key' => 'leases.manage', 'label' => 'Leases', 'description' => 'Create, activate, edit, end, and remove lease records.'],
            ['key' => 'finance.operations', 'label' => 'Billing operations', 'description' => 'Issue invoices, record payments, and send receipts without portfolio totals.'],
            ['key' => 'maintenance.manage', 'label' => 'Maintenance', 'description' => 'Assign and update tenant service requests.'],
            ['key' => 'messages.manage', 'label' => 'Messages & notices', 'description' => 'Reply to tenants and publish management notices.'],
            ['key' => 'cashflow.manage', 'label' => 'Expense entry', 'description' => 'Record and manage only their own expense/revenue entries.'],
            ['key' => 'reports.view', 'label' => 'Operational reports', 'description' => 'View non-sensitive portfolio and service reports.'],
        ];
    }

    private function roleLabel(string $role): string
    {
        return $role === 'super_admin' ? 'Super Admin' : 'Admin';
    }

    private function sendSetupLink(User $staff): string
    {
        try {
            return Password::sendResetLink(['email' => $staff->email]);
        } catch (\Throwable $exception) {
            report($exception);

            return Password::RESET_THROTTLED;
        }
    }
}
