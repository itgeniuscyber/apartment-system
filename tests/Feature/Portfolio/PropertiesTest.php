<?php

namespace Tests\Feature\Portfolio;

use App\Models\Estate;
use App\Models\Section;
use App\Models\SettlementAccount;
use App\Models\Unit;
use App\Models\UnitRentRate;
use App\Models\User;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PropertiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PortfolioSeeder::class);
    }

    public function test_portfolio_seed_matches_the_confirmed_starting_figures(): void
    {
        $this->assertDatabaseCount('estates', 4);
        $this->assertDatabaseCount('sections', 7);
        $this->assertDatabaseCount('units', 66);
        $this->assertDatabaseCount('unit_rent_rates', 66);
        $this->assertSame(36950000, Unit::query()->sum('rent_amount'));
        $this->assertNull(SettlementAccount::query()->firstOrFail()->account_number);
    }

    public function test_authenticated_user_can_view_the_database_backed_portfolio(): void
    {
        $this->actingAs(User::factory()->management()->create());

        $this->get(route('properties.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('properties/Index')
                ->where('summary.estate_count', 4)
                ->where('summary.section_count', 7)
                ->where('summary.unit_count', 66)
                ->where('summary.monthly_rent', 36950000)
                ->has('estates', 4)
                ->has('settlementAccounts', 2));
    }

    public function test_an_estate_can_be_added_and_routed_to_a_settlement_account(): void
    {
        $this->actingAs(User::factory()->management()->create());
        $account = SettlementAccount::query()->firstOrFail();

        $this->post(route('properties.store'), [
            'name' => 'Ntinda Heights',
            'code' => 'nti',
            'location' => 'Ntinda',
            'address' => 'Bukoto–Ntinda Road',
            'settlement_account_id' => $account->id,
            'color' => '#7457d9',
        ])->assertRedirect(route('properties.index'));

        $this->assertDatabaseHas('estates', [
            'name' => 'Ntinda Heights',
            'code' => 'NTI',
            'settlement_account_id' => $account->id,
        ]);
    }

    public function test_a_flexible_section_creates_units_and_initial_rent_history_atomically(): void
    {
        $user = User::factory()->management()->create();
        $this->actingAs($user);
        $estate = Estate::query()->where('code', 'LUB')->firstOrFail();

        $this->post(route('properties.sections.store'), [
            'estate_id' => $estate->id,
            'name' => 'Garden Wing',
            'code' => 'garden',
            'unit_count' => 3,
            'unit_prefix' => 'lub-gdn',
            'start_number' => 5,
            'default_rent' => 250000,
        ])->assertRedirect(route('properties.index'));

        $section = Section::query()->where('estate_id', $estate->id)->where('code', 'GARDEN')->firstOrFail();

        $this->assertSame(3, $section->units()->count());
        $this->assertDatabaseHas('units', [
            'section_id' => $section->id,
            'code' => 'LUB-GDN-05',
            'rent_amount' => 250000,
            'status' => 'setup_required',
        ]);
        $this->assertSame(69, Unit::query()->count());
        $this->assertSame(69, UnitRentRate::query()->count());
        $this->assertDatabaseHas('unit_rent_rates', [
            'amount' => 250000,
            'source' => 'admin',
            'created_by' => $user->id,
        ]);
    }

    public function test_overlapping_unit_codes_are_rejected_without_creating_the_section(): void
    {
        $this->actingAs(User::factory()->management()->create());
        $estate = Estate::query()->where('code', 'KIS')->firstOrFail();

        $this->from(route('properties.index'))->post(route('properties.sections.store'), [
            'estate_id' => $estate->id,
            'name' => 'Duplicate Wing',
            'code' => 'DUP',
            'unit_count' => 2,
            'unit_prefix' => 'KIS-UP',
            'start_number' => 1,
            'default_rent' => 700000,
        ])->assertRedirect(route('properties.index'))->assertSessionHasErrors('unit_prefix');

        $this->assertDatabaseMissing('sections', [
            'estate_id' => $estate->id,
            'code' => 'DUP',
        ]);
        $this->assertSame(66, Unit::query()->count());
    }
}
