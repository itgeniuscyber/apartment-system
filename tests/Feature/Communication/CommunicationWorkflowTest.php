<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationSetting;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\TenantEventNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-25 10:00:00'));
        $this->seed(PortfolioSeeder::class);
        $this->administrator = User::factory()->management()->create();
        $this->actingAs($this->administrator);
    }

    public function test_only_an_administrator_can_view_and_change_encrypted_provider_settings(): void
    {
        $this->get(route('communication.settings.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('communication/Settings')
                ->where('settings.sms_api_key_saved', false)
                ->where('settings.whatsapp_access_token_saved', false));

        $this->patch(route('communication.settings.update'), $this->settingsPayload([
            'sms_enabled' => true,
            'sms_username' => 'kr-estates',
            'sms_api_key' => 'sms-secret-key',
            'whatsapp_enabled' => true,
            'whatsapp_phone_number_id' => '1234567890',
            'whatsapp_access_token' => 'meta-secret-token',
            'whatsapp_notice_template' => 'kr_notice',
        ]))->assertRedirect(route('communication.settings.index'));

        $settings = CommunicationSetting::current();
        $this->assertSame('sms-secret-key', $settings->sms_api_key);
        $this->assertSame('meta-secret-token', $settings->whatsapp_access_token);
        $this->assertNotSame('sms-secret-key', DB::table('communication_settings')->value('sms_api_key'));
        $this->assertNotSame('meta-secret-token', DB::table('communication_settings')->value('whatsapp_access_token'));

        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager);
        $this->get(route('communication.settings.index'))->assertForbidden();
        $this->patch(route('communication.settings.update'), $this->settingsPayload())->assertForbidden();
    }

    public function test_financial_documents_create_dashboard_notifications_and_tracked_email_deliveries(): void
    {
        $lease = $this->createActiveLease();

        $this->post(route('finance.invoices.generate'), ['billing_month' => '2026-08'])
            ->assertRedirect(route('finance.index'));

        $invoice = Invoice::query()->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Tenant::class,
            'notifiable_id' => $lease->tenant_id,
        ]);
        $this->assertDatabaseHas('communication_deliveries', [
            'tenant_id' => $lease->tenant_id,
            'event_type' => 'invoice_issued',
            'reference' => $invoice->invoice_number,
            'channel' => 'email',
            'status' => 'sent',
        ]);

        $this->post(route('finance.invoices.email', $invoice))
            ->assertRedirect(route('finance.index'));

        $this->assertSame(2, CommunicationDelivery::query()
            ->where('event_type', 'invoice_issued')
            ->where('channel', 'email')
            ->count());
    }

    public function test_tenant_can_only_mark_their_own_dashboard_notification_as_read(): void
    {
        [$firstUser, $firstTenant] = $this->resident('first@example.test');
        [$secondUser, $secondTenant] = $this->resident('second@example.test');
        $notification = new TenantEventNotification(
            eventType: 'test_notice',
            title: 'Your notice',
            message: 'This belongs to the first tenant.',
        );
        $firstTenant->notifyNow($notification, ['database']);
        $notificationId = $firstTenant->notifications()->firstOrFail()->id;

        $this->actingAs($secondUser)
            ->patch(route('notifications.read', $notificationId))
            ->assertNotFound();
        $this->assertNull($firstTenant->notifications()->firstOrFail()->read_at);

        $this->actingAs($firstUser)
            ->patch(route('notifications.read', $notificationId))
            ->assertRedirect();
        $this->assertNotNull($firstTenant->notifications()->firstOrFail()->read_at);
        $this->assertSame(0, $secondTenant->notifications()->count());
    }

    public function test_rent_reminders_are_scheduled_for_configured_days_and_deduplicated(): void
    {
        $lease = $this->createActiveLease();
        CommunicationSetting::current()->update([
            'email_enabled' => false,
            'reminder_channels' => ['database'],
            'reminder_days_before' => [7, 3, 0],
        ]);
        $this->post(route('finance.invoices.generate'), ['billing_month' => '2026-08']);
        $tenant = $lease->tenant;
        $tenant->notifications()->delete();

        $this->artisan('rent:send-reminders', ['--date' => '2026-08-02'])
            ->expectsOutputToContain('Queued 1 rent reminders')
            ->assertSuccessful();
        $this->artisan('rent:send-reminders', ['--date' => '2026-08-02'])
            ->expectsOutputToContain('Queued 0 rent reminders')
            ->assertSuccessful();

        $this->assertDatabaseCount('tenant_notification_events', 1);
        $this->assertSame(1, $tenant->notifications()->count());
        $this->assertSame('rent_reminder', $tenant->notifications()->firstOrFail()->data['event_type']);
    }

    /** @param array<string, mixed> $overrides */
    private function settingsPayload(array $overrides = []): array
    {
        return [
            'in_app_enabled' => true,
            'email_enabled' => true,
            'email_from_name' => 'K&R Estates',
            'email_from_address' => 'management@example.test',
            'sms_enabled' => false,
            'sms_provider' => 'africas_talking',
            'sms_sender_id' => 'KREstates',
            'sms_username' => '',
            'sms_api_key' => '',
            'sms_webhook_url' => '',
            'sms_webhook_token' => '',
            'whatsapp_enabled' => false,
            'whatsapp_provider' => 'meta_cloud',
            'whatsapp_phone_number_id' => '',
            'whatsapp_access_token' => '',
            'whatsapp_api_version' => 'v23.0',
            'whatsapp_notice_template' => '',
            'whatsapp_invoice_template' => '',
            'whatsapp_receipt_template' => '',
            'whatsapp_reminder_template' => '',
            'invoice_channels' => ['database', 'email'],
            'receipt_channels' => ['database', 'email'],
            'reminder_channels' => ['database', 'email'],
            'reminder_days_before' => [7, 3, 0],
            'overdue_repeat_days' => 3,
            ...$overrides,
        ];
    }

    private function createActiveLease(): Lease
    {
        $unit = Unit::query()->where('code', 'KIS-UP-01')->firstOrFail();
        [, $tenant] = $this->resident('resident@example.test');
        $unit->update(['status' => 'occupied']);

        return Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-COMM-0001',
            'start_date' => '2026-08-01',
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

    /** @return array{User, Tenant} */
    private function resident(string $email): array
    {
        $user = User::factory()->create(['email' => $email]);
        $tenant = Tenant::query()->create([
            'user_id' => $user->id,
            'first_name' => 'Tenant',
            'last_name' => str($user->id),
            'email' => $email,
            'phone' => '+2567000000'.str_pad((string) $user->id, 2, '0', STR_PAD_LEFT),
            'status' => 'active',
            'preferred_channel' => 'email',
        ]);

        return [$user, $tenant];
    }
}
