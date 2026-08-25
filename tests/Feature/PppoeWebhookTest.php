<?php

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MikrotikApiService;
use App\Services\PppoeWebhookScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PppoeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('pppoeInterfaceBytes')->andReturn(null);
        $api->shouldReceive('pppoeInterfaceBytesMap')->andReturn([]);
        $this->app->instance(MikrotikApiService::class, $api);
    }

    private function enableWatch(int $debounce = 3): void
    {
        SiteSetting::setMany([
            'telegram_enabled' => '1',
            'telegram_bot_token' => '123456:TESTTOKEN',
            'telegram_admin_chat_id' => '99',
            'messaging_notify_pppoe_session' => '1',
            'messaging_pppoe_session_debounce' => (string) $debounce,
            'pppoe_webhook_secret' => 'pppoe-secret-token',
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

    private function router(string $name = 'Router 1'): MikrotikRouter
    {
        return MikrotikRouter::query()->create([
            'name' => $name,
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
    }

    private function customer(MikrotikRouter $router): PppoeCustomer
    {
        return PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'username' => 'budi01',
            'password' => 'secret',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function valid_up_event_notifies_telegram(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();
        $router = $this->router();
        $this->customer($router);

        $response = $this->get('/webhooks/pppoe?'.http_build_query([
            'token' => 'pppoe-secret-token',
            'event' => 'up',
            'router' => $router->id,
            'user' => 'budi01',
            'ip' => '10.10.10.5',
            'mac' => 'AA:BB:CC:DD:EE:FF',
        ]));

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertDatabaseHas('message_logs', [
            'channel' => 'telegram',
            'command' => 'pppoe_up',
            'status' => 'sent',
        ]);
        $this->assertStringContainsString('PPPoE connected', (string) MessageLog::query()->value('body'));
        $this->assertStringContainsString('budi01', (string) MessageLog::query()->value('body'));
    }

    #[Test]
    public function post_event_is_accepted(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();
        $router = $this->router();

        $this->post('/webhooks/pppoe', [
            'token' => 'pppoe-secret-token',
            'event' => 'up',
            'router' => $router->id,
            'user' => 'unknown01',
        ])->assertOk();

        $this->assertDatabaseHas('message_logs', [
            'command' => 'pppoe_up',
            'status' => 'sent',
        ]);
        $this->assertStringContainsString('belum terdaftar', (string) MessageLog::query()->value('body'));
    }

    #[Test]
    public function bad_token_is_forbidden(): void
    {
        $this->enableWatch(0);

        $this->get('/webhooks/pppoe?token=wrong&event=up&router=1&user=budi01')
            ->assertForbidden()
            ->assertSee('FORBIDDEN');
    }

    #[Test]
    public function ping_returns_ok_without_notify(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();

        $this->get('/webhooks/pppoe?token=pppoe-secret-token&event=ping&router=1')
            ->assertOk();

        $this->assertSame('OK', $this->get('/webhooks/pppoe?token=pppoe-secret-token&event=ping')->getContent());
        $this->assertDatabaseCount('message_logs', 0);
    }

    #[Test]
    public function unknown_router_does_not_retry_storm(): void
    {
        $this->enableWatch(0);
        $this->fakeTelegram();

        $response = $this->get('/webhooks/pppoe?token=pppoe-secret-token&event=up&router=999&user=budi01');

        $response->assertOk();
        $this->assertSame('UNKNOWN_ROUTER', $response->getContent());
        $this->assertDatabaseCount('message_logs', 0);
    }

    #[Test]
    public function scripts_are_one_command_per_line_with_router_and_user(): void
    {
        SiteSetting::setValue('pppoe_webhook_secret', 'pppoe-secret-token');
        $router = $this->router('Core-1');
        $script = PppoeWebhookScript::forRouter($router);

        $this->assertSame($router->id, $script['router_id']);
        $this->assertStringContainsString('router='.$router->id, $script['on_up']);
        $this->assertStringContainsString('event=up', $script['on_up']);
        $this->assertStringContainsString('.$user.', $script['on_up']);
        $this->assertStringContainsString('$"remote-address"', $script['on_up']);
        $this->assertStringContainsString('$"caller-id"', $script['on_up']);
        $this->assertStringContainsString('keep-result=no', $script['on_up']);
        $this->assertStringContainsString('check-certificate=no', $script['on_up']);
        $this->assertStringNotContainsString("\n", $script['on_up']);
        $this->assertStringContainsString('.$user)', $script['on_down']);
        $this->assertStringContainsString('event=down', $script['on_down']);
        $this->assertStringNotContainsString("\n", $script['on_down']);
        $this->assertStringContainsString('event=ping', $script['ping']);
        $this->assertStringStartsWith('/ppp profile set [find] on-up="', $script['apply_up']);
        $this->assertStringStartsWith('/ppp profile set [find] on-down="', $script['apply_down']);
        $this->assertStringNotContainsString('on-up={', $script['apply_up']);
        $this->assertStringNotContainsString('on-down={', $script['apply_down']);
        $this->assertStringContainsString('\\$user', $script['apply_up']);
        $this->assertStringContainsString('\\$user', $script['apply_down']);
        $this->assertStringNotContainsString("\n", $script['apply_up']);
        $this->assertStringNotContainsString("\n", $script['apply_down']);
        $this->assertSame($script['apply_up']."\n".$script['apply_down'], $script['apply_all']);
        $this->assertCount(3, explode("\n", $script['all']));
    }

    #[Test]
    public function messaging_page_lists_scripts_for_each_active_router(): void
    {
        SiteSetting::setValue('pppoe_webhook_secret', 'pppoe-secret-token');
        $alpha = $this->router('Alpha');
        $this->router('Beta');
        $this->router('Gamma');

        $this->actingAs(User::factory()->superadmin()->create())
            ->get('/admin/messaging')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Messaging/Index')
                ->has('pppoe_scripts', 3)
                ->where('pppoe_scripts.0.router_name', 'Alpha')
                ->where('pppoe_scripts.0.router_id', $alpha->id)
                ->has('pppoe_scripts.0.on_up')
                ->has('pppoe_scripts.0.on_down')
                ->has('pppoe_scripts.0.apply_up')
                ->has('pppoe_scripts.0.apply_down')
                ->has('pppoe_scripts.0.apply_all')
                ->has('webhook_urls.pppoe'));
    }

    #[Test]
    public function admin_can_regenerate_pppoe_webhook_secret(): void
    {
        SiteSetting::setValue('pppoe_webhook_secret', 'old-pppoe-secret');
        $this->router('Alpha');

        $this->actingAs(User::factory()->superadmin()->create())
            ->post('/admin/messaging/pppoe-webhook-secret')
            ->assertRedirect('/admin/messaging');

        $new = SiteSetting::getValue('pppoe_webhook_secret');
        $this->assertNotSame('old-pppoe-secret', $new);
        $this->assertNotEmpty($new);
        $this->assertStringContainsString((string) $new, PppoeWebhookScript::forActiveRouters()[0]['on_up']);
    }
}
