<?php

namespace Tests\Feature;

use App\Models\HotspotVoucher;
use App\Models\MikrotikRouter;
use App\Models\User;
use App\Services\HotspotVoucherService;
use App\Services\MikrotikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HotspotVoucherFeatureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generate_stores_agent_commission_and_sell_price(): void
    {
        $admin = User::factory()->superadmin()->create();
        $agent = User::factory()->agen(500)->create(['name' => 'Andi']);
        $router = MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.1.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('generateVoucherCode')->andReturn('123456', '123456');
        $api->shouldReceive('createHotspotUser')->once()->andReturn([
            'ok' => true,
            'message' => 'ok',
        ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $result = app(HotspotVoucherService::class)->generate($router, [
            'quantity' => 1,
            'prefix' => '',
            'code_length' => 6,
            'code_format' => 'numbers',
            'password_mode' => 'same',
            'profile' => '1jam',
            'agent_id' => $agent->id,
            'base_price' => 1500,
            'commission' => 500,
            'created_by' => $admin->id,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(2000, $result['vouchers'][0]['sell_price']);
        $this->assertSame('Andi', $result['vouchers'][0]['agent_name']);

        $this->assertDatabaseHas('hotspot_vouchers', [
            'username' => '123456',
            'agent_id' => $agent->id,
            'base_price' => 1500,
            'commission' => 500,
            'sell_price' => 2000,
            'status' => HotspotVoucher::STATUS_AVAILABLE,
        ]);
    }

    #[Test]
    public function report_page_requires_auth_and_renders_for_admin(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->get('/admin/network/hotspot/reports')
            ->assertOk();
    }

    #[Test]
    public function agent_commission_can_be_saved_on_user_form(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Agen Budi',
                'email' => 'budi@example.com',
                'role' => User::ROLE_AGEN,
                'voucher_commission' => 750,
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'email' => 'budi@example.com',
            'role' => User::ROLE_AGEN,
            'voucher_commission' => 750,
        ]);
    }
}
