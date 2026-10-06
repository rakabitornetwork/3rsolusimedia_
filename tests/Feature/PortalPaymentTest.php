<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\User;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function router(): MikrotikRouter
    {
        return MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
    }

    private function customer(?MikrotikRouter $router = null): PppoeCustomer
    {
        return PppoeCustomer::query()->create([
            'mikrotik_router_id' => ($router ?? $this->router())->id,
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

    private function invoice(PppoeCustomer $customer, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'number' => 'INV-100',
            'pppoe_customer_id' => $customer->id,
            'type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
        ], $overrides));
    }

    /**
     * @return array{0: PppoeCustomer, 1: string}
     */
    private function portalSession(PppoeCustomer $customer): array
    {
        $token = Str::lower(Str::random(48));
        Cache::put('portal_pay:'.$token, $customer->id, now()->addHours(2));

        return [$customer, $token];
    }

    #[Test]
    public function home_exposes_clickable_online_pay_payload_for_unpaid_invoice(): void
    {
        [$customer, $token] = $this->portalSession($this->customer());
        $invoice = $this->invoice($customer);

        $this->get("/portal/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Home')
                ->where('billing.unpaid_count', 1)
                ->where('billing.oldest_unpaid_id', $invoice->id)
                ->where('billing.gateway_ready', false)
            );
    }

    #[Test]
    public function invoices_page_keeps_gateway_ready_flag_for_pay_button(): void
    {
        [$customer, $token] = $this->portalSession($this->customer());
        $this->invoice($customer);

        $this->get("/portal/{$token}/tagihan")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Pay/Show')
                ->where('gateway_ready', false)
                ->has('unpaid', 1)
            );
    }

    #[Test]
    public function pay_without_gateway_returns_customer_friendly_error(): void
    {
        [$customer, $token] = $this->portalSession($this->customer());
        $invoice = $this->invoice($customer);

        $this->from("/portal/{$token}/tagihan")
            ->post("/portal/{$token}/pay/{$invoice->id}")
            ->assertRedirect("/portal/{$token}/tagihan")
            ->assertSessionHas('error', 'Pembayaran online belum diaktifkan. Hubungi admin untuk konfirmasi pembayaran.');
    }

    #[Test]
    public function pay_redirects_to_checkout_when_gateway_ready(): void
    {
        [$customer, $token] = $this->portalSession($this->customer());
        $invoice = $this->invoice($customer);

        $gateways = Mockery::mock(PaymentGatewayManager::class);
        $gateways->shouldReceive('hasEnabledGateway')->andReturn(true);
        $gateways->shouldReceive('createPayment')
            ->once()
            ->withArgs(fn ($passed) => $passed->is($invoice))
            ->andReturn([
                'checkout_url' => 'https://pay.example/abc',
                'transaction' => Mockery::mock(),
            ]);
        $this->app->instance(PaymentGatewayManager::class, $gateways);

        $this->post("/portal/{$token}/pay/{$invoice->id}")
            ->assertRedirect('https://pay.example/abc');
    }

    #[Test]
    public function inertia_pay_sends_external_checkout_location(): void
    {
        [$customer, $token] = $this->portalSession($this->customer());
        $invoice = $this->invoice($customer);

        $gateways = Mockery::mock(PaymentGatewayManager::class);
        $gateways->shouldReceive('hasEnabledGateway')->andReturn(true);
        $gateways->shouldReceive('createPayment')
            ->once()
            ->andReturn([
                'checkout_url' => 'https://pay.example/abc',
                'transaction' => Mockery::mock(),
            ]);
        $this->app->instance(PaymentGatewayManager::class, $gateways);

        $this->withHeaders(['X-Inertia' => 'true'])
            ->post("/portal/{$token}/pay/{$invoice->id}")
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://pay.example/abc');
    }

    #[Test]
    public function cannot_enable_xendit_without_secret_key(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->from('/admin/billing/payment-gateway')
            ->post('/admin/billing/payment-gateway', [
                'pg_default' => 'xendit',
                'xendit_enabled' => true,
                'xendit_mode' => 'sandbox',
                'midtrans_enabled' => false,
                'midtrans_mode' => 'sandbox',
                'duitku_enabled' => false,
                'duitku_mode' => 'sandbox',
            ])
            ->assertRedirect('/admin/billing/payment-gateway')
            ->assertSessionHasErrors('xendit_secret_key');
    }
}
