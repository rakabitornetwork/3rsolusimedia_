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
                ->where('sections.vpn.title', 'VPN Tunnel untuk mengalihkan trafik Speedtest')
                ->where('sections.vpn.cta_url', '/vpn/daftar')
                ->where('sections.vpn.content.features.0', 'Alihkan trafik Speedtest dari ISP utama ke VPN Tunnel')
                ->where('sections.vpn.content.steps.0.title', 'Daftar')
                ->where('sections.vpn.content.steps.1.title', 'Coba gratis 3 hari')
                ->where('sections.vpn.content.steps.2.title', 'Setelah masa coba')
            );

        $this->assertNotNull(PageSection::query()->where('key', 'vpn')->first());
    }

    #[Test]
    public function a_visitor_registers_with_name_email_and_whatsapp_only(): void
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
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'phone' => '081234567890',
        ])->assertRedirect('/portal');

        $customer = PppoeCustomer::query()->where('email', 'siti@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertSame(PppoeCustomer::SERVICE_L2TP, $customer->ppp_service);
        $this->assertSame('active', $customer->status);
        $this->assertTrue($customer->is_active);
        $this->assertSame('6281234567890', $customer->phone);
        $this->assertTrue($customer->vpnTrialActive());
        $this->assertSame(now()->startOfDay()->addDays(3)->toDateString(), $customer->vpn_trial_ends_at->toDateString());
        $this->assertSame(0, VpnRouter::query()->count());
        $this->assertSame(0, Invoice::query()->count());
        $this->assertNotSame('', (string) $customer->password);

        $this->post('/vpn/daftar', [
            'name' => 'Siti Lagi',
            'email' => 'lagi@example.com',
            'phone' => '081234567890',
        ])->assertSessionHasErrors('phone');
    }

    #[Test]
    public function the_free_router_is_billed_after_three_days_and_removed_after_a_month_without_payment(): void
    {
        $this->package();
        $this->post('/vpn/daftar', [
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'phone' => '081298765432',
        ])->assertRedirect('/portal');

        $customer = PppoeCustomer::query()->where('email', 'siti@example.com')->firstOrFail();
        app(BillingService::class)->generateOpenInvoices();
        $this->assertSame(0, Invoice::query()->count());

        $router = app(VpnRouterAccounts::class)->enroll($customer, 'toko-pusat')['router'];
        $this->assertTrue($router->included);
        $this->assertTrue($router->isUsable());

        Carbon::setTestNow(now()->addDays(4));
        $this->artisan('vpn:close-trials')->assertSuccessful();

        $customer->refresh();
        $this->assertSame('isolated', $customer->status);
        $this->assertNotNull($customer->vpn_trial_billed_at);
        $this->assertFalse($router->fresh()->isUsable());
        $invoice = Invoice::query()->where('pppoe_customer_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('monthly', $invoice->type);
        $this->assertNotNull(VpnRouter::query()->find($router->id));

        Carbon::setTestNow(Carbon::parse($customer->start_date)->addMonthNoOverflow());
        $this->artisan('vpn:close-trials')->assertSuccessful();

        $this->assertNull(VpnRouter::query()->find($router->id));
        $this->assertNotNull(PppoeCustomer::query()->find($customer->id));
        $this->assertSame('unpaid', $invoice->fresh()->status);

        Carbon::setTestNow();
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
