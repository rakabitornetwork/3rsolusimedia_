<?php

namespace Tests\Unit;

use App\Services\HotspotVoucherService;
use App\Services\MikrotikApiService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HotspotVoucherServiceTest extends TestCase
{
    private function service(): HotspotVoucherService
    {
        return new HotspotVoucherService(Mockery::mock(MikrotikApiService::class));
    }

    #[Test]
    public function it_parses_mikrotik_duration(): void
    {
        $service = $this->service();

        $this->assertSame(0, $service->parseMikrotikDuration('0s'));
        $this->assertSame(3661, $service->parseMikrotikDuration('1h1m1s'));
        $this->assertSame(86400, $service->parseMikrotikDuration('1d'));
        $this->assertSame(90061, $service->parseMikrotikDuration('1d1h1m1s'));
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
}
