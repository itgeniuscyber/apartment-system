<?php

namespace Tests\Feature\Operations;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\MessageThread;
use App\Models\Notice;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MaintenanceAndMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected User $resident;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PortfolioSeeder::class);
        $this->administrator = User::factory()->management()->create();
        $this->resident = User::factory()->create(['email' => 'resident@example.test']);
        $this->tenant = Tenant::query()->create([
            'user_id' => $this->resident->id,
            'first_name' => 'Amina',
            'last_name' => 'Nansubuga',
            'email' => $this->resident->email,
            'phone' => '+256700000001',
            'status' => 'active',
            'preferred_channel' => 'email',
        ]);
        $unit = Unit::query()->where('code', 'KIS-UP-01')->firstOrFail();
        $unit->update(['status' => 'occupied']);
        Lease::query()->create([
            'tenant_id' => $this->tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-OPS-0001',
            'start_date' => now()->startOfMonth(),
            'rent_amount' => 700000,
            'deposit_amount' => 700000,
            'due_day' => 5,
            'grace_days' => 3,
            'billing_frequency' => 'monthly',
            'status' => 'active',
            'signed_at' => now(),
            'created_by' => $this->administrator->id,
        ]);
    }

    public function test_tenant_submits_maintenance_evidence_and_management_posts_public_and_private_updates(): void
    {
        Storage::fake('local');
        $this->actingAs($this->resident)
            ->post(route('tenant.requests.store'), [
                'category' => 'plumbing',
                'title' => 'Kitchen sink leak',
                'description' => 'Water is leaking beneath the kitchen sink.',
                'priority' => 'urgent',
                'access_permission' => true,
                'preferred_visit_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'evidence' => UploadedFile::fake()->image('leak.jpg'),
            ])->assertRedirect(route('tenant.requests'));

        $maintenance = MaintenanceRequest::query()->firstOrFail();
        $this->assertSame($this->tenant->id, $maintenance->tenant_id);
        $this->assertNotNull($maintenance->unit_id);
        Storage::disk('local')->assertExists($maintenance->evidence_path);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->administrator->id,
        ]);

        $this->actingAs($this->administrator)
            ->patch(route('maintenance.update', $maintenance), [
                'status' => 'in_progress',
                'priority' => 'urgent',
                'assigned_to' => $this->administrator->id,
                'body' => 'A plumber is scheduled for tomorrow morning.',
                'is_internal' => false,
            ])->assertRedirect(route('maintenance.index'));
        $this->actingAs($this->administrator)
            ->patch(route('maintenance.update', $maintenance), [
                'status' => 'in_progress',
                'priority' => 'urgent',
                'assigned_to' => $this->administrator->id,
                'body' => 'Check the shared stop valve before arrival.',
                'is_internal' => true,
            ])->assertRedirect(route('maintenance.index'));

        $this->actingAs($this->resident)
            ->get(route('tenant.requests'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/Requests')
                ->has('requests', 1)
                ->has('requests.0.updates', 1)
                ->where('requests.0.updates.0.body', 'A plumber is scheduled for tomorrow morning.'));

        $this->get(route('maintenance.evidence', $maintenance))->assertOk()->assertDownload('leak.jpg');
    }

    public function test_evidence_and_request_replies_are_scoped_to_the_owning_tenant(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('maintenance-evidence/secure.pdf', 'private');
        $maintenance = MaintenanceRequest::query()->create([
            'reference' => 'MNT-2026-00001',
            'tenant_id' => $this->tenant->id,
            'category' => 'security',
            'title' => 'Door concern',
            'description' => 'Please inspect the lock.',
            'priority' => 'normal',
            'status' => 'open',
            'evidence_path' => 'maintenance-evidence/secure.pdf',
            'evidence_original_name' => 'secure.pdf',
        ]);
        $otherUser = User::factory()->create(['email' => 'other@example.test']);
        Tenant::query()->create([
            'user_id' => $otherUser->id,
            'first_name' => 'Other',
            'last_name' => 'Tenant',
            'email' => $otherUser->email,
        ]);

        $this->actingAs($otherUser)
            ->get(route('maintenance.evidence', $maintenance))->assertForbidden();
        $this->post(route('tenant.requests.reply', $maintenance), ['body' => 'Not my request'])
            ->assertForbidden();
        $this->assertDatabaseCount('maintenance_updates', 0);
    }

    public function test_management_publishes_a_targeted_notice_and_tenant_can_start_a_conversation(): void
    {
        $this->actingAs($this->administrator)
            ->post(route('messages.notices.publish'), [
                'subject' => 'Water service update',
                'body' => 'Water service will be paused briefly tomorrow morning.',
                'severity' => 'warning',
                'audience_type' => 'tenant',
                'tenant_id' => $this->tenant->id,
                'estate_id' => '',
                'channels' => ['database'],
            ])->assertRedirect(route('messages.index'));

        $notice = Notice::query()->firstOrFail();
        $this->assertSame(1, $notice->recipient_count);
        $this->assertDatabaseHas('notice_recipients', [
            'notice_id' => $notice->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertSame(1, $this->tenant->notifications()->count());

        $this->actingAs($this->resident)
            ->get(route('tenant.messages'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/Messages')
                ->has('notices', 1)
                ->where('notices.0.subject', 'Water service update'));
        $this->post(route('tenant.messages.store'), [
            'subject' => 'Question about the notice',
            'category' => 'general',
            'body' => 'Will the upper section also be affected?',
        ])->assertRedirect(route('tenant.messages'));

        $thread = MessageThread::query()->firstOrFail();
        $this->actingAs($this->administrator)
            ->patch(route('messages.threads.read', $thread))
            ->assertRedirect();
        $this->assertNotNull($thread->messages()->where('sender_role', 'tenant')->firstOrFail()->read_at);

        $this->actingAs($this->administrator)
            ->post(route('messages.threads.reply', $thread), [
                'body' => 'Yes, but only between 9:00 and 10:00.',
                'status' => 'closed',
            ])->assertRedirect(route('messages.index'));

        $this->assertSame('closed', $thread->fresh()->status);
        $this->assertSame(2, $thread->messages()->count());
        $this->assertSame('management', $thread->messages()->latest('id')->firstOrFail()->sender_role);
    }

    public function test_tenant_cannot_publish_management_notices_or_manage_requests(): void
    {
        $maintenance = MaintenanceRequest::query()->create([
            'reference' => 'MNT-2026-00999',
            'tenant_id' => $this->tenant->id,
            'category' => 'other',
            'title' => 'Existing request',
            'description' => 'An existing request for authorization testing.',
            'priority' => 'normal',
            'status' => 'open',
        ]);
        $this->actingAs($this->resident);
        $this->post(route('messages.notices.publish'), [])->assertForbidden();
        $this->patch(route('maintenance.update', $maintenance), [])->assertForbidden();
    }
}
