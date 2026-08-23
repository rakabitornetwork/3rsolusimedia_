<?php

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Services\MikrotikApiService;
use App\Services\PppoeSessionMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PppoeSessionMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Carbon::setTestNow('2026-08-23 13:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function enableWatch(int $debounce = 3): void
    {
        SiteSetting::setMany([
            'telegram_enabled' => '1',
            'telegram_bot_token' => '123456:TESTTOKEN',
            'telegram_admin_chat_id' => '99',
            'messaging_notify_pppoe_session' => '1',
            'messaging_pppoe_session_debounce' => (string) $debounce,
        ]);
    }

    private function fakeTelegram(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ], 200),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     */
    private function mockSessions(array $sessions, int $times = 1): MikrotikRouter
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('listPppActiveSessions')
            ->times($times)
            ->andReturn(['ok' => true, 'sessions' => $sessions]);
        $this->app->instance(MikrotikApiService::class, $api);

        return $router;
    }

    /**
     * @param  list<list<array<string, mixed>>>  $waves
     */
    private function mockSessionWaves(array $waves): MikrotikRouter
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('listPppActiveSessions')->andReturnUsing(function () use (&$waves) {
            $next = array_shift($waves) ?? [];

            return ['ok' => true, 'sessions' => $next];
        });
        $this->app->instance(MikrotikApiService::class, $api);

        return $router;
    }

    private function customer(MikrotikRouter $router, array $overrides = []): PppoeCustomer
    {
        return PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'username' => 'budi01',
            'password' => 'secret',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    private function pppSession(string $name, string $address = '10.10.10.5'): array
    {
        return [
            'id' => '*1',
            'name' => $name,
            'address' => $address,
            'caller_id' => 'AA:BB:CC:DD:EE:FF',
            'uptime' => '12m',
        ];
    }

    #[Test]
    public function first_snapshot_does_not_notify(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();
        $router = $this->mockSessions([$this->pppSession('budi01')]);
        $this->customer($router);

        $summary = app(PppoeSessionMonitor::class)->run();

        $this->assertSame(1, $summary['first_snapshots']);
        $this->assertSame(0, $summary['connected']);
        $this->assertSame(0, $summary['sent']);
        $this->assertDatabaseCount('message_logs', 0);
    }

    #[Test]
    public function new_session_notifies_admin_with_customer_details(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();
        $router = $this->mockSessionWaves([
            [],
            [$this->pppSession('budi01')],
        ]);
        $this->customer($router);

        app(PppoeSessionMonitor::class)->run();
        $summary = app(PppoeSessionMonitor::class)->run();

        $this->assertSame(1, $summary['connected']);
        $this->assertSame('up', $summary['events'][0]['type'] ?? null);
        $this->assertSame('budi01', $summary['events'][0]['username'] ?? null);

        $body = MessageLog::query()->value('body');
        $this->assertStringContainsString('PPPoE connected', (string) $body);
        $this->assertStringContainsString('Budi Santoso', (string) $body);
        $this->assertStringContainsString('budi01', (string) $body);
        $this->assertStringContainsString('10.10.10.5', (string) $body);
        $this->assertDatabaseHas('message_logs', [
            'channel' => 'telegram',
            'external_id' => '99',
            'command' => 'pppoe_up',
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function disconnect_waits_for_debounce_then_notifies(): void
    {
        $this->enableWatch(3);
        $this->fakeTelegram();
        $router = $this->mockSessionWaves([
            [$this->pppSession('budi01')],
            [],
            [],
        ]);
        $this->customer($router);

        app(PppoeSessionMonitor::class)->run();
        $early = app(PppoeSessionMonitor::class)->run();
        $this->assertSame(0, $early['disconnected']);
        $this->assertDatabaseCount('message_logs', 0);

        Carbon::setTestNow(now()->addMinutes(3));
        $late = app(PppoeSessionMonitor::class)->run();

        $this->assertSame(1, $late['disconnected']);
        $this->assertSame('down', $late['events'][0]['type'] ?? null);
        $this->assertDatabaseHas('message_logs', [
            'command' => 'pppoe_down',
            'status' => 'sent',
        ]);
    }

    #[Test]
    public function flap_within_debounce_notifies_neither(): void
    {
        $this->enableWatch(3);
        $this->fakeTelegram();
        $router = $this->mockSessionWaves([
            [$this->pppSession('budi01')],
            [],
            [$this->pppSession('budi01')],
        ]);
        $this->customer($router);

        app(PppoeSessionMonitor::class)->run();
        app(PppoeSessionMonitor::class)->run();
        $back = app(PppoeSessionMonitor::class)->run();

        $this->assertSame(1, $back['flaps']);
        $this->assertSame(0, $back['connected']);
        $this->assertSame(0, $back['disconnected']);
        $this->assertDatabaseCount('message_logs', 0);
    }

    #[Test]
    public function api_failure_does_not_mark_sessions_disconnected(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();
        $router = MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $this->customer($router);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('listPppActiveSessions')->twice()->andReturn(
            ['ok' => true, 'sessions' => [$this->pppSession('budi01')]],
            ['ok' => false, 'message' => 'timeout', 'sessions' => []],
        );
        $this->app->instance(MikrotikApiService::class, $api);

        app(PppoeSessionMonitor::class)->run();
        $failed = app(PppoeSessionMonitor::class)->run();

        $this->assertSame(1, $failed['routers_fail']);
        $this->assertSame(0, $failed['disconnected']);
        $this->assertDatabaseCount('message_logs', 0);
    }

    #[Test]
    public function mass_disconnect_sends_one_summary(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();

        $first = [];
        for ($i = 1; $i <= 12; $i++) {
            $first[] = $this->pppSession('user'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), '10.10.10.'.$i);
        }

        $this->mockSessionWaves([$first, []]);

        app(PppoeSessionMonitor::class)->run();
        $summary = app(PppoeSessionMonitor::class)->run();

        $this->assertSame(1, $summary['mass_events']);
        $this->assertSame('mass_down', $summary['events'][0]['type'] ?? null);
        $this->assertSame(1, MessageLog::query()->count());
        $this->assertStringContainsString('Banyak sesi PPPoE terputus', (string) MessageLog::query()->value('body'));
    }

    #[Test]
    public function unknown_username_is_still_reported(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();
        $this->mockSessionWaves([
            [],
            [$this->pppSession('unknown01')],
        ]);

        app(PppoeSessionMonitor::class)->run();
        app(PppoeSessionMonitor::class)->run();

        $body = (string) MessageLog::query()->value('body');
        $this->assertStringContainsString('unknown01', $body);
        $this->assertStringContainsString('belum terdaftar', $body);
    }

    #[Test]
    public function disabled_setting_does_not_poll_or_send(): void
    {
        SiteSetting::setMany([
            'telegram_enabled' => '1',
            'telegram_bot_token' => '123456:TESTTOKEN',
            'telegram_admin_chat_id' => '99',
            'messaging_notify_pppoe_session' => '0',
        ]);
        $this->fakeTelegram();

        $summary = app(PppoeSessionMonitor::class)->run();

        $this->assertFalse($summary['enabled']);
        $this->assertSame(0, $summary['routers_ok']);
        $this->assertDatabaseCount('message_logs', 0);
    }

    #[Test]
    public function admin_can_save_session_notification_settings(): void
    {
        $admin = \App\Models\User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->post('/admin/messaging/templates', [
                'messaging_notify_pppoe_session' => '1',
                'messaging_pppoe_session_debounce' => 5,
            ])
            ->assertRedirect('/admin/messaging');

        $this->assertSame('1', SiteSetting::getValue('messaging_notify_pppoe_session'));
        $this->assertSame('5', SiteSetting::getValue('messaging_pppoe_session_debounce'));
    }
}
