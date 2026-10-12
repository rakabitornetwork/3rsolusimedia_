<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PppoeCustomerAgentFilterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_list_can_be_filtered_by_agent(): void
    {
        $admin = User::factory()->superadmin()->create();
        $sari = User::factory()->agen()->create(['name' => 'Agen Sari']);
        $budi = User::factory()->agen()->create(['name' => 'Agen Budi']);
        $router = $this->router();

        $owned = $this->customer($router, [
            'name' => 'Pelanggan Sari',
            'username' => 'sari-01',
            'agent_id' => $sari->id,
        ]);
        $this->customer($router, [
            'name' => 'Pelanggan Budi',
            'username' => 'budi-01',
            'agent_id' => $budi->id,
        ]);
        $unassigned = $this->customer($router, [
            'name' => 'Tanpa Agen',
            'username' => 'bebas-01',
        ]);

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe?agent_id='.$sari->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Pppoe/Index', false)
                ->where('filters.agent_id', (string) $sari->id)
                ->has('customers', 1)
                ->where('customers.0.id', $owned->id)
                ->where('customers.0.agent_name', 'Agen Sari')
                ->where('stats.total', 1)
                ->has('agents', 2)
            );

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe?agent_id=none')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.agent_id', 'none')
                ->has('customers', 1)
                ->where('customers.0.id', $unassigned->id)
                ->where('customers.0.agent_name', null)
                ->where('stats.total', 1)
            );

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe/print?date=2026-10-20&agent_id='.$sari->id)
            ->assertOk()
            ->assertSee('Pelanggan Sari')
            ->assertSee('Agen: Agen Sari')
            ->assertDontSee('Pelanggan Budi');
    }

    #[Test]
    public function agent_account_stays_limited_to_its_own_customers(): void
    {
        $sari = User::factory()->agen()->create(['name' => 'Agen Sari']);
        $budi = User::factory()->agen()->create(['name' => 'Agen Budi']);
        $router = $this->router();

        $this->customer($router, [
            'name' => 'Milik Sari',
            'username' => 'sari-vpn',
            'agent_id' => $sari->id,
            'ppp_service' => PppoeCustomer::SERVICE_L2TP,
        ]);
        $this->customer($router, [
            'name' => 'Milik Budi',
            'username' => 'budi-vpn',
            'agent_id' => $budi->id,
            'ppp_service' => PppoeCustomer::SERVICE_L2TP,
        ]);

        $this->actingAs($sari)
            ->get('/admin/customers/vpn?agent_id='.$budi->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Pppoe/Index', false)
                ->has('customers', 1)
                ->where('customers.0.name', 'Milik Sari')
                ->where('filters.agent_id', '')
                ->has('agents', 0)
            );
    }

    #[Test]
    public function user_list_links_agents_to_their_customer_count(): void
    {
        $admin = User::factory()->superadmin()->create();
        $agent = User::factory()->agen()->create(['name' => 'Agen Sari']);
        $router = $this->router();
        $this->customer($router, [
            'name' => 'Pelanggan Sari',
            'username' => 'sari-01',
            'agent_id' => $agent->id,
        ]);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Index', false)
                ->where('users', function ($users) use ($agent) {
                    $row = collect($users)->firstWhere('id', $agent->id);

                    return $row && $row['customer_count'] === 1;
                })
            );
    }

    private function router(): MikrotikRouter
    {
        return MikrotikRouter::query()->create([
            'name' => 'Router A',
            'host' => '192.168.10.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(MikrotikRouter $router, array $overrides = []): PppoeCustomer
    {
        return PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'name' => 'Pelanggan',
            'phone' => '081234567890',
            'username' => 'user01',
            'password' => 'secret',
            'due_date' => '2026-10-20',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }
}
