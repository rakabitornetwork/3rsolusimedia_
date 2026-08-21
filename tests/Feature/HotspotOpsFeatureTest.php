<?php

namespace Tests\Feature;

use App\Models\HotspotVoucher;
use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\MikrotikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HotspotOpsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function router(): MikrotikRouter
    {
        return MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function tools_page_lists_hosts_from_api(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router();

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('listHotspotHosts')->once()->andReturn([
            'ok' => true,
            'hosts' => [
                [
                    'id' => '*1',
                    'mac_address' => 'AA:BB:CC:DD:EE:FF',
                    'address' => '10.10.10.2',
                    'to_address' => '10.10.10.2',
                    'server' => 'hotspot1',
                    'comment' => null,
                    'authorized' => true,
                    'bypassed' => false,
                    'dhcp' => true,
                    'dynamic' => false,
                    'flags' => 'A H',
                ],
            ],
        ]);
        $api->shouldReceive('listHotspotServers')->andReturn(['ok' => true, 'servers' => []]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->actingAs($admin)
            ->get('/admin/network/hotspot/tools?router_id='.$router->id)
            ->assertOk();
    }

    #[Test]
    public function adding_a_named_user_creates_router_user_and_local_row(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router();

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('createHotspotUsers')
            ->once()
            ->withArgs(function (MikrotikRouter $passed, array $users) use ($router) {
                return $passed->is($router)
                    && ($users[0]['name'] ?? null) === 'budi'
                    && ($users[0]['password'] ?? null) === 'budi'
                    && ($users[0]['profile'] ?? null) === '1jam'
                    && ($users[0]['comment'] ?? null) === 'vc-walkin';
            })
            ->andReturn([
                'ok' => true,
                'created' => [['name' => 'budi', 'password' => 'budi']],
                'errors' => [],
                'message' => 'ok',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->actingAs($admin)
            ->post('/admin/network/hotspot/users', [
                'router_id' => $router->id,
                'name' => 'budi',
                'password' => 'budi',
                'profile' => '1jam',
                'comment' => 'walkin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hotspot_vouchers', [
            'username' => 'budi',
            'password' => 'budi',
            'profile' => '1jam',
            'comment' => 'vc-walkin',
            'status' => HotspotVoucher::STATUS_AVAILABLE,
        ]);
    }

    #[Test]
    public function reset_expired_user_calls_api_and_restores_local_status(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router();

        HotspotVoucher::query()->create([
            'batch_id' => '11111111-1111-1111-1111-111111111111',
            'mikrotik_router_id' => $router->id,
            'username' => 'vc001',
            'password' => 'vc001',
            'profile' => '1jam',
            'status' => HotspotVoucher::STATUS_USED,
            'used_at' => now(),
            'comment' => 'expired',
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('resetHotspotUser')
            ->once()
            ->withArgs(fn (MikrotikRouter $passed, string $id) => $passed->is($router) && $id === '*3')
            ->andReturn([
                'ok' => true,
                'message' => 'reset',
                'username' => 'vc001',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->actingAs($admin)
            ->post('/admin/network/hotspot/'.$router->id.'/'.rawurlencode('*3').'/reset')
            ->assertRedirect();

        $this->assertDatabaseHas('hotspot_vouchers', [
            'username' => 'vc001',
            'status' => HotspotVoucher::STATUS_AVAILABLE,
        ]);
        $this->assertDatabaseMissing('hotspot_vouchers', [
            'username' => 'vc001',
            'status' => HotspotVoucher::STATUS_USED,
        ]);
    }

    #[Test]
    public function adding_user_requires_username_and_profile(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router();

        $this->actingAs($admin)
            ->from('/admin/network/hotspot/users/create')
            ->post('/admin/network/hotspot/users', [
                'router_id' => $router->id,
            ])
            ->assertRedirect('/admin/network/hotspot/users/create')
            ->assertSessionHasErrors(['name', 'profile']);
    }
}
