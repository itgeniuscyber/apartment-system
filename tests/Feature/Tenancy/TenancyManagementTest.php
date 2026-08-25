<?php

namespace Tests\Feature\Tenancy;

use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\TenantPortalInvitation;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenancyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PortfolioSeeder::class);
        $this->manager = User::factory()->management()->create();
        $this->actingAs($this->manager);
        Notification::fake();
    }

    public function test_management_can_view_truthful_empty_tenant_and_lease_registers(): void
    {
        $this->get(route('tenants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenants/Index')
                ->where('summary.total', 0)
                ->has('tenants', 0));

        $this->get(route('leases.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('leases/Index')
                ->where('summary.active', 0)
                ->has('leases', 0)
                ->has('units', 66));
    }

    public function test_management_can_create_a_tenant_without_assuming_occupancy(): void
    {
        Storage::fake('local');

        $this->post(route('tenants.store'), [
            'first_name' => '  Amina ',
            'last_name' => ' Nansubuga ',
            'email' => 'AMINA@example.com',
            'phone' => '+256 700 000001',
            'alternative_phone' => '',
            'identity_document_type' => 'national_id',
            'identity_document_number' => 'CM 9000 1234 5678',
            'identity_document' => UploadedFile::fake()->create('national-id.pdf', 200, 'application/pdf'),
            'next_of_kin_name' => 'Sarah N.',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_phone' => '+256 700 000002',
            'next_of_kin_email' => 'SARAH@example.com',
            'next_of_kin_address' => 'Kampala, Uganda',
            'preferred_channel' => 'whatsapp',
            'notes' => 'Prefers notices in the afternoon.',
        ])->assertRedirect(route('tenants.index'));

        $this->assertDatabaseHas('tenants', [
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.com',
            'status' => 'pending',
            'identity_document_type' => 'national_id',
            'identity_document_last_four' => '5678',
            'next_of_kin_name' => 'Sarah N.',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_email' => 'sarah@example.com',
        ]);

        $tenant = Tenant::query()->firstOrFail();
        $resident = $tenant->user;
        $this->assertNotNull($resident);
        $this->assertSame('tenant', $resident->role);
        $this->assertSame('amina@example.com', $resident->email);
        $this->assertFalse(Hash::check('password', $resident->password));
        $this->assertNotNull($tenant->portal_invited_at);
        $this->assertNull($tenant->portal_activated_at);
        Notification::assertSentTo($resident, TenantPortalInvitation::class);
        $this->assertSame('CM 9000 1234 5678', $tenant->identity_document_number);
        $this->assertNotSame(
            'CM 9000 1234 5678',
            DB::table('tenants')->where('id', $tenant->id)->value('identity_document_number'),
        );
        $this->assertNotNull($tenant->identity_document_path);
        Storage::disk('local')->assertExists($tenant->identity_document_path);
        $this->assertNull($tenant->identity_verified_at);
        $this->assertSame(0, Lease::query()->count());
        $this->assertSame(66, Unit::query()->where('status', 'setup_required')->count());
    }

    public function test_an_invited_tenant_chooses_their_own_password_and_can_log_in(): void
    {
        $this->post(route('tenants.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.com',
            'phone' => '+256 700 000001',
            'identity_document_type' => 'national_id',
            'identity_document_number' => 'CM900012345678',
            'next_of_kin_name' => 'Sarah N.',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_phone' => '+256 700 000002',
            'preferred_channel' => 'whatsapp',
        ])->assertRedirect(route('tenants.index'));

        $tenant = Tenant::query()->firstOrFail();
        $resident = $tenant->user;
        $this->assertNotNull($resident);

        Notification::assertSentTo(
            $resident,
            TenantPortalInvitation::class,
            function (TenantPortalInvitation $notification) use ($resident): bool {
                $this->app['auth']->logout();

                $this->post(route('password.update'), [
                    'token' => $notification->token,
                    'email' => $resident->email,
                    'password' => 'MyPrivatePassword123!',
                    'password_confirmation' => 'MyPrivatePassword123!',
                ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

                return true;
            },
        );

        $this->assertNotNull($tenant->fresh()->portal_activated_at);
        $this->assertNotNull($resident->fresh()->email_verified_at);
        $this->assertTrue(Hash::check('MyPrivatePassword123!', $resident->fresh()->password));

        $this->app['auth']->logout();

        $this->post(route('login.store'), [
            'email' => 'amina@example.com',
            'password' => 'MyPrivatePassword123!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($resident->fresh());
        $this->get(route('dashboard'))->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('tenant/Dashboard'),
        );
    }

    public function test_management_can_resend_a_fresh_activation_link(): void
    {
        $tenant = Tenant::query()->create([
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.com',
        ]);

        $this->post(route('tenants.portal-invitation', $tenant))
            ->assertRedirect(route('tenants.index'));
        $this->post(route('tenants.portal-invitation', $tenant))
            ->assertRedirect(route('tenants.index'));

        $resident = $tenant->fresh()->user;
        $this->assertNotNull($resident);
        Notification::assertSentToTimes($resident, TenantPortalInvitation::class, 2);
        $this->assertNotNull($tenant->fresh()->portal_invited_at);
        $this->assertNull($tenant->fresh()->portal_activated_at);
    }

    public function test_a_tenant_without_email_is_created_without_an_unsafe_login_password(): void
    {
        $this->post(route('tenants.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => '',
            'phone' => '+256 700 000001',
            'identity_document_type' => 'national_id',
            'identity_document_number' => 'CM900012345678',
            'next_of_kin_name' => 'Sarah N.',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_phone' => '+256 700 000002',
            'preferred_channel' => 'whatsapp',
        ])->assertRedirect(route('tenants.index'));

        $tenant = Tenant::query()->firstOrFail();
        $this->assertNull($tenant->user_id);
        $this->assertNull($tenant->portal_invited_at);
        Notification::assertNothingSent();
    }

    public function test_identity_and_next_of_kin_are_required_for_management_onboarding(): void
    {
        $this->from(route('tenants.index'))->post(route('tenants.store'), [
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.com',
            'phone' => '',
            'preferred_channel' => 'whatsapp',
        ])->assertRedirect(route('tenants.index'))->assertSessionHasErrors([
            'identity_document_type',
            'identity_document_number',
            'next_of_kin_name',
            'next_of_kin_relationship',
            'next_of_kin_phone',
        ]);
    }

    public function test_identity_upload_rejects_unsupported_or_oversized_files(): void
    {
        Storage::fake('local');

        $payload = [
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.com',
            'phone' => '',
            'identity_document_type' => 'national_id',
            'identity_document_number' => '',
            'next_of_kin_name' => 'Sarah N.',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_phone' => '+256 700 000002',
            'preferred_channel' => 'whatsapp',
        ];

        $this->from(route('tenants.index'))->post(route('tenants.store'), [
            ...$payload,
            'identity_document' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
        ])->assertSessionHasErrors('identity_document');

        $this->from(route('tenants.index'))->post(route('tenants.store'), [
            ...$payload,
            'identity_document' => UploadedFile::fake()->create('national-id.pdf', 5121, 'application/pdf'),
        ])->assertSessionHasErrors('identity_document');

        $this->assertSame(0, Tenant::query()->count());
    }

    public function test_management_can_privately_download_and_verify_an_identity_document(): void
    {
        Storage::fake('local');
        $path = 'tenant-identities/2026/08/secure-file.pdf';
        Storage::disk('local')->put($path, 'private identity document');

        $tenant = Tenant::query()->create([
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.com',
            'identity_document_type' => 'national_id',
            'identity_document_number' => 'CM900012345678',
            'identity_document_last_four' => '5678',
            'identity_document_path' => $path,
            'identity_document_original_name' => 'national-id.pdf',
        ]);

        $this->get(route('tenants.identity-document.download', $tenant))
            ->assertOk()
            ->assertDownload('national-id.pdf');

        $this->patch(route('tenants.identity.verify', $tenant))
            ->assertRedirect(route('tenants.index'));

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'identity_verified_by' => $this->manager->id,
        ]);
        $this->assertNotNull($tenant->fresh()->identity_verified_at);

        $resident = User::factory()->create();
        $this->actingAs($resident);
        $this->get(route('tenants.identity-document.download', $tenant))->assertForbidden();
        $this->patch(route('tenants.identity.verify', $tenant))->assertForbidden();
    }

    public function test_a_tenant_can_review_their_masked_security_and_next_of_kin_profile(): void
    {
        Storage::fake('local');
        $path = 'tenant-identities/2026/08/secure-file.pdf';
        Storage::disk('local')->put($path, 'private identity document');
        $resident = User::factory()->create(['email' => 'amina@example.com']);
        $tenant = Tenant::query()->create([
            'user_id' => $resident->id,
            'portal_activated_at' => now(),
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.com',
            'phone' => '+256 700 000001',
            'identity_document_type' => 'national_id',
            'identity_document_number' => 'CM900012345678',
            'identity_document_last_four' => '5678',
            'identity_document_path' => $path,
            'identity_document_original_name' => 'national-id.pdf',
            'next_of_kin_name' => 'Sarah N.',
            'next_of_kin_relationship' => 'Sister',
            'next_of_kin_phone' => '+256 700 000002',
        ]);

        $this->actingAs($resident)
            ->get(route('tenant.profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/Profile')
                ->where('tenant.full_name', 'Amina Nansubuga')
                ->where('tenant.identity.type', 'National ID')
                ->where('tenant.identity.last_four', '5678')
                ->where('tenant.next_of_kin.name', 'Sarah N.')
                ->missing('tenant.identity.number'));

        $this->get(route('tenant.profile.identity-document'))
            ->assertOk()
            ->assertDownload('national-id.pdf');

        $this->actingAs($this->manager)
            ->get(route('tenant.profile'))
            ->assertForbidden();

        $this->assertSame('CM900012345678', $tenant->fresh()->identity_document_number);
    }

    public function test_active_lease_assigns_unit_and_configures_due_date_terms(): void
    {
        $tenant = Tenant::query()->create([
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => 'amina@example.com',
            'status' => 'pending',
        ]);
        $unit = Unit::query()->where('code', 'KIS-UP-01')->firstOrFail();

        $this->post(route('leases.store'), [
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 675000,
            'deposit_amount' => 675000,
            'due_day' => 5,
            'grace_days' => 3,
            'status' => 'active',
            'notes' => 'Confirmed lease-specific rate.',
        ])->assertRedirect(route('leases.index'));

        $this->assertDatabaseHas('leases', [
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-'.now()->format('Y').'-0001',
            'rent_amount' => 675000,
            'deposit_amount' => 675000,
            'due_day' => 5,
            'grace_days' => 3,
            'status' => 'active',
            'created_by' => $this->manager->id,
        ]);
        $this->assertDatabaseHas('units', ['id' => $unit->id, 'status' => 'occupied']);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'active']);
        $this->assertSame(700000, $unit->fresh()->rent_amount);
    }

    public function test_an_active_unit_cannot_receive_a_second_active_lease(): void
    {
        $unit = Unit::query()->where('code', 'KIS-UP-01')->firstOrFail();
        $firstTenant = Tenant::query()->create([
            'first_name' => 'First',
            'last_name' => 'Tenant',
            'email' => 'first@example.com',
        ]);
        $secondTenant = Tenant::query()->create([
            'first_name' => 'Second',
            'last_name' => 'Tenant',
            'email' => 'second@example.com',
        ]);

        Lease::query()->create([
            'tenant_id' => $firstTenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-LOCK-0001',
            'start_date' => now(),
            'rent_amount' => 700000,
            'deposit_amount' => 700000,
            'due_day' => 5,
            'status' => 'active',
        ]);

        $this->from(route('leases.index'))->post(route('leases.store'), [
            'tenant_id' => $secondTenant->id,
            'unit_id' => $unit->id,
            'start_date' => now()->toDateString(),
            'end_date' => '',
            'rent_amount' => 700000,
            'deposit_amount' => 700000,
            'due_day' => 5,
            'grace_days' => 3,
            'status' => 'active',
            'notes' => '',
        ])->assertRedirect(route('leases.index'))->assertSessionHasErrors('unit_id');

        $this->assertSame(1, Lease::query()->count());
    }

    public function test_management_can_activate_a_saved_draft_lease(): void
    {
        $tenant = Tenant::query()->create([
            'first_name' => 'Draft',
            'last_name' => 'Resident',
            'email' => 'draft@example.com',
            'status' => 'pending',
        ]);
        $unit = Unit::query()->where('code', 'GAY-UP-01')->firstOrFail();
        $lease = Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-DRAFT-0001',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 500000,
            'deposit_amount' => 500000,
            'due_day' => 5,
            'grace_days' => 3,
            'status' => 'draft',
        ]);

        $this->patch(route('leases.activate', $lease))
            ->assertRedirect(route('leases.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leases', [
            'id' => $lease->id,
            'status' => 'active',
        ]);
        $this->assertNotNull($lease->fresh()->signed_at);
        $this->assertSame('occupied', $unit->fresh()->status);
        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_a_future_draft_cannot_be_activated_early(): void
    {
        $tenant = Tenant::query()->create([
            'first_name' => 'Future',
            'last_name' => 'Resident',
            'email' => 'future@example.com',
        ]);
        $unit = Unit::query()->where('code', 'GAY-UP-01')->firstOrFail();
        $lease = Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-DRAFT-0002',
            'start_date' => now()->addWeek()->toDateString(),
            'rent_amount' => 500000,
            'deposit_amount' => 500000,
            'due_day' => 5,
            'grace_days' => 3,
            'status' => 'draft',
        ]);

        $this->from(route('leases.index'))
            ->patch(route('leases.activate', $lease))
            ->assertRedirect(route('leases.index'))
            ->assertSessionHasErrors('lease');

        $this->assertSame('draft', $lease->fresh()->status);
        $this->assertSame('setup_required', $unit->fresh()->status);
    }

    public function test_management_can_edit_a_draft_lease_assignment_and_terms(): void
    {
        $firstTenant = Tenant::query()->create([
            'first_name' => 'First',
            'last_name' => 'Resident',
            'email' => 'first-draft@example.com',
        ]);
        $secondTenant = Tenant::query()->create([
            'first_name' => 'Second',
            'last_name' => 'Resident',
            'email' => 'second-draft@example.com',
        ]);
        $firstUnit = Unit::query()->where('code', 'GAY-UP-01')->firstOrFail();
        $secondUnit = Unit::query()->where('code', 'GAY-UP-02')->firstOrFail();
        $lease = Lease::query()->create([
            'tenant_id' => $firstTenant->id,
            'unit_id' => $firstUnit->id,
            'lease_number' => 'KR-DRAFT-0003',
            'start_date' => now()->toDateString(),
            'rent_amount' => 500000,
            'deposit_amount' => 500000,
            'due_day' => 5,
            'grace_days' => 3,
            'status' => 'draft',
        ]);

        $this->patch(route('leases.update', $lease), [
            'tenant_id' => $secondTenant->id,
            'unit_id' => $secondUnit->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 525000,
            'deposit_amount' => 300000,
            'due_day' => 7,
            'grace_days' => 4,
            'notes' => 'Updated before activation.',
        ])->assertRedirect(route('leases.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leases', [
            'id' => $lease->id,
            'tenant_id' => $secondTenant->id,
            'unit_id' => $secondUnit->id,
            'rent_amount' => 525000,
            'due_day' => 7,
            'status' => 'draft',
        ]);
    }

    public function test_ending_an_active_lease_releases_the_unit_and_preserves_the_record(): void
    {
        $tenant = Tenant::query()->create([
            'first_name' => 'Ending',
            'last_name' => 'Resident',
            'email' => 'ending@example.com',
            'status' => 'active',
        ]);
        $unit = Unit::query()->where('code', 'GAY-UP-01')->firstOrFail();
        $unit->update(['status' => 'occupied']);
        $lease = Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-ACTIVE-0001',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 500000,
            'deposit_amount' => 500000,
            'due_day' => 5,
            'grace_days' => 3,
            'status' => 'active',
            'signed_at' => now()->subMonth(),
        ]);

        $this->patch(route('leases.end', $lease), [
            'end_date' => now()->toDateString(),
        ])->assertRedirect(route('leases.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leases', [
            'id' => $lease->id,
            'status' => 'ended',
        ]);
        $this->assertSame(now()->toDateString(), $lease->fresh()->end_date->toDateString());
        $this->assertSame('available', $unit->fresh()->status);
        $this->assertSame('former', $tenant->fresh()->status);
    }

    public function test_only_an_empty_draft_lease_can_be_deleted(): void
    {
        $tenant = Tenant::query()->create([
            'first_name' => 'Delete',
            'last_name' => 'Draft',
            'email' => 'delete-draft@example.com',
        ]);
        $firstUnit = Unit::query()->where('code', 'GAY-UP-01')->firstOrFail();
        $secondUnit = Unit::query()->where('code', 'GAY-UP-02')->firstOrFail();
        $draft = Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $firstUnit->id,
            'lease_number' => 'KR-DELETE-0001',
            'start_date' => now(),
            'rent_amount' => 500000,
            'deposit_amount' => 500000,
            'due_day' => 5,
            'status' => 'draft',
        ]);
        $active = Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $secondUnit->id,
            'lease_number' => 'KR-DELETE-0002',
            'start_date' => now(),
            'rent_amount' => 500000,
            'deposit_amount' => 500000,
            'due_day' => 5,
            'status' => 'active',
        ]);

        $this->delete(route('leases.destroy', $draft))
            ->assertRedirect(route('leases.index'));
        $this->assertSoftDeleted($draft);

        $this->from(route('leases.index'))
            ->delete(route('leases.destroy', $active))
            ->assertRedirect(route('leases.index'))
            ->assertSessionHasErrors('lease');
        $this->assertNotSoftDeleted($active);
    }

    public function test_management_can_update_tenant_security_and_portal_contact_details(): void
    {
        Storage::fake('local');
        $oldPath = 'tenant-identities/2026/08/old-id.pdf';
        Storage::disk('local')->put($oldPath, 'old document');
        $resident = User::factory()->create([
            'name' => 'Old Resident',
            'email' => 'old-resident@example.com',
            'role' => 'tenant',
        ]);
        $tenant = Tenant::query()->create([
            'user_id' => $resident->id,
            'first_name' => 'Old',
            'last_name' => 'Resident',
            'email' => 'old-resident@example.com',
            'phone' => '+256 700 000010',
            'identity_document_type' => 'national_id',
            'identity_document_number' => 'CM000011112222',
            'identity_document_last_four' => '2222',
            'identity_document_path' => $oldPath,
            'identity_verified_at' => now(),
            'identity_verified_by' => $this->manager->id,
            'next_of_kin_name' => 'Old Kin',
            'next_of_kin_relationship' => 'Sibling',
            'next_of_kin_phone' => '+256 700 000011',
        ]);

        $this->post(route('tenants.update', $tenant), [
            '_method' => 'PATCH',
            'first_name' => 'Updated',
            'last_name' => 'Resident',
            'email' => 'updated-resident@example.com',
            'phone' => '+256 700 000012',
            'alternative_phone' => '+256 700 000013',
            'identity_document_type' => 'passport',
            'identity_document_number' => '',
            'identity_document' => UploadedFile::fake()->image('passport.jpg'),
            'next_of_kin_name' => 'Updated Kin',
            'next_of_kin_relationship' => 'Parent',
            'next_of_kin_phone' => '+256 700 000014',
            'next_of_kin_email' => 'kin@example.com',
            'next_of_kin_address' => 'Kampala',
            'preferred_channel' => 'email',
            'notes' => 'Updated safely.',
        ])->assertRedirect(route('tenants.index'))->assertSessionHasNoErrors();

        $freshTenant = $tenant->fresh();
        $this->assertSame('Updated Resident', $freshTenant->full_name);
        $this->assertSame('passport', $freshTenant->identity_document_type);
        $this->assertSame('CM000011112222', $freshTenant->identity_document_number);
        $this->assertNull($freshTenant->identity_verified_at);
        $this->assertNull($freshTenant->identity_verified_by);
        $this->assertSame('Updated Kin', $freshTenant->next_of_kin_name);
        $this->assertSame('updated-resident@example.com', $resident->fresh()->email);
        $this->assertSame('Updated Resident', $resident->fresh()->name);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($freshTenant->identity_document_path);
    }

    public function test_only_a_tenant_without_lease_or_financial_history_can_be_deleted(): void
    {
        $resident = User::factory()->create(['role' => 'tenant']);
        $emptyTenant = Tenant::query()->create([
            'user_id' => $resident->id,
            'first_name' => 'Empty',
            'last_name' => 'Profile',
            'email' => $resident->email,
        ]);

        $this->delete(route('tenants.destroy', $emptyTenant))
            ->assertRedirect(route('tenants.index'));
        $this->assertSoftDeleted($emptyTenant);
        $this->assertDatabaseMissing('users', ['id' => $resident->id]);

        $protectedTenant = Tenant::query()->create([
            'first_name' => 'Protected',
            'last_name' => 'Resident',
            'email' => 'protected@example.com',
        ]);
        $unit = Unit::query()->where('code', 'GAY-UP-01')->firstOrFail();
        Lease::query()->create([
            'tenant_id' => $protectedTenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-PROTECTED-0001',
            'start_date' => now(),
            'rent_amount' => 500000,
            'deposit_amount' => 500000,
            'due_day' => 5,
            'status' => 'draft',
        ]);

        $this->from(route('tenants.index'))
            ->delete(route('tenants.destroy', $protectedTenant))
            ->assertRedirect(route('tenants.index'))
            ->assertSessionHasErrors('tenant');
        $this->assertNotSoftDeleted($protectedTenant);
    }

    public function test_tenant_role_cannot_enter_management_routes(): void
    {
        $resident = User::factory()->create();
        $this->actingAs($resident);

        $this->get(route('properties.index'))->assertForbidden();
        $this->get(route('tenants.index'))->assertForbidden();
        $this->get(route('leases.index'))->assertForbidden();
    }
}
