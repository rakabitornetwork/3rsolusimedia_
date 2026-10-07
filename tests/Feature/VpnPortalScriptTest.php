<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\SubscriptionPackage;
use App\Models\VpnRouter;
use App\Services\BillingService;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Messaging\MessageTemplate;
use App\Services\Vpn\L2tpClientScript;
use App\Services\Vpn\RunsChrCommands;
use App\Services\Vpn\VpnChrSettings;
use App\Services\Vpn\VpnRouterAccounts;
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
    public function vpn_templates_leave_out_dana_and_bank_details(): void
    {
        foreach ([
            MessageTemplate::VPN_INVOICE,
            MessageTemplate::VPN_REMINDER,
            MessageTemplate::VPN_PAID,
            MessageTemplate::VPN_ISOLIR,
            MessageTemplate::VPN_RESTORE,
            MessageTemplate::VPN_WELCOME,
        ] as $template) {
            $body = MessageTemplate::get($template);

            $this->assertStringNotContainsString('{{rekening}}', $body, $template);
            $this->assertStringNotContainsString('{{nama_bank}}', $body, $template);
            $this->assertStringNotContainsString('{{nomor_rekening}}', $body, $template);
            $this->assertStringNotContainsString('Dana', $body, $template);
            $this->assertStringNotContainsString('Transfer ke', $body, $template);
        }

        $rendered = MessageTemplate::render(MessageTemplate::VPN_INVOICE, [
            'nama' => 'Siti',
            'nomor' => 'INV-1',
            'total' => 'Rp 100.000',
            'jatuh_tempo' => '01/11/2026',
            'paket' => 'VPN 10 Mbps',
            'rekening' => "Transfer ke:\nBCA\n123\n\nDana\n0812",
            'nama_bank' => 'BCA',
            'nomor_rekening' => '123',
        ]);

        $this->assertStringNotContainsString('Dana', $rendered);
        $this->assertStringNotContainsString('BCA', $rendered);
        $this->assertStringNotContainsString('Transfer ke', $rendered);
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
    public function the_customer_can_replace_a_router_without_a_new_invoice(): void
    {
        $this->app->instance(RunsChrCommands::class, new class implements RunsChrCommands
        {
            public function run(string $command): array
            {
                return ['ok' => true, 'output' => '', 'message' => 'ok'];
            }
        });
        VpnChrSettings::store('31.57.178.91', 2223, 'agenapp', 'test-only');

        $package = SubscriptionPackage::query()->create([
            'name' => 'VPN',
            'price' => 150000,
            'mikrotik_profile' => 'default',
            'is_active' => true,
        ]);
        $customer = $this->customer('203.0.113.10', 'rahasia');
        $customer->update(['subscription_package_id' => $package->id]);
        $enrolled = app(VpnRouterAccounts::class)->enroll($customer, 'toko-lama');
        app(BillingService::class)->markPaid($enrolled['invoice']);
        $invoiceId = $enrolled['invoice']->id;
        $token = $this->portalToken($customer);
        $otherRouter = MikrotikRouter::query()->create([
            'name' => 'Router lain',
            'host' => '203.0.113.11',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret-api',
            'is_active' => true,
        ]);
        $other = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $otherRouter->id,
            'name' => 'Pelanggan lain',
            'phone' => '081234567891',
            'username' => 'vpn-lain',
            'ppp_service' => PppoeCustomer::SERVICE_L2TP,
            'password' => 'rahasia-lain',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);
        $otherToken = $this->portalToken($other);

        $this->delete('/portal/'.$otherToken.'/vpn/routers/'.$enrolled['router']->id)
            ->assertNotFound();
        $this->assertNotNull(VpnRouter::query()->find($enrolled['router']->id));

        $this->delete('/portal/'.$token.'/vpn/routers/'.$enrolled['router']->id)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull(VpnRouter::query()->find($enrolled['router']->id));
        $this->assertSame('paid', Invoice::query()->find($invoiceId)?->status);

        $this->post('/portal/'.$token.'/vpn/routers', ['name' => 'toko-baru'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Invoice::query()->where('pppoe_customer_id', $customer->id)->where('type', 'vpn_router')->count());
        $replacement = VpnRouter::query()->where('name', 'toko-baru')->first();
        $this->assertNotNull($replacement);
        $this->assertTrue($replacement->isUsable());
        $this->assertSame($replacement->id, (int) Invoice::query()->find($invoiceId)?->vpn_router_id);

        $this->get('/portal/'.$token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Vpn/Home', false)
                ->where('vpn.spare_routers', 0)
                ->where('vpn.extra_routers.0.name', 'toko-baru')
            );
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
