<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_has_private_overview_while_admin_gets_operations_dashboard(): void
    {
        $this->seed(PortfolioSeeder::class);
        $superAdmin = User::factory()->superAdmin()->create();
        $admin = User::factory()->create([
            'role' => 'administrator',
            'permissions' => User::ADMIN_PERMISSIONS,
        ]);

        $this->actingAs($superAdmin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('portfolio.monthly_rent', 36950000)
                ->has('settlementAccounts', 2));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ManagementDashboard')
                ->missing('portfolio')
                ->missing('settlementAccounts')
                ->where('summary.units', 66));

        $this->actingAs($admin)->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/Operations')
                ->missing('summary')
                ->missing('accounts')
                ->missing('trend'));
    }

    public function test_permissions_are_enforced_on_the_server(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrator',
            'permissions' => ['tenants.manage'],
        ]);

        $this->actingAs($admin)->get(route('tenants.index'))->assertOk();
        $this->actingAs($admin)->get(route('finance.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('loans.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('security.index'))->assertForbidden();
    }

    public function test_super_admin_can_invite_and_deactivate_admin_but_cannot_remove_self(): void
    {
        Notification::fake();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)->post(route('security.staff.store'), [
            'name' => 'Kisaasi Caretaker',
            'email' => 'caretaker@example.test',
            'role' => 'administrator',
            'permissions' => ['tenants.manage', 'maintenance.manage'],
        ])->assertRedirect(route('security.index'));

        $staff = User::query()->where('email', 'caretaker@example.test')->firstOrFail();
        $this->assertSame(['tenants.manage', 'maintenance.manage'], $staff->permissions);
        Notification::assertSentTo($staff, ResetPassword::class);

        $this->patch(route('security.staff.status', $staff), ['is_active' => false])
            ->assertRedirect(route('security.index'));
        $this->assertFalse($staff->fresh()->is_active);

        $this->delete(route('security.staff.destroy', $superAdmin))
            ->assertSessionHasErrors('staff');
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'staff.deactivated']);
    }

    public function test_deactivated_account_cannot_authenticate(): void
    {
        $admin = User::factory()->create([
            'email' => 'disabled@example.test',
            'password' => 'password',
            'role' => 'administrator',
            'is_active' => false,
        ]);

        $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_mail_transport_failure_does_not_lose_the_new_staff_account_or_return_500(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Password::shouldReceive('sendResetLink')->once()->andThrow(new \RuntimeException('SMTP temporarily unavailable'));

        $this->actingAs($superAdmin)->post(route('security.staff.store'), [
            'name' => 'Operations Assistant',
            'email' => 'operations@example.test',
            'role' => 'administrator',
            'permissions' => ['maintenance.manage'],
        ])->assertRedirect(route('security.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'operations@example.test',
            'role' => 'administrator',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'staff.created']);
    }
}
