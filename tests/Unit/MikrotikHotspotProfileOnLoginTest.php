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
            'validity' => '1h',
            'session_timeout' => '0s',
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
            'validity' => '1d',
        ]);

        $this->assertStringContainsString(
            ':if ($ucode = "vc" or $ucode = "up" or $ucode = "vo" or $comment = "") do={',
            $script
        );
    }

    #[Test]
    public function on_login_detects_routeros7_iso_date_format(): void
    {
        $script = $this->buildOnLogin([
            'expired_mode' => 'remove',
            'lock_user' => false,
            'validity' => '1h',
        ]);

        $this->assertStringContainsString(':if ([:pick $date 4 5] = "-") do={', $script);
        $this->assertStringContainsString('start-time=$time', $script);
        $this->assertStringContainsString(':set year [:pick $date 7 11];', $script);
    }

    #[Test]
    public function expire_monitor_script_supports_iso_and_classic_comments(): void
    {
        $script = (new MikrotikApiService)->buildHotspotExpireMonitorScript('1jam', 'remove');

        $this->assertStringContainsString('profile="1jam"', $script);
        $this->assertStringContainsString('/ip hotspot user remove', $script);
        $this->assertStringContainsString('dateintiso', $script);
        $this->assertStringContainsString('dateintcls', $script);
        $this->assertStringContainsString('[:pic $comment 4] = "-"', $script);
        $this->assertStringContainsString('[:pic $comment 3] = "/"', $script);
        $this->assertStringContainsString('limit != "00:00:01"', $script);
    }

    #[Test]
    public function expire_monitor_script_notices_users_for_notice_mode(): void
    {
        $script = (new MikrotikApiService)->buildHotspotExpireMonitorScript('Paket-1Jam', 'notice');

        $this->assertStringContainsString('profile="Paket-1Jam"', $script);
        $this->assertStringContainsString('set limit-uptime=1s', $script);
    }

    #[Test]
    public function expire_monitor_script_notices_users_for_ntfc_mode(): void
    {
        $script = (new MikrotikApiService)->buildHotspotExpireMonitorScript('Paket-1Jam', 'ntfc');

        $this->assertStringContainsString('set limit-uptime=1s', $script);
        $this->assertStringNotContainsString('/ip hotspot user remove', $script);
    }

    #[Test]
    public function parser_detects_lock_from_mac_address_script(): void
    {
        $parsed = $this->parseOnLogin(
            ':put (",rem,0,1h,0,,Enable,Disable,");'."\n".
            '[:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]];'
        );

        $this->assertSame('rem', $parsed['expired_mode']);
        $this->assertSame('1h', $parsed['validity']);
        $this->assertTrue($parsed['lock_user']);
    }

    #[Test]
    public function on_login_uses_validity_not_session_timeout(): void
    {
        $script = $this->buildOnLogin([
            'expired_mode' => 'remove',
            'validity' => '30d',
            'session_timeout' => '1h',
        ]);

        $this->assertStringContainsString('interval="30d"', $script);
        $this->assertStringNotContainsString('interval="1h"', $script);
        $this->assertStringContainsString(':put (",rem,0,30d,0,,Disable,Disable,");', $script);
    }

    #[Test]
    public function empty_or_zero_session_timeout_does_not_become_one_day_validity(): void
    {
        $script = $this->buildOnLogin([
            'expired_mode' => 'remove',
            'session_timeout' => '0s',
        ]);

        $this->assertStringNotContainsString('interval="1d"', $script);
        $this->assertStringNotContainsString('/sys sch add', $script);
        $this->assertStringContainsString(':put (",rem,0,0,0,,Disable,Disable,");', $script);
    }

    #[Test]
    public function parser_reads_validity_from_on_login_metadata(): void
    {
        $parsed = $this->parseOnLogin(':put (",ntf,5000,7d,7000,,Enable,");');

        $this->assertSame('ntf', $parsed['expired_mode']);
        $this->assertSame('7d', $parsed['validity']);
        $this->assertSame(5000, $parsed['price']);
        $this->assertSame(7000, $parsed['selling_price']);
        $this->assertTrue($parsed['lock_user']);
    }

    #[Test]
    public function remc_on_login_records_sale_to_system_script(): void
    {
        $script = $this->buildOnLogin([
            'name' => '1hari',
            'expired_mode' => 'remc',
            'validity' => '1d',
            'price' => 5000,
            'selling_price' => 7000,
        ]);

        $this->assertStringContainsString(':put (",remc,5000,1d,7000,,Disable,Disable,");', $script);
        $this->assertStringContainsString('/system script add', $script);
        $this->assertStringContainsString('comment=mikhmon', $script);
        $this->assertStringContainsString('|-5000-|', $script);
        $this->assertStringContainsString('|-1d-|-1hari-|', $script);
        $this->assertStringContainsString(':local mode "X";', $script);
    }

    #[Test]
    public function rem_on_login_does_not_write_sales_script(): void
    {
        $script = $this->buildOnLogin([
            'name' => '1hari',
            'expired_mode' => 'rem',
            'validity' => '1d',
            'price' => 5000,
        ]);

        $this->assertStringContainsString(':put (",rem,5000,1d,0,,Disable,Disable,");', $script);
        $this->assertStringNotContainsString('/system script add', $script);
    }

    #[Test]
    public function legacy_remove_notice_mode_maps_to_remc(): void
    {
        $script = $this->buildOnLogin([
            'expired_mode' => 'remove,notice',
            'validity' => '12h',
        ]);

        $this->assertStringContainsString(':put (",remc,0,12h,0,,Disable,Disable,");', $script);
        $this->assertStringContainsString('/system script add', $script);
    }
}
