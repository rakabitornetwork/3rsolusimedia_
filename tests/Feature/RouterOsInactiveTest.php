<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\MikrotikApiService;
use App\Services\PppoeSyncService;
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

        $this->assertSame('inactive', $router->last_status);
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
        $this->assertSame('inactive', $router->last_status);
        $this->assertStringContainsString('tidak aktif', (string) $router->last_message);
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
