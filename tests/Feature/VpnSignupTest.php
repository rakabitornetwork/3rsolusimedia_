<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PageSection;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\VpnRouter;
use App\Services\BillingService;
use App\Services\Vpn\VpnRouterAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VpnSignupTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_landing_page_explains_speedtest_steering(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Landing', false)
                ->where('sections.vpn.key', 'vpn')
                ->where('sections.vpn.title', 'VPN Tunnel: remote RouterOS dan Speedtest')
                ->where('sections.vpn.cta_url', '/vpn/daftar')
                ->where('sections.vpn.content.features.0', 'Alihkan trafik Speedtest dari ISP utama ke VPN Tunnel')
                ->where('sections.vpn.content.steps.0.title', 'Daftar')
                ->where('sections.vpn.content.steps.1.title', 'Coba gratis')
                ->where('sections.vpn.content.steps.2.title', 'Jika cocok')
                ->where('sections.vpn.content.steps.1.description', 'Masuk portal dengan email dan password, lalu buat akun VPN. Gratis 3 hari.')
                ->where('sections.vpn.content.steps.2.description', 'Bayar tagihan lewat payment gateway. Jika tidak dibayar setelah masa gratis, akun dihapus dari CHR.')
            );

        $this->assertNotNull(PageSection::query()->where('key', 'vpn')->first());
    }

    #[Test]
    public function a_visitor_registers_with_name_email_password_and_whatsapp(): void
    {
        $this->package();

        $this->get('/vpn/daftar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Vpn/Signup', false)
                ->where('open', true)
                ->missing('packages')
            );

        $this->post('/vpn/daftar', [
            'name' => '',
            'email' => 'bukan-email',
            'password' => 'pendek',
            'password_confirmation' => 'lain',
            'phone' => '',
        ])->assertSessionHasErrors(['name', 'email', 'password', 'phone']);

        $this->post('/vpn/daftar', [
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'phone' => '081234567890',
        ])->assertRedirect('/vpn/masuk');

        $customer = PppoeCustomer::query()->where('email', 'siti@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame(PppoeCustomer::SERVICE_L2TP, $customer->ppp_service);
        $this->assertSame('Siti', $customer->username);
        $this->assertSame('disabled', $customer->status);
        $this->assertFalse($customer->is_active);
        $this->assertTrue($customer->vpn_self_signup);
        $this->assertSame('6281234567890', $customer->phone);
        $this->assertSame('Rahasia123', $customer->password);
        $this->assertFalse($customer->vpnTrialActive());
        $this->assertNull($customer->vpn_trial_ends_at);
        $this->assertSame(0, VpnRouter::query()->count());
        $this->assertSame(0, Invoice::query()->count());

        $this->post('/vpn/daftar', [
            'name' => 'Siti Lagi',
            'email' => 'lagi@example.com',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'phone' => '081234567890',
        ])->assertSessionHasErrors('phone');

        $this->post('/vpn/daftar', [
            'name' => 'Siti Lagi',
            'email' => 'siti@example.com',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'phone' => '081298765432',
        ])->assertSessionHasErrors('email');
    }

    #[Test]
    public function a_vpn_customer_logs_in_with_email_and_password_then_creates_a_free_account(): void
    {
        $this->package();
        $this->post('/vpn/daftar', [
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'phone' => '081234567890',
        ])->assertRedirect('/vpn/masuk');

        $this->post('/vpn/masuk', [
            'email' => '',
            'password' => '',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->post('/vpn/masuk', [
            'email' => 'siti@example.com',
            'password' => 'salah',
        ])->assertSessionHasErrors('email');

        $this->post('/vpn/masuk', [
            'email' => 'siti@example.com',
            'password' => 'Rahasia123',
        ])->assertRedirect();

        $customer = PppoeCustomer::query()->where('email', 'siti@example.com')->firstOrFail();
        app(VpnRouterAccounts::class)->enroll($customer, 'toko-pusat');
        $customer->refresh();
        $this->assertTrue($customer->vpnTrialActive());
        $this->assertSame(now()->startOfDay()->addDays(3)->toDateString(), $customer->vpn_trial_ends_at->toDateString());
        $this->assertTrue($customer->is_active);
        $this->assertSame(1, VpnRouter::query()->count());
    }

    #[Test]
    public function the_free_router_is_billed_a_month_after_the_trial_and_removed_a_month_after_the_start_without_payment(): void
    {
        $this->package();
        $this->post('/vpn/daftar', [
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'phone' => '081298765432',
        ])->assertRedirect('/vpn/masuk');

        $customer = PppoeCustomer::query()->where('email', 'siti@example.com')->firstOrFail();
        app(BillingService::class)->generateOpenInvoices();
        $this->assertSame(0, Invoice::query()->count());

        $router = app(VpnRouterAccounts::class)->enroll($customer, 'toko-pusat')['router'];
        $this->assertTrue($router->included);
        $this->assertTrue($router->isUsable());

        Carbon::setTestNow(now()->addDays(4));
        $this->artisan('vpn:close-trials')->assertSuccessful();

        $customer->refresh();
        $this->assertSame('active', $customer->status);
        $this->assertNull($customer->vpn_trial_billed_at);
        $this->assertTrue($router->fresh()->isUsable());
        $this->assertSame(0, Invoice::query()->count());
        $this->assertNotNull(VpnRouter::query()->find($router->id));

        Carbon::setTestNow(Carbon::parse($customer->start_date)->addMonthNoOverflow());
        $this->artisan('vpn:close-trials')->assertSuccessful();

        $customer->refresh();
        $this->assertNull($customer->vpn_trial_billed_at);
        $this->assertSame(0, Invoice::query()->count());
        $this->assertNull(VpnRouter::query()->find($router->id));
        $this->assertNotNull(PppoeCustomer::query()->find($customer->id));

        Carbon::setTestNow(Carbon::parse($customer->vpn_trial_ends_at)->addMonthNoOverflow());
        $this->artisan('vpn:close-trials')->assertSuccessful();

        $customer->refresh();
        $this->assertSame('isolated', $customer->status);
        $this->assertNotNull($customer->vpn_trial_billed_at);
        $invoice = Invoice::query()->where('pppoe_customer_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('monthly', $invoice->type);
        $this->assertNotNull(PppoeCustomer::query()->find($customer->id));

        Carbon::setTestNow();
    }

    #[Test]
    public function signup_uses_the_vpn_tunnel_router_instead_of_the_first_catalog_package(): void
    {
        $sendy = MikrotikRouter::query()->create([
            'name' => 'SENDY MEDIA DATA',
            'host' => '10.0.0.2',
            'port' => 1928,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
        SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $sendy->id,
            'name' => '10 Mbps : 2 | 120K',
            'price' => 120000,
            'mikrotik_profile' => '10 Mbps',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $perwira = MikrotikRouter::query()->create([
            'name' => 'PERWIRA CLOUD',
            'host' => '10.0.0.4',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
            'notes' => 'VPN TUNNEL L2TP',
        ]);
        $package = SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $perwira->id,
            'name' => 'VPN Tunnel + Speedtest',
            'price' => 25000,
            'mikrotik_profile' => 'l2tp-profile',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $perwira->id,
            'name' => 'VPN Lite',
            'price' => 20000,
            'mikrotik_profile' => 'l2tp-profile',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->post('/vpn/daftar', [
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
            'phone' => '081234567890',
        ])->assertRedirect('/vpn/masuk');

        $customer = PppoeCustomer::query()->where('email', 'siti@example.com')->firstOrFail();
        $this->assertSame($perwira->id, $customer->mikrotik_router_id);
        $this->assertSame($package->id, $customer->subscription_package_id);
        $this->assertSame('l2tp-profile', $customer->service_profile);
    }

    private function package(): SubscriptionPackage
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'CHR pelanggan',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        return SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => 'VPN 10 Mbps',
            'price' => 150000,
            'mikrotik_profile' => '10Mbps',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
