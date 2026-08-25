<?php

namespace Tests\Feature\Finance;

use App\Models\GatewayWebhookEvent;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use App\Models\PaymentLink;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantPaymentPortalTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-25 10:00:00'));
        $this->seed(PortfolioSeeder::class);
        $this->manager = User::factory()->management()->create();
    }

    public function test_tenant_payment_dashboard_is_scoped_to_the_signed_in_resident(): void
    {
        [$resident, $tenant, $invoice] = $this->createBilledResident('KIS-UP-01', 700000, 5);
        [, , $otherInvoice] = $this->createBilledResident('GAY-LOW-01', 500000, 5);

        $this->actingAs($this->manager)->post(route('finance.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 200000,
            'payment_method' => 'mobile_money',
            'provider_reference' => 'MTN-PORTAL-001',
            'paid_at' => '2026-08-25',
        ])->assertRedirect(route('finance.index'));

        $this->actingAs($resident)->get(route('tenant.payments'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/Payments')
                ->where('tenant.id', $tenant->id)
                ->where('summary.outstanding', 500000)
                ->where('summary.paid_total', 200000)
                ->has('invoices', 1)
                ->where('invoices.0.id', $invoice->id)
                ->has('payments', 1)
                ->missing('invoices.1'));

        $this->assertNotSame($invoice->tenant_id, $otherInvoice->tenant_id);
    }

    public function test_management_can_create_an_encrypted_expiring_payment_link(): void
    {
        [, $tenant, $invoice] = $this->createBilledResident('KIS-UP-01', 700000, 5);

        $this->actingAs($this->manager)->post(route('finance.payment-links.store', $invoice), [
            'expires_in_days' => 10,
        ])->assertRedirect(route('finance.index'));

        $paymentLink = PaymentLink::query()->firstOrFail();

        $this->assertSame($tenant->id, $paymentLink->tenant_id);
        $this->assertSame($invoice->settlement_account_id, $paymentLink->settlement_account_id);
        $this->assertSame(700000, $paymentLink->amount);
        $this->assertSame(64, strlen($paymentLink->token));
        $this->assertNotSame($paymentLink->token, $paymentLink->getRawOriginal('token'));
        $this->assertSame(hash('sha256', $paymentLink->token), $paymentLink->token_hash);
        $this->assertTrue($paymentLink->expires_at?->isSameDay(now()->addDays(10)) === true);

        $this->get(route('payment-links.show', ['token' => $paymentLink->token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/PaymentCheckout')
                ->where('paymentLink.usable', true)
                ->where('paymentLink.amount', 700000)
                ->where('invoice.invoice_number', $invoice->invoice_number)
                ->where('invoice.tenant_first_name', $tenant->first_name)
                ->where('invoice.settlement_account.account_last_four', '3432')
                ->missing('invoice.settlement_account.account_number'));
    }

    public function test_invoice_and_receipt_pdfs_are_downloadable_only_by_the_record_owner_or_management(): void
    {
        [$resident, , $invoice] = $this->createBilledResident('KIS-UP-01', 700000, 5);
        [$otherResident, , $otherInvoice] = $this->createBilledResident('GAY-LOW-01', 500000, 5);

        $this->actingAs($this->manager)->post(route('finance.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 700000,
            'payment_method' => 'bank_transfer',
            'provider_reference' => 'CENT-PDF-001',
            'paid_at' => '2026-08-25',
        ]);
        $payment = Payment::query()->where('tenant_id', $invoice->tenant_id)->firstOrFail();

        $invoiceResponse = $this->actingAs($resident)->get(route('tenant.payments.invoices.pdf', $invoice));
        $invoiceResponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $invoiceResponse->getContent());

        $receiptResponse = $this->actingAs($resident)->get(route('tenant.payments.receipts.pdf', $payment));
        $receiptResponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $receiptResponse->getContent());

        $this->actingAs($resident)->get(route('tenant.payments.invoices.pdf', $otherInvoice))->assertForbidden();
        $this->actingAs($otherResident)->get(route('tenant.payments.receipts.pdf', $payment))->assertForbidden();
        $this->actingAs($this->manager)->get(route('finance.invoices.pdf', $otherInvoice))->assertOk();
    }

    public function test_signed_gateway_callback_confirms_once_and_rejects_tampering(): void
    {
        [, , $invoice] = $this->createBilledResident('KIS-UP-01', 700000, 5);
        $this->actingAs($this->manager)->post(route('finance.payment-links.store', $invoice));
        $paymentLink = PaymentLink::query()->firstOrFail();

        config()->set('payments.driver', 'testpay');
        config()->set('payments.webhook_secret', 'local-test-secret');

        $attempt = PaymentAttempt::query()->create([
            'public_id' => (string) Str::uuid(),
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'settlement_account_id' => $invoice->settlement_account_id,
            'payment_link_id' => $paymentLink->id,
            'provider' => 'testpay',
            'payment_method' => 'mobile_money',
            'idempotency_key' => (string) Str::uuid(),
            'amount' => 700000,
            'currency' => 'UGX',
            'status' => 'pending',
        ]);

        $payload = [
            'event_id' => 'evt-20260825-001',
            'attempt_id' => $attempt->public_id,
            'status' => 'confirmed',
            'provider_reference' => 'TP-PAID-001',
            'amount' => 700000,
            'currency' => 'UGX',
        ];

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, GatewayWebhookEvent::query()->count());
        $this->assertDatabaseHas('payments', [
            'payment_attempt_id' => $attempt->id,
            'provider_reference' => 'TP-PAID-001',
            'amount' => 700000,
        ]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'balance_due' => 0]);
        $this->assertDatabaseHas('payment_links', ['id' => $paymentLink->id, 'status' => 'used']);

        $changedConfirmation = [
            ...$payload,
            'event_id' => 'evt-20260825-changed',
            'provider_reference' => 'TP-CHANGED-REFERENCE',
        ];
        $this->postSignedWebhook($changedConfirmation)->assertConflict();

        $tampered = [...$payload, 'event_id' => 'evt-20260825-002', 'amount' => 1];
        $json = json_encode($tampered, JSON_THROW_ON_ERROR);

        $this->call('POST', route('webhooks.payments', 'testpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KR_SIGNATURE' => hash_hmac('sha256', $json, 'wrong-secret'),
        ], $json)->assertUnauthorized();

        $this->assertSame(1, Payment::query()->count());
    }

    /** @return array{User, Tenant, Invoice} */
    private function createBilledResident(string $unitCode, int $rentAmount, int $dueDay): array
    {
        $resident = User::factory()->create();
        $tenant = Tenant::query()->create([
            'user_id' => $resident->id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => $resident->email,
            'phone' => '+256 700 '.fake()->unique()->numerify('######'),
            'status' => 'active',
        ]);
        $unit = Unit::query()->where('code', $unitCode)->firstOrFail();
        $unit->update(['status' => 'occupied']);
        $lease = Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-PORTAL-'.str_pad((string) (Lease::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'start_date' => '2026-08-01',
            'rent_amount' => $rentAmount,
            'deposit_amount' => $rentAmount,
            'due_day' => $dueDay,
            'grace_days' => 3,
            'billing_frequency' => 'monthly',
            'status' => 'active',
            'signed_at' => now(),
            'created_by' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)->post(route('finance.invoices.generate'), [
            'billing_month' => '2026-08',
        ]);

        return [$resident, $tenant, Invoice::query()->where('lease_id', $lease->id)->firstOrFail()];
    }

    /** @param array<string, mixed> $payload */
    private function postSignedWebhook(array $payload): TestResponse
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', route('webhooks.payments', 'testpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KR_SIGNATURE' => hash_hmac('sha256', $json, 'local-test-secret'),
        ], $json);
    }
}
