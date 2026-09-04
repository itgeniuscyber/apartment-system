<?php

namespace Tests\Feature\Finance;

use App\Models\CashflowEntry;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PrivateFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_track_borrowed_and_issued_loans_and_payments(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->actingAs($superAdmin);

        $this->post(route('loans.store'), [
            'direction' => 'borrowed',
            'name' => 'Renovation facility',
            'counterparty_name' => 'Centenary Bank',
            'counterparty_phone' => null,
            'counterparty_email' => null,
            'tenant_id' => null,
            'principal_amount' => 10000000,
            'total_payable' => 12000000,
            'interest_rate' => 20,
            'installment_amount' => 1000000,
            'repayment_frequency' => 'monthly',
            'start_date' => '2026-09-01',
            'next_due_date' => '2026-10-01',
            'maturity_date' => '2027-09-01',
            'reminder_days_before' => 5,
            'status' => 'active',
            'notes' => 'Property improvements',
        ])->assertRedirect(route('loans.index'));

        $loan = Loan::query()->firstOrFail();
        $this->post(route('loans.payments.store', $loan), [
            'amount' => 1000000,
            'paid_on' => '2026-10-01',
            'payment_method' => 'bank_transfer',
            'reference' => 'TXN-1001',
            'next_due_date' => '2026-11-01',
            'notes' => null,
        ])->assertRedirect(route('loans.index'));

        $this->get(route('loans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('loans/Index')
                ->where('summary.borrowed_balance', 11000000)
                ->where('summary.paid_total', 1000000)
                ->where('loans.0.progress', 8)
                ->has('loans.0.payments', 1));

        $this->assertDatabaseHas('activity_logs', ['action' => 'loan.payment_recorded']);
    }

    public function test_admin_cashbook_is_scoped_to_own_entries_while_super_admin_sees_all(): void
    {
        $adminA = User::factory()->create([
            'role' => 'administrator',
            'permissions' => ['cashflow.manage'],
        ]);
        $adminB = User::factory()->create([
            'role' => 'administrator',
            'permissions' => ['cashflow.manage'],
        ]);
        $superAdmin = User::factory()->superAdmin()->create();

        $payload = [
            'type' => 'expense',
            'category' => 'Salaries & wages',
            'description' => 'Gate security salary',
            'amount' => 350000,
            'occurred_on' => now()->toDateString(),
            'estate_id' => null,
            'settlement_account_id' => null,
            'counterparty' => 'Security guard',
            'payment_method' => 'cash',
            'external_reference' => null,
            'is_recurring' => true,
            'recurrence' => 'monthly',
            'notes' => null,
        ];

        $this->actingAs($adminA)->post(route('cashflow.store'), $payload)
            ->assertRedirect(route('cashflow.index'));
        $this->actingAs($adminB)->post(route('cashflow.store'), [
            ...$payload,
            'description' => 'Rubbish collection',
            'amount' => 100000,
        ])->assertRedirect(route('cashflow.index'));

        $this->actingAs($adminA)->get(route('cashflow.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('isSuperAdmin', false)
                ->where('summary.expenses', 350000)
                ->where('summary.rent_collections', null)
                ->has('entries', 1));

        $this->actingAs($superAdmin)->get(route('cashflow.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('isSuperAdmin', true)
                ->where('summary.expenses', 450000)
                ->has('entries', 2));

        $entryFromB = CashflowEntry::query()->where('created_by', $adminB->id)->firstOrFail();
        $this->actingAs($adminA)->delete(route('cashflow.destroy', $entryFromB))->assertForbidden();
    }
}
