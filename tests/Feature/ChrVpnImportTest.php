<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\User;
use App\Models\VpnPortForward;
use App\Models\VpnRouter;
use App\Services\Vpn\RunsChrCommands;
use App\Services\Vpn\VpnChrSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChrVpnImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function chr_secrets_and_nat_forwards_appear_on_vpn_customers(): void
    {
        $this->app->instance(RunsChrCommands::class, new class implements RunsChrCommands
        {
            public function run(string $command): array
            {
                if (str_contains($command, 'ppp secret find')) {
                    $output = "33net|192.168.172.2|false|default-encryption\n3rsolusimedia|192.168.172.10|true|default-encryption";
                } elseif (str_contains($command, 'password')) {
                    $output = str_contains($command, '33net') ? 'rahasia-33' : 'rahasia-3r';
                } elseif (str_contains($command, 'firewall nat')) {
                    $output = implode("\n", [
                        'tcp|1180|192.168.172.2|80|Port 80 | 33 Net|false',
                        'tcp|1122|192.168.172.2|22|Port 22 SSH | 33 Net|false',
                        'tcp|1722|192.168.172.2|22|Port 1722 SSH | 33 Net|false',
                        'tcp|3000-3010|192.168.172.2|3000-3010|Port ACS | 33 Net|false',
                        'tcp|6522|192.168.172.11|22|Port 22 SSH | CAKRA8|false',
                        'Warning: Permanently added',
                    ]);
                } else {
                    $output = '';
                }

                return ['ok' => true, 'output' => $output, 'message' => 'ok'];
            }
        });
        VpnChrSettings::store('31.57.178.91', 2223, 'agenapp', 'test-only');
        MikrotikRouter::query()->create([
            'name' => 'CHR',
            'host' => '31.57.178.91',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'notes' => 'dipakai VPN TUNNEL',
            'is_active' => true,
        ]);

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->post('/admin/customers/vpn/import-chr')
            ->assertRedirect()
            ->assertSessionHas('success');

        $net = PppoeCustomer::query()->where('username', '33net')->first();
        $this->assertNotNull($net);
        $this->assertSame('33 Net', $net->name);
        $this->assertSame(PppoeCustomer::SERVICE_L2TP, $net->ppp_service);
        $this->assertSame('rahasia-33', $net->password);
        $this->assertSame('bypass', $net->overdue_action);
        $this->assertSame('active', $net->status);
        $this->assertSame(0, Invoice::query()->where('pppoe_customer_id', $net->id)->count());

        $router = VpnRouter::query()->where('name', '33net')->first();
        $this->assertSame('192.168.172.2', $router->vpn_remote_address);
        $ports = $router->portForwards->keyBy('public_port');
        $this->assertCount(4, $ports);
        $this->assertSame(80, $ports[1180]->dst_port);
        $this->assertSame(VpnPortForward::KIND_STANDARD, $ports[1180]->kind);
        $this->assertSame(22, $ports[1122]->dst_port);
        $this->assertSame(22, $ports[1722]->dst_port);
        $this->assertSame(3000, $ports[3000]->public_port);
        $this->assertSame(3010, $ports[3000]->public_port_end);
        $this->assertSame('3000-3010', $ports[3000]->publicPortSpec());

        $disabled = PppoeCustomer::query()->where('username', '3rsolusimedia')->first();
        $this->assertSame('disabled', $disabled->status);
        $this->assertFalse($disabled->is_active);

        $cakra = PppoeCustomer::query()->where('username', 'cakra8')->first();
        $this->assertSame('CAKRA8', $cakra->name);
        $this->assertSame('', $cakra->password);
        $this->assertSame('192.168.172.11', $cakra->vpnRouters->first()->vpn_remote_address);

        $this->actingAs($admin)
            ->post('/admin/customers/vpn/import-chr')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, PppoeCustomer::query()->where('username', '33net')->count());
        $this->assertSame(4, VpnRouter::query()->where('name', '33net')->first()->portForwards()->count());
    }
}
