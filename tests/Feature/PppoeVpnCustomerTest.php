<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\BillingService;
use App\Services\MikrotikApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PppoeVpnCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

        $response = $this->actingAs($admin)
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
            ->assertRedirect();

        $customer = PppoeCustomer::query()->where('username', 'vpnuser')->first();
        $this->assertNotNull($customer);
        $response->assertRedirect('/admin/customers/pppoe/'.$customer->id.'/edit');
        $this->assertNull($customer->vpn_remote_address);
        $this->assertSame(0, $customer->vpnRouters()->count());
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
    public function expired_vpn_disables_the_routeros_secret_until_payment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 00:05:00', 'Asia/Jakarta'));
        SiteSetting::setValue('app_auto_isolir', '1');

        [$admin, $router, $package] = $this->records();
        unset($admin);
        $customer = $this->customer($router, $package, 'Siti', 'vpnuser', PppoeCustomer::SERVICE_L2TP, [
            'due_date' => '2026-10-01',
            'billing_day' => 1,
            'overdue_action' => 'isolir',
            'status' => 'active',
        ]);

        $api = $this->mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->once()
            ->withArgs(function (...$args) {
                return ($args[1] ?? null) === 'vpnuser'
                    && ($args[3] ?? null) === '10Mbps'
                    && ($args[5] ?? null) === true
                    && ($args[6] ?? null) === true
                    && ($args[8] ?? null) === PppoeCustomer::SERVICE_L2TP;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret VPN L2TP dinonaktifkan di RouterOS.',
            ]);

        $this->artisan('pppoe:sync-overdue')
            ->expectsOutputToContain('Secret VPN L2TP dinonaktifkan')
            ->assertSuccessful();

        $customer->refresh();
        $this->assertSame('isolated', $customer->status);
        $this->assertSame('10Mbps', $customer->service_profile);

        $invoice = Invoice::query()->create([
            'number' => 'INV/2026/10/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-09-01',
            'period_end' => '2026-10-01',
            'due_date' => '2026-10-01',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $api->shouldReceive('upsertPppSecret')
            ->once()
            ->withArgs(function (...$args) {
                return ($args[1] ?? null) === 'vpnuser'
                    && ($args[3] ?? null) === '10Mbps'
                    && ($args[5] ?? null) === false
                    && ($args[8] ?? null) === PppoeCustomer::SERVICE_L2TP;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret VPN L2TP berhasil diperbarui di RouterOS.',
            ]);

        app(BillingService::class)->markPaid($invoice, 'cash');

        $customer->refresh();
        $this->assertSame('active', $customer->status);
        $this->assertSame('2026-11-01', $customer->due_date?->toDateString());
        $this->assertFalse($customer->shouldIsolir());
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(
        MikrotikRouter $router,
        SubscriptionPackage $package,
        string $name,
        string $username,
        string $service,
        array $overrides = [],
    ): PppoeCustomer {
        return PppoeCustomer::query()->create(array_merge([
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
        ], $overrides));
    }
}
