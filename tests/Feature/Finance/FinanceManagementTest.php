<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-25 10:00:00'));
        $this->seed(PortfolioSeeder::class);
        $this->manager = User::factory()->management()->create();
        $this->actingAs($this->manager);
    }

    public function test_management_can_view_a_truthful_empty_finance_register(): void
    {
        $this->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/Index')
                ->where('summary.invoiced', 0)
                ->where('summary.collected', 0)
                ->where('summary.outstanding', 0)
                ->has('accounts', 2)
                ->has('invoices', 0)
                ->has('payments', 0)
                ->has('aging', 5));
    }

    public function test_monthly_invoice_generation_uses_lease_terms_and_estate_routing_once(): void
    {
        $lease = $this->createActiveLease('KIS-UP-01', 700000, 5);
        $accountId = $lease->unit->section->estate->settlement_account_id;

        $this->post(route('finance.invoices.generate'), [
            'billing_month' => '2026-08',
        ])->assertRedirect(route('finance.index'));

        $this->assertDatabaseHas('invoices', [
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'unit_id' => $lease->unit_id,
            'settlement_account_id' => $accountId,
            'invoice_number' => 'INV-000001',
            'billing_period_start' => '2026-08-01 00:00:00',
            'billing_period_end' => '2026-08-31 00:00:00',
            'issue_date' => '2026-07-29 00:00:00',
            'due_date' => '2026-08-05 00:00:00',
            'total_amount' => 700000,
            'amount_paid' => 0,
            'balance_due' => 700000,
            'status' => 'sent',
            'created_by' => $this->manager->id,
        ]);

        $this->post(route('finance.invoices.generate'), [
            'billing_month' => '2026-08',
        ])->assertRedirect(route('finance.index'));

        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_draft_and_out_of_period_leases_are_not_invoiced(): void
    {
        $active = $this->createActiveLease('KIS-UP-01', 700000, 5);
        $draft = $this->createLease('GAY-LOW-01', 500000, 1, 'draft', '2026-08-01');
        $future = $this->createLease('KIT-MAIN-01', 500000, 1, 'active', '2026-09-01');

        $this->post(route('finance.invoices.generate'), [
            'billing_month' => '2026-08',
        ])->assertRedirect(route('finance.index'));

        $this->assertDatabaseHas('invoices', ['lease_id' => $active->id]);
        $this->assertDatabaseMissing('invoices', ['lease_id' => $draft->id]);
        $this->assertDatabaseMissing('invoices', ['lease_id' => $future->id]);
    }

    public function test_payment_is_partially_then_fully_allocated_with_receipt_numbers(): void
    {
        $lease = $this->createActiveLease('KIS-UP-01', 700000, 5);
        $this->generateInvoices();
        $invoice = Invoice::query()->firstOrFail();

        $this->post(route('finance.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 200000,
            'payment_method' => 'mobile_money',
            'provider_reference' => 'MTN-20260825-001',
            'paid_at' => '2026-08-25',
            'payer_phone' => '+256 700 000001',
            'payer_email' => '',
            'card_brand' => '',
            'notes' => 'First instalment.',
        ])->assertRedirect(route('finance.index'));

        $payment = Payment::query()->firstOrFail();
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'tenant_id' => $lease->tenant_id,
            'settlement_account_id' => $invoice->settlement_account_id,
            'payment_number' => 'PAY-2026-000001',
            'receipt_number' => 'RCT-2026-000001',
            'amount' => 200000,
            'status' => 'confirmed',
            'recorded_by' => $this->manager->id,
        ]);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => 200000,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount_paid' => 200000,
            'balance_due' => 500000,
            'status' => 'partially_paid',
        ]);

        $this->post(route('finance.payments.store'), [
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'payment_method' => 'bank_transfer',
            'provider_reference' => 'CENT-20260825-002',
            'paid_at' => '2026-08-25',
            'payer_phone' => '',
            'payer_email' => '',
            'card_brand' => '',
            'notes' => '',
        ])->assertRedirect(route('finance.index'));

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'amount_paid' => 700000,
            'balance_due' => 0,
            'status' => 'paid',
        ]);
        $this->assertSame(2, Payment::query()->count());
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_overpayment_and_duplicate_provider_reference_are_rejected(): void
    {
        $firstLease = $this->createActiveLease('KIS-UP-01', 700000, 5);
        $secondLease = $this->createActiveLease('GAY-LOW-01', 500000, 1);
        $this->generateInvoices();
        $firstInvoice = Invoice::query()->where('lease_id', $firstLease->id)->firstOrFail();
        $secondInvoice = Invoice::query()->where('lease_id', $secondLease->id)->firstOrFail();

        $this->from(route('finance.index'))->post(route('finance.payments.store'), [
            'invoice_id' => $firstInvoice->id,
            'amount' => 700001,
            'payment_method' => 'cash',
            'provider_reference' => '',
            'paid_at' => '2026-08-25',
        ])->assertRedirect(route('finance.index'))->assertSessionHasErrors('amount');

        $payload = [
            'amount' => 100000,
            'payment_method' => 'mobile_money',
            'provider_reference' => 'MTN-DUPLICATE-001',
            'paid_at' => '2026-08-25',
        ];

        $this->post(route('finance.payments.store'), [
            ...$payload,
            'invoice_id' => $firstInvoice->id,
        ])->assertRedirect(route('finance.index'));

        $this->from(route('finance.index'))->post(route('finance.payments.store'), [
            ...$payload,
            'invoice_id' => $secondInvoice->id,
        ])->assertRedirect(route('finance.index'))->assertSessionHasErrors('provider_reference');

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_finance_dashboard_separates_owner_accounts_and_aging_totals(): void
    {
        $kisaasi = $this->createActiveLease('KIS-UP-01', 700000, 5);
        $this->createActiveLease('GAY-LOW-01', 500000, 1);
        $this->generateInvoices();
        $kisaasiInvoice = Invoice::query()->where('lease_id', $kisaasi->id)->firstOrFail();

        $this->post(route('finance.payments.store'), [
            'invoice_id' => $kisaasiInvoice->id,
            'amount' => 700000,
            'payment_method' => 'bank_transfer',
            'provider_reference' => 'CENT-SETTLED-001',
            'paid_at' => '2026-08-25',
        ])->assertRedirect(route('finance.index'));

        $this->get(route('finance.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/Index')
                ->where('summary.scheduled_monthly', 1200000)
                ->where('summary.invoiced', 1200000)
                ->where('summary.collected', 700000)
                ->where('summary.outstanding', 500000)
                ->where('summary.overdue', 500000)
                ->where('accounts.0.account_last_four', '3432')
                ->where('accounts.0.collected', 700000)
                ->where('accounts.0.outstanding', 0)
                ->where('accounts.1.account_last_four', '5278')
                ->where('accounts.1.collected', 0)
                ->where('accounts.1.outstanding', 500000)
                ->has('invoices', 2)
                ->has('payments', 1));
    }

    public function test_tenant_role_cannot_use_management_finance_routes(): void
    {
        $resident = User::factory()->create();
        $this->actingAs($resident);

        $this->get(route('finance.index'))->assertForbidden();
        $this->post(route('finance.invoices.generate'), ['billing_month' => '2026-08'])->assertForbidden();
        $this->post(route('finance.payments.store'), [])->assertForbidden();
    }

    private function generateInvoices(string $month = '2026-08'): void
    {
        $this->post(route('finance.invoices.generate'), [
            'billing_month' => $month,
        ])->assertRedirect(route('finance.index'));
    }

    private function createActiveLease(string $unitCode, int $rentAmount, int $dueDay): Lease
    {
        return $this->createLease($unitCode, $rentAmount, $dueDay, 'active', '2026-08-01');
    }

    private function createLease(
        string $unitCode,
        int $rentAmount,
        int $dueDay,
        string $status,
        string $startDate,
    ): Lease {
        $unit = Unit::query()->where('code', $unitCode)->firstOrFail();
        $tenant = Tenant::query()->create([
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+256 700 '.fake()->unique()->numerify('######'),
            'status' => $status === 'active' ? 'active' : 'pending',
        ]);

        if ($status === 'active') {
            $unit->update(['status' => 'occupied']);
        }

        return Lease::query()->create([
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'lease_number' => 'KR-TEST-'.str_pad((string) (Lease::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'start_date' => $startDate,
            'rent_amount' => $rentAmount,
            'deposit_amount' => $rentAmount,
            'due_day' => $dueDay,
            'grace_days' => 3,
            'billing_frequency' => 'monthly',
            'status' => $status,
            'signed_at' => $status === 'active' ? now() : null,
            'created_by' => $this->manager->id,
        ]);
    }
}
