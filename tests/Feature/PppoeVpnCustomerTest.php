<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\MikrotikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PppoeVpnCustomerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function vpn_customer_keeps_one_username_and_syncs_l2tp_service(): void
    {
        [$admin, $router, $package] = $this->records();

        $api = $this->mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->once()
            ->withArgs(function (...$args) {
                return ($args[1] ?? null) === 'vpnuser'
                    && ($args[8] ?? null) === PppoeCustomer::SERVICE_L2TP;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret VPN L2TP berhasil ditambahkan di RouterOS.',
            ]);

        $this->actingAs($admin)
            ->post('/admin/customers/pppoe', [
                'mikrotik_router_id' => $router->id,
                'subscription_package_id' => $package->id,
                'name' => 'Siti VPN',
                'username' => 'vpnuser',
                'password' => 'secret',
                'ppp_service' => 'l2tp',
                'service_profile' => '10Mbps',
                'start_date' => '2026-10-01',
                'due_date' => '2026-11-01',
                'billing_day' => 1,
                'overdue_action' => 'bypass',
                'is_active' => 1,
            ])
            ->assertRedirect('/admin/customers/pppoe');

        $customer = PppoeCustomer::query()->where('username', 'vpnuser')->first();
        $this->assertNotNull($customer);
        $this->assertSame(PppoeCustomer::SERVICE_L2TP, $customer->ppp_service);
        $this->assertSame('VPN L2TP', $customer->pppServiceLabel());
    }

    #[Test]
    public function customer_list_can_show_only_vpn_customers(): void
    {
        [$admin, $router, $package] = $this->records();
        $this->customer($router, $package, 'Budi', 'budi01', PppoeCustomer::SERVICE_PPPOE);
        $this->customer($router, $package, 'Siti', 'vpnuser', PppoeCustomer::SERVICE_L2TP);

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe?service=l2tp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Pppoe/Index', false)
                ->has('customers', 1)
                ->where('customers.0.username', 'vpnuser')
                ->where('customers.0.ppp_service', 'l2tp')
                ->where('customers.0.ppp_service_label', 'VPN L2TP')
                ->where('filters.service', 'l2tp')
            );
    }

    #[Test]
    public function omitted_service_stays_pppoe(): void
    {
        $this->assertSame(PppoeCustomer::SERVICE_PPPOE, PppoeCustomer::normalizePppService(null));
        $this->assertSame(PppoeCustomer::SERVICE_PPPOE, PppoeCustomer::normalizePppService('any'));
        $this->assertSame(PppoeCustomer::SERVICE_L2TP, PppoeCustomer::normalizePppService('L2TP'));
    }

    /**
     * @return array{0: User, 1: MikrotikRouter, 2: SubscriptionPackage}
     */
    private function records(): array
    {
        $admin = User::factory()->superadmin()->create();
        $router = MikrotikRouter::query()->create([
            'name' => 'Router VPN',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $package = SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => '10 Mbps',
            'price' => 150000,
            'mikrotik_profile' => '10Mbps',
            'is_active' => true,
        ]);

        return [$admin, $router, $package];
    }

    private function customer(
        MikrotikRouter $router,
        SubscriptionPackage $package,
        string $name,
        string $username,
        string $service,
    ): PppoeCustomer {
        return PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => $name,
            'username' => $username,
            'password' => 'secret',
            'ppp_service' => $service,
            'service_profile' => '10Mbps',
            'start_date' => '2026-10-01',
            'billing_day' => 1,
            'due_date' => '2026-11-01',
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);
    }
}
