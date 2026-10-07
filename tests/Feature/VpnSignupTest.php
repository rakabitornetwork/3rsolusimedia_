<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PageSection;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\VpnRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VpnSignupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_landing_page_includes_the_vpn_section(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Landing', false)
                ->where('sections.vpn.key', 'vpn')
                ->where('sections.vpn.cta_url', '/vpn/daftar')
            );

        $this->assertNotNull(PageSection::query()->where('key', 'vpn')->first());
    }

    #[Test]
    public function a_visitor_can_register_a_vpn_account_and_its_first_router(): void
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'CHR pelanggan',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $package = SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => 'VPN 10 Mbps',
            'price' => 150000,
            'mikrotik_profile' => '10Mbps',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->get('/vpn/daftar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vpn/Signup', false)
                ->where('open', true)
                ->where('packages.0.name', 'VPN 10 Mbps')
            );

        $this->post('/vpn/daftar', [
            'name' => 'Siti',
            'phone' => '081234567890',
            'username' => 'siti-vpn',
            'password' => 'rahasia1',
            'router_name' => 'toko-pusat',
            'subscription_package_id' => $package->id,
        ])->assertRedirect();

        $customer = PppoeCustomer::query()->where('username', 'siti-vpn')->first();
        $this->assertNotNull($customer);
        $this->assertSame(PppoeCustomer::SERVICE_L2TP, $customer->ppp_service);
        $this->assertSame('isolated', $customer->status);
        $this->assertTrue($customer->is_active);

        $vpnRouter = VpnRouter::query()->where('name', 'toko-pusat')->first();
        $this->assertNotNull($vpnRouter);
        $this->assertTrue($vpnRouter->included);
        $this->assertFalse($vpnRouter->isUsable());
        $this->assertSame($customer->id, $vpnRouter->pppoe_customer_id);

        $invoice = Invoice::query()->where('pppoe_customer_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertGreaterThan(0, (int) $invoice->total);
        $this->assertNotSame('vpn_router', $invoice->type);
    }
}
