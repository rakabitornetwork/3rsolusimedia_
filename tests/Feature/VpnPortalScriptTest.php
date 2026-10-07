<?php

namespace Tests\Feature;

use App\Models\MessageLog;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Messaging\MessageTemplate;
use App\Services\Vpn\L2tpClientScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VpnPortalScriptTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function script_quotes_routeros_password_and_targets_the_assigned_router(): void
    {
        $customer = $this->customer('203.0.113.10', 'p@ss"w$rd');

        $script = app(L2tpClientScript::class)->build($customer);

        $this->assertNotNull($script);
        $this->assertStringContainsString('connect-to=203.0.113.10', $script);
        $this->assertStringContainsString('user="vpnuser"', $script);
        $this->assertStringContainsString('password="p@ss\\"w\\$rd"', $script);
        $this->assertStringContainsString('name="l2tp-vpn"', $script);
        $this->assertStringContainsString('add-default-route=no', $script);
        $this->assertStringContainsString('New Terminal Winbox', $script);
        $this->assertStringNotContainsString('secret-api', $script);
    }

    #[Test]
    public function vpn_portal_home_shows_the_script_and_hides_the_device_page(): void
    {
        $customer = $this->customer('203.0.113.10', 'rahasia');
        $token = $this->portalToken($customer);

        $this->get('/portal/'.$token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Vpn/Home', false)
                ->where('customer.ppp_service', 'l2tp')
                ->where('vpn.server', '203.0.113.10')
                ->where('vpn.username', 'vpnuser')
                ->where('script', fn ($script) => is_string($script)
                    && str_contains($script, 'connect-to=203.0.113.10')
                    && str_contains($script, 'password="rahasia"')
                    && ! str_contains($script, 'secret-api'))
                ->missing('device')
            );

        $this->get('/portal/'.$token.'/perangkat')
            ->assertRedirect('/portal/'.$token)
            ->assertSessionHas('error');
    }

    #[Test]
    public function pppoe_portal_home_stays_on_the_device_page(): void
    {
        $customer = $this->customer('203.0.113.10', 'rahasia', PppoeCustomer::SERVICE_PPPOE);
        $token = $this->portalToken($customer);

        $this->get('/portal/'.$token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Home', false)
                ->where('customer.ppp_service', 'pppoe')
                ->missing('script')
            );
    }

    #[Test]
    public function vpn_isolir_notice_uses_the_vpn_template(): void
    {
        SiteSetting::setMany([
            'whatsapp_enabled' => '1',
            'whatsapp_base_url' => 'http://evolution.test',
            'whatsapp_api_key' => 'evo-key',
            'whatsapp_instance' => 'teslatech',
            'messaging_notify_isolir' => '1',
        ]);
        Http::fake([
            'http://evolution.test/*' => Http::response(['key' => ['id' => 'BAE1']], 201),
        ]);

        $customer = $this->customer('203.0.113.10', 'rahasia');

        app(CustomerNotifier::class)->notifyIsolir($customer);

        $log = MessageLog::query()->where('command', MessageTemplate::VPN_ISOLIR)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('dinonaktifkan', (string) $log->body);
        $this->assertStringContainsString('Winbox', (string) $log->body);
        $this->assertStringNotContainsString('ONU', (string) $log->body);
        $this->assertStringNotContainsString('rahasia', (string) $log->body);
    }

    #[Test]
    public function vpn_welcome_points_at_the_portal_script_without_the_password(): void
    {
        SiteSetting::setMany([
            'whatsapp_enabled' => '1',
            'whatsapp_base_url' => 'http://evolution.test',
            'whatsapp_api_key' => 'evo-key',
            'whatsapp_instance' => 'teslatech',
            'messaging_notify_welcome' => '1',
        ]);
        Http::fake([
            'http://evolution.test/*' => Http::response(['key' => ['id' => 'BAE1']], 201),
        ]);

        $customer = $this->customer('203.0.113.10', 'rahasia-vpn');

        app(CustomerNotifier::class)->notifyWelcome($customer);

        $log = MessageLog::query()->where('command', MessageTemplate::VPN_WELCOME)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('New Terminal', (string) $log->body);
        $this->assertStringContainsString('/portal', (string) $log->body);
        $this->assertStringNotContainsString('rahasia-vpn', (string) $log->body);
        $this->assertStringNotContainsString('ONU', (string) $log->body);
    }

    private function customer(string $host, string $password, string $service = PppoeCustomer::SERVICE_L2TP): PppoeCustomer
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router VPN',
            'host' => $host,
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret-api',
            'is_active' => true,
        ]);

        return PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => 'Siti VPN',
            'phone' => '081234567890',
            'username' => 'vpnuser',
            'ppp_service' => $service,
            'password' => $password,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);
    }

    private function portalToken(PppoeCustomer $customer): string
    {
        $token = Str::lower(Str::random(48));
        Cache::put('portal_pay:'.$token, $customer->id, now()->addHours(2));

        return $token;
    }
}
