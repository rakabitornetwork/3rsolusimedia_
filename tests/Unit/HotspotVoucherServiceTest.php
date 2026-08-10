<?php

namespace Tests\Unit;

use App\Models\HotspotVoucher;
use App\Models\MikrotikRouter;
use App\Services\HotspotVoucherService;
use App\Services\MikrotikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HotspotVoucherServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(?MikrotikApiService $api = null): HotspotVoucherService
    {
        return new HotspotVoucherService($api ?? Mockery::mock(MikrotikApiService::class));
    }

    #[Test]
    public function it_parses_mikrotik_duration(): void
    {
        $service = $this->service();

        $this->assertSame(0, $service->parseMikrotikDuration('0s'));
        $this->assertSame(3661, $service->parseMikrotikDuration('1h1m1s'));
        $this->assertSame(86400, $service->parseMikrotikDuration('1d'));
        $this->assertSame(90061, $service->parseMikrotikDuration('1d1h1m1s'));
        $this->assertSame(3526, $service->parseMikrotikDuration('00:58:46'));
        $this->assertSame(965, $service->parseMikrotikDuration('16:05'));
    }

    #[Test]
    public function it_detects_exhausted_uptime_for_purge(): void
    {
        $service = $this->service();

        $this->assertTrue($service->shouldPurgeUser([
            'is_online' => false,
            'uptime' => '1h',
            'limit_uptime' => '1h',
            'bytes_in' => 0,
            'bytes_out' => 0,
        ]));

        $this->assertFalse($service->shouldPurgeUser([
            'is_online' => true,
            'uptime' => '1h',
            'limit_uptime' => '1h',
            'bytes_in' => 10,
            'bytes_out' => 10,
        ]));

        $this->assertFalse($service->shouldPurgeUser([
            'is_online' => false,
            'uptime' => '10m',
            'limit_uptime' => '1h',
            'bytes_in' => 100,
            'bytes_out' => 50,
        ]));
    }

    #[Test]
    public function it_purges_used_vouchers_without_limit_when_offline(): void
    {
        $service = $this->service();

        $this->assertTrue($service->shouldPurgeUser([
            'is_online' => false,
            'uptime' => '5m',
            'limit_uptime' => null,
            'limit_bytes_total' => null,
            'bytes_in' => 0,
            'bytes_out' => 0,
        ]));

        $this->assertFalse($service->shouldPurgeUser([
            'is_online' => false,
            'uptime' => '0s',
            'limit_uptime' => null,
            'bytes_in' => 0,
            'bytes_out' => 0,
        ]));
    }

    #[Test]
    public function it_normalizes_code_formats(): void
    {
        $service = $this->service();

        $this->assertSame('numbers', $service->normalizeFormat('numbers'));
        $this->assertSame('numbers', $service->normalizeFormat('numeric'));
        $this->assertSame('upper', $service->normalizeFormat('letters'));
        $this->assertSame('alt_numbers_upper', $service->normalizeFormat('alt_numbers_upper'));
        $this->assertSame('numbers', $service->normalizeFormat('unknown'));
    }

    #[Test]
    public function sync_sold_from_usage_marks_available_vouchers_as_used(): void
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router Sync',
            'host' => '10.0.0.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        HotspotVoucher::query()->create([
            'batch_id' => 'batch-1',
            'mikrotik_router_id' => $router->id,
            'username' => 'GS3N9H7',
            'password' => 'GS3N9H7',
            'profile' => '1jam',
            'status' => HotspotVoucher::STATUS_AVAILABLE,
            'base_price' => 3000,
            'commission' => 0,
            'sell_price' => 3000,
        ]);

        HotspotVoucher::query()->create([
            'batch_id' => 'batch-1',
            'mikrotik_router_id' => $router->id,
            'username' => 'UNUSED01',
            'password' => 'UNUSED01',
            'profile' => '1jam',
            'status' => HotspotVoucher::STATUS_AVAILABLE,
            'base_price' => 3000,
            'commission' => 0,
            'sell_price' => 3000,
        ]);

        $sold = $this->service()->syncSoldFromUsage($router, [
            [
                'name' => 'GS3N9H7',
                'uptime' => '00:58:46',
                'bytes_in' => 1000,
                'bytes_out' => 2000,
                'is_online' => true,
                'limit_uptime' => '1h',
            ],
            [
                'name' => 'UNUSED01',
                'uptime' => '0s',
                'bytes_in' => 0,
                'bytes_out' => 0,
                'is_online' => false,
            ],
        ]);

        $this->assertSame(1, $sold);
        $this->assertDatabaseHas('hotspot_vouchers', [
            'username' => 'GS3N9H7',
            'status' => HotspotVoucher::STATUS_USED,
        ]);
        $this->assertDatabaseHas('hotspot_vouchers', [
            'username' => 'UNUSED01',
            'status' => HotspotVoucher::STATUS_AVAILABLE,
        ]);
        $this->assertNotNull(
            HotspotVoucher::query()->where('username', 'GS3N9H7')->value('used_at')
        );
    }

    #[Test]
    public function purge_used_syncs_sales_without_removing_online_vouchers(): void
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router Purge',
            'host' => '10.0.0.2',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        HotspotVoucher::query()->create([
            'batch_id' => 'batch-2',
            'mikrotik_router_id' => $router->id,
            'username' => 'GS6G6S5',
            'password' => 'GS6G6S5',
            'profile' => '1jam',
            'status' => HotspotVoucher::STATUS_AVAILABLE,
            'base_price' => 2000,
            'commission' => 500,
            'sell_price' => 2500,
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('listHotspotUsers')->once()->andReturn([
            'ok' => true,
            'users' => [
                [
                    'id' => '*1',
                    'name' => 'GS6G6S5',
                    'uptime' => '00:16:05',
                    'bytes_in' => 2014,
                    'bytes_out' => 5500,
                    'is_online' => true,
                    'limit_uptime' => '1h',
                ],
            ],
            'active_count' => 1,
        ]);
        $api->shouldNotReceive('removeHotspotUsers');

        $result = $this->service($api)->purgeUsed($router);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['removed']);
        $this->assertSame(1, $result['sold']);
        $this->assertDatabaseHas('hotspot_vouchers', [
            'username' => 'GS6G6S5',
            'status' => HotspotVoucher::STATUS_USED,
        ]);
        $this->assertNull(
            HotspotVoucher::query()->where('username', 'GS6G6S5')->value('deleted_from_router_at')
        );
    }
}
