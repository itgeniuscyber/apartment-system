<?php

namespace Tests\Feature\Tenancy;

use App\Models\CommunicationSetting;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\LeaseRentRevision;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-25 10:00:00'));
        $this->seed(PortfolioSeeder::class);
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($this->superAdmin);
    }

    public function test_super_admin_can_schedule_tenant_rent_without_changing_an_existing_invoice(): void
    {
        $lease = $this->activeLease(675000);

        $this->post(route('finance.invoices.generate'), ['billing_month' => '2026-08'])
            ->assertRedirect(route('finance.index'));

        $this->post(route('leases.rent-revisions.store', $lease), [
            'amount' => 725000,
            'effective_from' => '2026-09-01',
            'reason' => 'negotiation',
            'notes' => 'Agreed tenant-specific adjustment.',
            'notify_tenant' => false,
        ])->assertRedirect(route('leases.index'));

        $revision = LeaseRentRevision::query()->firstOrFail();
        $this->assertSame('approved', $revision->status);
        $this->assertSame($this->superAdmin->id, $revision->approved_by);
        $this->assertSame(675000, $lease->fresh()->rentForDate(CarbonImmutable::parse('2026-08-01')));
        $this->assertSame(725000, $lease->fresh()->rentForDate(CarbonImmutable::parse('2026-09-01')));

        $this->post(route('finance.invoices.generate'), ['billing_month' => '2026-09'])
            ->assertRedirect(route('finance.index'));

        $this->assertDatabaseHas('invoices', [
            'lease_id' => $lease->id,
            'billing_period_start' => '2026-08-01 00:00:00',
            'total_amount' => 675000,
            'balance_due' => 675000,
        ]);
        $this->assertDatabaseHas('invoices', [
            'lease_id' => $lease->id,
            'billing_period_start' => '2026-09-01 00:00:00',
            'total_amount' => 725000,
            'balance_due' => 725000,
        ]);
        $this->assertSame(675000, Invoice::query()->whereDate('billing_period_start', '2026-08-01')->value('total_amount'));
    }

    public function test_admin_rent_change_waits_for_super_admin_approval_and_notifies_after_approval(): void
    {
        CommunicationSetting::current()->update([
            'in_app_enabled' => true,
            'email_enabled' => false,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
        ]);
        $lease = $this->activeLease(700000);
        $admin = User::factory()->create([
            'role' => 'administrator',
            'permissions' => ['leases.manage'],
        ]);

        $this->actingAs($admin)->post(route('leases.rent-revisions.store', $lease), [
            'amount' => 650000,
            'effective_from' => '2026-09-01',
            'reason' => 'discount',
            'notes' => 'Temporary loyalty discount.',
            'notify_tenant' => true,
        ])->assertRedirect(route('leases.index'));

        $revision = LeaseRentRevision::query()->firstOrFail();
        $this->assertSame('pending', $revision->status);
        $this->assertSame(700000, $lease->fresh()->rentForDate(CarbonImmutable::parse('2026-09-01')));
        $this->assertSame(0, $lease->tenant->notifications()->count());

        $this->actingAs($this->superAdmin)
            ->patch(route('leases.rent-revisions.approve', [$lease, $revision]))
            ->assertRedirect(route('leases.index'));

        $this->assertSame('approved', $revision->fresh()->status);
        $this->assertSame(650000, $lease->fresh()->rentForDate(CarbonImmutable::parse('2026-09-01')));
        $this->assertSame(1, $lease->tenant->notifications()->count());
        $this->assertSame('rent_change_approved', $lease->tenant->notifications()->firstOrFail()->data['event_type']);
    }

    public function test_standard_unit_rate_changes_future_defaults_without_overwriting_active_tenant_rent(): void
    {
        $lease = $this->activeLease(675000);
        $unit = $lease->unit;

        $this->post(route('properties.units.rent-rates.store', $unit), [
            'amount' => 800000,
            'effective_from' => '2026-09-01',
            'notes' => 'New standard rate for future agreements.',
        ])->assertRedirect(route('properties.index'));

        $this->assertSame(700000, $unit->fresh()->rent_amount);
        $this->assertSame(675000, $lease->fresh()->rent_amount);

        $this->artisan('rent:apply-scheduled-rates', ['--date' => '2026-09-01'])
            ->expectsOutputToContain('Applied 1 standard unit rates and 0 tenant lease rates.')
            ->assertSuccessful();

        $this->assertSame(800000, $unit->fresh()->rent_amount);
        $this->assertSame(675000, $lease->fresh()->rent_amount);
    }

    public function test_active_lease_rent_cannot_be_directly_overwritten(): void
    {
        $lease = $this->activeLease(700000);

        $this->from(route('leases.index'))->patch(route('leases.update', $lease), [
            'tenant_id' => $lease->tenant_id,
            'unit_id' => $lease->unit_id,
            'start_date' => $lease->start_date->toDateString(),
            'end_date' => '',
            'rent_amount' => 900000,
            'deposit_amount' => $lease->deposit_amount,
            'due_day' => $lease->due_day,
            'grace_days' => $lease->grace_days,
            'notes' => '',
        ])->assertRedirect(route('leases.index'))->assertSessionHasErrors('rent_amount');

        $this->assertSame(700000, $lease->fresh()->rent_amount);
        $this->assertSame(0, LeaseRentRevision::query()->count());
    }

    private function activeLease(int $rentAmount): Lease
    {
        $unit = Unit::query()->where('code', 'KIS-UP-01')->firstOrFail();
        $tenant = Tenant::query()->create([
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.test',
            'phone' => '+256700000001',
            'status' => 'active',
            'preferred_channel' => 'email',
        ]);
        $unit->update(['status' => 'occupied']);

        return Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-RENT-0001',
            'start_date' => '2026-08-01',
            'rent_amount' => $rentAmount,
            'initial_rent_amount' => $rentAmount,
            'deposit_amount' => $rentAmount,
            'due_day' => 5,
            'grace_days' => 3,
            'billing_frequency' => 'monthly',
            'status' => 'active',
            'signed_at' => now(),
            'created_by' => $this->superAdmin->id,
        ]);
    }
}
