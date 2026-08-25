<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $this->seed(PortfolioSeeder::class);
        $user = User::factory()->management()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('portfolio.unit_count', 66)
            ->where('portfolio.monthly_rent', 36950000)
            ->has('estatePerformance', 4)
            ->has('settlementAccounts', 2));
    }

    public function test_tenant_users_receive_the_resident_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/Dashboard')
                ->where('tenant', null)
                ->where('lease', null));
    }
}
