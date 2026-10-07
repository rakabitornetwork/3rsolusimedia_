<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\VpnPortForward;
use App\Services\BillingService;
use App\Services\Vpn\RunsChrCommands;
use App\Services\Vpn\VpnAccessPlan;
use App\Services\Vpn\VpnChrSettings;
use App\Services\Vpn\VpnServerScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VpnAccessPlanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function later_customers_follow_the_previous_port_series(): void
    {
        $plan = app(VpnAccessPlan::class);
        $first = $this->customer('vpn-a');
        $second = $this->customer('vpn-b');

        $plan->ensure($first);
        $plan->ensure($second);
        $first->refresh();
        $second->refresh();

        $this->assertSame($first->vpn_port_series + 1, $second->vpn_port_series);
        $this->assertNotSame($first->vpn_remote_address, $second->vpn_remote_address);

        $ports = $second->vpnPortForwards->keyBy('dst_port');
        $series = (int) $second->vpn_port_series;
        $this->assertSame($series * 100 + 22, $ports[22]->public_port);
        $this->assertSame($series * 100 + 80, $ports[80]->public_port);
        $this->assertSame($series * 100 + 91, $ports[8291]->public_port);
        $this->assertSame($series * 100 + 28, $ports[8728]->public_port);

        $custom = $plan->addCustom($second, 3389, 'RDP');
        $this->assertSame($series * 100 + 1, $custom->public_port);
        $this->assertSame(VpnPortForward::KIND_CUSTOM, $custom->kind);
    }

    #[Test]
    public function push_sends_the_server_script_to_the_chr(): void
    {
        $fake = new class implements RunsChrCommands
        {
            /** @var list<string> */
            public array $commands = [];

            public function run(string $command): array
            {
                $this->commands[] = $command;

                return ['ok' => true, 'output' => '', 'message' => 'ok'];
            }
        };
        $this->app->instance(RunsChrCommands::class, $fake);
        VpnChrSettings::store('31.57.178.91', 2223, 'agenapp', 'test-only');

        $admin = User::factory()->superadmin()->create();
        $customer = $this->customer('vpn-push');
        app(VpnAccessPlan::class)->ensure($customer);
        $customer->refresh();

        $this->actingAs($admin)
            ->post('/admin/customers/pppoe/'.$customer->id.'/vpn/push')
            ->assertRedirect()
            ->assertSessionHas('success');

        $script = implode("\n", $fake->commands);
        $this->assertStringContainsString('remote-address='.$customer->vpn_remote_address, $script);
        $this->assertStringContainsString('local-address=192.168.172.254', $script);
        $this->assertStringContainsString('profile="default-encryption"', $script);
        $this->assertStringContainsString('dst-address=31.57.178.91', $script);
        $this->assertStringContainsString('to-ports=22', $script);
        $this->assertStringContainsString('to-ports=8291', $script);
        $this->assertStringNotContainsString('test-only', $script);

        $this->assertNotNull($customer->vpnPortForwards()->first()?->fresh()->pushed_at);

        $shown = app(VpnServerScript::class)->text($customer->fresh('vpnPortForwards'));
        $this->assertStringContainsString('/ppp secret add', $shown);
    }

    #[Test]
    public function extra_routers_are_limited_to_three_and_stay_off_until_paid(): void
    {
        $plan = app(VpnAccessPlan::class);
        $customer = $this->customer('vpn-utama');
        $package = SubscriptionPackage::query()->create([
            'name' => 'VPN',
            'price' => 150000,
            'mikrotik_profile' => 'default',
            'is_active' => true,
        ]);
        $customer->update(['subscription_package_id' => $package->id]);
        $dueBefore = $customer->fresh()->due_date?->toDateString();

        $plan->ensure($customer);
        $customer->refresh();
        $router = $plan->addRouter($customer, 'toko-pusat');

        $this->assertSame($customer->vpn_port_series + 1, $router->vpn_port_series);
        $this->assertCount(4, $router->portForwards);
        $this->assertCount(4, $customer->vpnPortForwards()->get());

        $invoice = app(BillingService::class)->createVpnRouterOpeningInvoice($customer->fresh(), $router);
        $this->assertSame('vpn_router', $invoice->type);
        $this->assertSame(now()->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame(150000, (int) $invoice->total);
        $this->assertFalse($router->fresh()->isUsable());
        $this->assertStringContainsString('disabled=yes', app(VpnServerScript::class)->textForRouter($router->fresh()));
        $this->assertStringContainsString('name="toko-pusat"', app(VpnServerScript::class)->textForRouter($router->fresh()));

        app(BillingService::class)->markPaid($invoice);
        $router->refresh();
        $this->assertTrue($router->isUsable());
        $this->assertSame($dueBefore, $customer->fresh()->due_date?->toDateString());
        $this->assertStringContainsString('disabled=no', app(VpnServerScript::class)->textForRouter($router->fresh()));

        $plan->addRouter($customer->fresh(), 'gudang');

        $this->expectException(InvalidArgumentException::class);
        $plan->addRouter($customer->fresh(), 'rumah');
    }

    private function customer(string $username): PppoeCustomer
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router '.$username,
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        return PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => $username,
            'username' => $username,
            'password' => 'secret',
            'ppp_service' => PppoeCustomer::SERVICE_L2TP,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);
    }
}
