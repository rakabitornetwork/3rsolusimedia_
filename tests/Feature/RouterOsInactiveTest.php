<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\MikrotikApiService;
use App\Services\PppoeSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouterOsInactiveTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function testing_an_inactive_router_fails_without_connecting(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router(['is_active' => false, 'last_status' => 'online']);

        $this->actingAs($admin)
            ->from('/admin/network/routeros')
            ->post('/admin/network/routeros/'.$router->id.'/test')
            ->assertRedirect('/admin/network/routeros')
            ->assertSessionHas('error');

        $router->refresh();

        $this->assertSame('offline', $router->last_status);
        $this->assertStringContainsString('tidak aktif', (string) $router->last_message);
        $this->assertStringNotContainsString('Koneksi berhasil', (string) $router->last_message);
    }

    #[Test]
    public function opening_an_inactive_router_does_not_sync_dashboard_data(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router(['is_active' => false]);

        $this->actingAs($admin)
            ->get('/admin/network/routeros/'.$router->id)
            ->assertRedirect(route('admin.network.routeros'))
            ->assertSessionHas('error');

        $router->refresh();
        $this->assertSame('offline', $router->last_status);
        $this->assertStringContainsString('tidak aktif', (string) $router->last_message);
    }

    #[Test]
    public function deactivating_a_router_shows_offline_instead_of_unchecked(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router(['is_active' => true, 'last_status' => null]);

        $this->actingAs($admin)
            ->put('/admin/network/routeros/'.$router->id, [
                'name' => $router->name,
                'host' => $router->host,
                'port' => $router->port,
                'username' => $router->username,
                'use_ssl' => 0,
                'is_active' => 0,
            ])
            ->assertRedirect(route('admin.network.routeros'));

        $router->refresh();

        $this->assertFalse($router->is_active);
        $this->assertSame('offline', $router->last_status);
        $this->assertSame('offline', $router->toSafeArray()['last_status']);
    }

    #[Test]
    public function api_test_connection_refuses_inactive_routers_before_socket(): void
    {
        $router = $this->router([
            'is_active' => false,
            'host' => '203.0.113.1',
        ]);

        $result = (new MikrotikApiService)->testConnection($router);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('tidak aktif', $result['message']);
        $this->assertArrayNotHasKey('info', $result);
    }

    #[Test]
    public function pppoe_sync_does_not_call_inactive_routeros(): void
    {
        $router = $this->router(['is_active' => false]);
        $package = SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => '10 Mbps',
            'price' => 150000,
            'mikrotik_profile' => '10Mbps',
            'is_active' => true,
        ]);
        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi',
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-01-20',
            'billing_day' => 20,
            'due_date' => '2026-10-20',
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')->never();
        $this->app->instance(MikrotikApiService::class, $api);

        $this->app->make(PppoeSyncService::class)->sync($customer);

        $customer->refresh();
        $this->assertSame('error', $customer->sync_status);
        $this->assertStringContainsString('tidak aktif', (string) $customer->sync_message);
    }

    #[Test]
    public function overdue_customer_is_marked_isolated_locally_when_router_is_inactive(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Jakarta'));

        $router = $this->router(['is_active' => false]);
        $package = SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => '10 Mbps',
            'price' => 150000,
            'mikrotik_profile' => '10Mbps',
            'is_active' => true,
        ]);
        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Wiwin',
            'username' => 'wiwin01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'isolir_profile' => 'ISOLIR',
            'start_date' => '2026-01-15',
            'billing_day' => 15,
            'due_date' => '2026-10-15',
            'overdue_action' => 'isolir',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')->never();
        $this->app->instance(MikrotikApiService::class, $api);

        $this->app->make(PppoeSyncService::class)->sync($customer);

        $customer->refresh();
        $this->assertSame('isolated', $customer->status);
        $this->assertSame('error', $customer->sync_status);
        $this->assertStringContainsString('tidak aktif', (string) $customer->sync_message);

        Carbon::setTestNow();
    }

    #[Test]
    public function failed_mikrotik_push_still_marks_overdue_customer_isolated_locally(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Jakarta'));

        $router = $this->router(['is_active' => true]);
        $package = SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => '10 Mbps',
            'price' => 150000,
            'mikrotik_profile' => '10Mbps',
            'is_active' => true,
        ]);
        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Wiwin',
            'username' => 'wiwin01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'isolir_profile' => 'ISOLIR',
            'start_date' => '2026-01-15',
            'billing_day' => 15,
            'due_date' => '2026-10-15',
            'overdue_action' => 'isolir',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')->once()->andReturn([
            'ok' => false,
            'message' => 'Koneksi RouterOS gagal.',
        ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->app->make(PppoeSyncService::class)->sync($customer);

        $customer->refresh();
        $this->assertSame('isolated', $customer->status);
        $this->assertSame('error', $customer->sync_status);
        $this->assertStringContainsString('gagal', (string) $customer->sync_message);

        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function router(array $overrides = []): MikrotikRouter
    {
        return MikrotikRouter::query()->create(array_merge([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ], $overrides));
    }
}
