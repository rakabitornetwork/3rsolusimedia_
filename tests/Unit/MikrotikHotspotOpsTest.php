<?php

namespace Tests\Unit;

use App\Services\MikrotikApiService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MikrotikHotspotOpsTest extends TestCase
{
    #[Test]
    public function it_detects_notice_expired_limit_uptime(): void
    {
        $this->assertTrue(MikrotikApiService::isHotspotLimitExpired('1s'));
        $this->assertTrue(MikrotikApiService::isHotspotLimitExpired('00:00:01'));
        $this->assertFalse(MikrotikApiService::isHotspotLimitExpired('0'));
        $this->assertFalse(MikrotikApiService::isHotspotLimitExpired('0s'));
        $this->assertFalse(MikrotikApiService::isHotspotLimitExpired('1d'));
        $this->assertFalse(MikrotikApiService::isHotspotLimitExpired(null));
    }

    #[Test]
    public function it_prefixes_user_comment_like_mikhmon(): void
    {
        $this->assertSame('vc-note', MikrotikApiService::prefixHotspotUserComment('andi', 'andi', 'note'));
        $this->assertSame('up-note', MikrotikApiService::prefixHotspotUserComment('andi', 'secret', 'note'));
        $this->assertSame('vc-already', MikrotikApiService::prefixHotspotUserComment('andi', 'andi', 'vc-already'));
        $this->assertSame('up-keep', MikrotikApiService::prefixHotspotUserComment('andi', 'andi', 'up-keep'));
        $this->assertSame('vc-', MikrotikApiService::prefixHotspotUserComment('andi', 'andi', ''));
    }

    #[Test]
    public function it_parses_hotspot_login_log_messages(): void
    {
        $login = MikrotikApiService::parseHotspotLogMessage('->:user1:trying to login');
        $this->assertTrue($login['login_event']);
        $this->assertSame('user1', $login['user']);
        $this->assertSame('login', $login['detail']);

        $mac = MikrotikApiService::parseHotspotLogMessage('->:AA:BB:CC:DD:EE:FF:trying to login by http');
        $this->assertTrue($mac['login_event']);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $mac['user']);
        $this->assertSame('login by http', $mac['detail']);

        $other = MikrotikApiService::parseHotspotLogMessage('dhcp assigned');
        $this->assertFalse($other['login_event']);
        $this->assertNull($other['user']);
    }
}
