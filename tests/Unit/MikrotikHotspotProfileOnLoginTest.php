<?php

namespace Tests\Unit;

use App\Services\MikrotikApiService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class MikrotikHotspotProfileOnLoginTest extends TestCase
{
    private function buildOnLogin(array $data): string
    {
        $method = new ReflectionMethod(MikrotikApiService::class, 'buildHotspotProfileOnLogin');
        $method->setAccessible(true);

        return (string) $method->invoke(new MikrotikApiService, $data);
    }

    private function parseOnLogin(string $onLogin): array
    {
        $method = new ReflectionMethod(MikrotikApiService::class, 'parseHotspotProfileOnLogin');
        $method->setAccessible(true);

        return $method->invoke(new MikrotikApiService, $onLogin);
    }

    #[Test]
    public function lock_user_script_uses_mikhmon_mac_address_syntax(): void
    {
        $script = $this->buildOnLogin([
            'expired_mode' => 'remove',
            'lock_user' => true,
            'session_timeout' => '1h',
        ]);

        $this->assertStringContainsString('$"mac-address"', $script);
        $this->assertStringNotContainsString('"$mac-address"', $script);
        $this->assertStringContainsString('set mac-address=$mac', $script);
    }

    #[Test]
    public function expire_gate_accepts_voucher_app_and_vc_prefixes(): void
    {
        $script = $this->buildOnLogin([
            'expired_mode' => 'remove',
            'lock_user' => false,
            'session_timeout' => '1d',
        ]);

        $this->assertStringContainsString(
            ':if ($ucode = "vc" or $ucode = "up" or $ucode = "vo" or $comment = "") do={',
            $script
        );
    }

    #[Test]
    public function parser_detects_lock_from_mac_address_script(): void
    {
        $parsed = $this->parseOnLogin(
            ':put (",rem,0,1h,0,,Enable,Disable,");'."\n".
            '[:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]];'
        );

        $this->assertSame('remove', $parsed['expired_mode']);
        $this->assertTrue($parsed['lock_user']);
    }

    #[Test]
    public function expire_monitor_script_removes_users_for_remove_mode(): void
    {
        $script = (new MikrotikApiService)->buildHotspotExpireMonitorScript('1jam', 'remove');

        $this->assertStringContainsString('profile="1jam"', $script);
        $this->assertStringContainsString('/ip hotspot user remove', $script);
        $this->assertStringContainsString('/ip hotspot active remove', $script);
        $this->assertStringNotContainsString('limit-uptime=1s', $script);
    }

    #[Test]
    public function expire_monitor_script_notices_users_for_notice_mode(): void
    {
        $script = (new MikrotikApiService)->buildHotspotExpireMonitorScript('Paket-1Jam', 'notice');

        $this->assertStringContainsString('profile="Paket-1Jam"', $script);
        $this->assertStringContainsString('set limit-uptime=1s', $script);
    }
}
