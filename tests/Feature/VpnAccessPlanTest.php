<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\VpnPortForward;
use App\Models\VpnRouter;
use App\Models\VpnRouterCredit;
use App\Services\BillingService;
use App\Services\Vpn\RunsChrCommands;
use App\Services\Vpn\VpnAccessPlan;
use App\Services\Vpn\VpnChrSettings;
use App\Services\Vpn\VpnRouterAccounts;
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

        $firstRouter = $plan->addRouter($first, 'router-a');
        $secondRouter = $plan->addRouter($second, 'router-b');

        $this->assertSame($firstRouter->vpn_port_series + 1, $secondRouter->vpn_port_series);
        $this->assertNotSame($firstRouter->vpn_remote_address, $secondRouter->vpn_remote_address);

        $ports = $secondRouter->portForwards->keyBy('dst_port');
        $series = (int) $secondRouter->vpn_port_series;
        $this->assertSame($series * 100 + 22, $ports[22]->public_port);
        $this->assertSame($series * 100 + 80, $ports[80]->public_port);
        $this->assertSame($series * 100 + 91, $ports[8291]->public_port);
        $this->assertSame($series * 100 + 28, $ports[8728]->public_port);

        $custom = $plan->addCustom($second, 3389, 'RDP', $secondRouter);
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
        $router = app(VpnAccessPlan::class)->addRouter($customer, 'toko-push');

        $this->actingAs($admin)
            ->post('/admin/customers/pppoe/'.$customer->id.'/vpn/routers/'.$router->id.'/push')
            ->assertRedirect()
            ->assertSessionHas('success');

        $script = implode("\n", $fake->commands);
        $this->assertStringContainsString('name="toko-push"', $script);
        $this->assertStringContainsString('remote-address='.$router->vpn_remote_address, $script);
        $this->assertStringContainsString('local-address=192.168.172.254', $script);
        $this->assertStringContainsString('profile="default-encryption"', $script);
        $this->assertStringContainsString('dst-address=31.57.178.91', $script);
        $this->assertStringContainsString('to-ports=22', $script);
        $this->assertStringContainsString('to-ports=8291', $script);
        $this->assertStringNotContainsString('test-only', $script);

        $this->assertNotNull($router->portForwards()->first()?->fresh()->pushed_at);

        $shown = app(VpnServerScript::class)->textForRouter($router->fresh('portForwards'));
        $this->assertStringContainsString('/ppp secret add', $shown);
        $this->assertStringContainsString('name="toko-push"', $shown);
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

        $accounts = app(VpnRouterAccounts::class);
        $included = $accounts->enroll($customer, 'kantor');
        $this->assertNull($included['invoice']);
        $this->assertTrue($included['router']->included);
        $this->assertTrue($included['router']->isUsable());

        $router = $accounts->enroll($customer->fresh(), 'toko-pusat')['router'];
        $this->assertFalse($router->included);
        $this->assertCount(4, $router->portForwards);

        $invoice = Invoice::query()->where('vpn_router_id', $router->id)->first();
        $this->assertNotNull($invoice);
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

    #[Test]
    public function releasing_a_paid_router_reuses_that_invoice_for_the_next_name(): void
    {
        $fake = $this->bindChr();
        $customer = $this->customer('vpn-ganti');
        $customer->update(['subscription_package_id' => $this->package()->id]);
        $accounts = app(VpnRouterAccounts::class);

        $accounts->enroll($customer, 'kantor');
        $first = $accounts->enroll($customer->fresh(), 'toko-lama');
        $invoice = $first['invoice'];
        $this->assertNotNull($invoice);
        app(BillingService::class)->markPaid($invoice);
        $until = $first['router']->fresh()->service_until?->toDateString();
        $billingDay = (int) $first['router']->billing_day;

        $released = $accounts->release($first['router']->fresh());

        $this->assertTrue($released['ok']);
        $script = implode("\n", $fake->commands);
        $this->assertStringContainsString('name="toko-lama"', $script);
        $this->assertStringContainsString('/ip firewall nat remove', $script);
        $this->assertNull(VpnRouter::query()->find($first['router']->id));
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNull($invoice->vpn_router_id);
        $this->assertSame(1, VpnRouterCredit::query()->count());

        $second = $accounts->enroll($customer->fresh(), 'toko-baru');

        $this->assertTrue($second['reused']);
        $this->assertNull($second['invoice']);
        $this->assertSame(1, Invoice::query()->where('type', 'vpn_router')->count());
        $invoice->refresh();
        $this->assertSame($second['router']->id, (int) $invoice->vpn_router_id);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame($until, $second['router']->service_until?->toDateString());
        $this->assertSame($billingDay, (int) $second['router']->billing_day);
        $this->assertTrue($second['router']->isUsable());
        $this->assertSame(0, VpnRouterCredit::query()->count());
    }

    #[Test]
    public function a_failed_chr_delete_keeps_the_router_and_its_invoice(): void
    {
        $this->app->instance(RunsChrCommands::class, new class implements RunsChrCommands
        {
            public function run(string $command): array
            {
                return ['ok' => false, 'output' => '', 'message' => 'denied'];
            }
        });
        VpnChrSettings::store('31.57.178.91', 2223, 'agenapp', 'test-only');
        $customer = $this->customer('vpn-gagal');
        $customer->update(['subscription_package_id' => $this->package()->id]);
        $accounts = app(VpnRouterAccounts::class);
        $included = $accounts->enroll($customer, 'kantor');
        $first = $accounts->enroll($customer->fresh(), 'toko-gagal');

        $released = $accounts->release($first['router']);

        $this->assertFalse($released['ok']);
        $this->assertNotNull(VpnRouter::query()->find($first['router']->id));
        $this->assertSame('unpaid', $first['invoice']?->fresh()->status);
        $this->assertSame($first['router']->id, (int) $first['invoice']?->fresh()->vpn_router_id);
        $this->assertFalse($accounts->release($included['router'])['ok']);
        $this->assertNotNull(VpnRouter::query()->find($included['router']->id));
        $this->assertSame(0, VpnRouterCredit::query()->count());
    }

    private function bindChr(): object
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

        return $fake;
    }

    private function package(): SubscriptionPackage
    {
        return SubscriptionPackage::query()->create([
            'name' => 'VPN',
            'price' => 150000,
            'mikrotik_profile' => 'default',
            'is_active' => true,
        ]);
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
