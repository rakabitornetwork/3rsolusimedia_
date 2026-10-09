<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GitUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentHiddenPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GitUpdateService::class, function ($mock) {
            $mock->shouldReceive('dashboardNotice')->andReturn(null);
        });
    }

    #[Test]
    public function agent_cannot_open_pppoe_customers_or_commission_pages(): void
    {
        $agent = User::factory()->agen()->create();

        $this->actingAs($agent)
            ->get('/admin/customers/pppoe')
            ->assertRedirect('/admin')
            ->assertSessionHas('error');

        $this->actingAs($agent)
            ->get('/admin/billing/agent-commissions')
            ->assertRedirect('/admin')
            ->assertSessionHas('error');
    }

    #[Test]
    public function agent_dashboard_omits_hidden_cards(): void
    {
        $agent = User::factory()->agen()->create();

        $this->actingAs($agent)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard', false)
                ->where('new_customers', null)
                ->where('revenue_charts', null)
                ->where('quick_actions', [])
            );
    }

    #[Test]
    public function admin_still_sees_pppoe_customers_commission_and_dashboard_cards(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Customers/Pppoe/Index', false));

        $this->actingAs($admin)
            ->get('/admin/billing/agent-commissions')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Billing/AgentCommissions/Index', false));

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard', false)
                ->has('new_customers.charts.daily')
                ->has('revenue_charts.daily')
                ->has('quick_actions', 5)
            );
    }
}
