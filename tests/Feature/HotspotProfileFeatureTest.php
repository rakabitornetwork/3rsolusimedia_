<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\MikrotikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HotspotProfileFeatureTest extends TestCase
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
    public function storing_profile_with_expire_mode_requires_validity(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router();

        $this->actingAs($admin)
            ->from('/admin/network/hotspot/profiles/create')
            ->post('/admin/network/hotspot/profiles', [
                'router_id' => $router->id,
                'name' => '1hari',
                'expired_mode' => 'remove',
                'shared_users' => 1,
            ])
            ->assertRedirect('/admin/network/hotspot/profiles/create')
            ->assertSessionHasErrors('validity');
    }

    #[Test]
    public function storing_profile_passes_validity_separate_from_session_timeout(): void
    {
        $admin = User::factory()->superadmin()->create();
        $router = $this->router();

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('createHotspotUserProfile')
            ->once()
            ->withArgs(function (MikrotikRouter $passedRouter, array $data) use ($router) {
                return $passedRouter->is($router)
                    && ($data['name'] ?? null) === '30hari'
                    && ($data['expired_mode'] ?? null) === 'remove'
                    && ($data['validity'] ?? null) === '30d'
                    && ($data['session_timeout'] ?? null) === null;
            })
            ->andReturn(['ok' => true, 'message' => 'ok']);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->actingAs($admin)
            ->post('/admin/network/hotspot/profiles', [
                'router_id' => $router->id,
                'name' => '30hari',
                'expired_mode' => 'remove',
                'validity' => '30d',
                'session_timeout' => '',
                'shared_users' => 1,
            ])
            ->assertRedirect();
    }
}
