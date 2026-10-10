<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\BillingService;
use App\Services\MikrotikApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingAdvancePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function paying_three_months_ahead_extends_due_date_before_the_cycle_starts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Jakarta'));

        [$admin, $customer] = $this->customerDueOn('2026-11-20');

        $this->actingAs($admin)
            ->post('/admin/billing/prepare', [
                'username' => 'budi01',
                'months' => 3,
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('status', 'unpaid')->first();
        $this->assertNotNull($invoice);
        $this->assertSame('multi_month', $invoice->type);
        $this->assertSame(3, $invoice->billing_months);
        $this->assertSame('2026-10-20', $invoice->period_start?->toDateString());
        $this->assertSame('2027-01-20', $invoice->period_end?->toDateString());
        $this->assertSame('2026-11-20', $invoice->due_date?->toDateString());
        $this->assertSame(450000, $invoice->total);
        $this->assertSame('2026-11-20', $customer->fresh()->due_date?->toDateString());

        $this->mockRouterSync();
        $result = app(BillingService::class)->markPaid($invoice);

        $this->assertSame('2027-02-20', $result['next_due_date']);
        $this->assertSame('2027-02-20', $customer->fresh()->due_date?->toDateString());
        $this->assertSame(0, Invoice::query()->where('status', 'unpaid')->count());
    }

    #[Test]
    public function one_month_prepare_still_refuses_a_cycle_that_has_not_started(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Jakarta'));

        [$admin] = $this->customerDueOn('2026-11-20');

        $this->actingAs($admin)
            ->from('/admin/billing')
            ->post('/admin/billing/prepare', [
                'username' => 'budi01',
                'months' => 1,
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::query()->where('status', 'unpaid')->count());
    }

    #[Test]
    public function advance_invoice_replaces_the_open_monthly_bill_and_keeps_vpn_router_bills(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00', 'Asia/Jakarta'));

        [, $customer, $package] = $this->customerDueOn('2026-10-20');

        $monthly = Invoice::query()->create([
            'number' => 'INV/2026/10/0008',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-09-20',
            'period_end' => '2026-10-20',
            'due_date' => '2026-10-20',
            'amount' => 150000,
            'discount' => 20000,
            'total' => 130000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);
        $routerBill = Invoice::query()->create([
            'number' => 'INV/2026/10/0009',
            'pppoe_customer_id' => $customer->id,
            'type' => 'vpn_router',
            'billing_months' => 1,
            'period_start' => '2026-10-10',
            'period_end' => '2026-11-10',
            'due_date' => '2026-10-10',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => 'Router kantor',
            'package_price' => 150000,
        ]);

        $invoice = app(BillingService::class)->createAdvanceInvoice($customer->fresh(), 2);

        $this->assertSame('void', $monthly->fresh()->status);
        $this->assertSame('unpaid', $routerBill->fresh()->status);
        $this->assertSame(2, $invoice->billing_months);
        $this->assertSame(300000, $invoice->amount);
        $this->assertSame(20000, $invoice->discount);
        $this->assertSame(280000, $invoice->total);
        $this->assertSame(0, $customer->fresh()->billing_credit);

        $again = app(BillingService::class)->createAdvanceInvoice($customer->fresh(), 2);
        $this->assertSame($invoice->id, $again->id);
        $this->assertSame(1, Invoice::query()->where('status', 'unpaid')->where('type', 'multi_month')->count());
    }

    #[Test]
    public function first_cycle_advance_keeps_the_prorata_opening_amount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00', 'Asia/Jakarta'));

        [, $customer] = $this->customerDueOn('2026-10-20', [
            'start_date' => '2026-10-01',
            'first_bill_amount' => 80000,
        ]);
        Invoice::query()->where('pppoe_customer_id', $customer->id)->delete();

        $invoice = app(BillingService::class)->createAdvanceInvoice($customer->fresh(), 3);

        $this->assertSame(380000, $invoice->amount);
        $this->assertSame('2026-10-01', $invoice->period_start?->toDateString());
        $this->assertSame('2026-12-20', $invoice->period_end?->toDateString());
    }

    #[Test]
    public function pay_action_can_settle_several_months_from_the_open_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00', 'Asia/Jakarta'));

        [$admin, $customer, $package] = $this->customerDueOn('2026-10-20');
        $monthly = Invoice::query()->create([
            'number' => 'INV/2026/10/0011',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-09-20',
            'period_end' => '2026-10-20',
            'due_date' => '2026-10-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $this->actingAs($admin)
            ->from('/admin/billing')
            ->post('/admin/billing/invoices/'.$monthly->id.'/pay', [
                'method' => 'cash',
                'months' => 3,
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('success');

        $this->assertSame('void', $monthly->fresh()->status);

        $paid = Invoice::query()->where('status', 'paid')->where('type', 'multi_month')->first();
        $this->assertNotNull($paid);
        $this->assertSame(3, $paid->billing_months);
        $this->assertSame(450000, $paid->total);
        $this->assertSame('2027-01-20', $customer->fresh()->due_date?->toDateString());
    }

    #[Test]
    public function portal_can_open_an_advance_invoice_before_checkout_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Jakarta'));

        [, $customer] = $this->customerDueOn('2026-11-20');
        $token = Str::lower(Str::random(48));
        Cache::put('portal_pay:'.$token, $customer->id, now()->addHours(2));

        $this->get('/portal/'.$token.'/tagihan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Pay/Show', false)
                ->has('advance.options', 5)
                ->where('advance.options.0.months', 2)
                ->where('advance.options.0.ahead', true)
            );

        $this->from('/portal/'.$token.'/tagihan')
            ->post('/portal/'.$token.'/bayar-depan', ['months' => 2])
            ->assertRedirect('/portal/'.$token.'/tagihan')
            ->assertSessionHas('success');

        $invoice = Invoice::query()->where('status', 'unpaid')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(2, $invoice->billing_months);
        $this->assertSame(300000, $invoice->total);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: User, 1: PppoeCustomer, 2: SubscriptionPackage}
     */
    private function customerDueOn(string $dueDate, array $overrides = []): array
    {
        $admin = User::factory()->superadmin()->create();
        $router = MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $package = SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => '10 Mbps',
            'price' => 150000,
            'mikrotik_profile' => '10Mbps',
            'is_active' => true,
        ]);
        $customer = PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi Santoso',
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-01-20',
            'billing_day' => 20,
            'due_date' => $dueDate,
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));

        Invoice::query()->create([
            'number' => 'INV/2026/09/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-08-20',
            'period_end' => '2026-09-20',
            'due_date' => '2026-09-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'paid',
            'paid_at' => '2026-09-18 10:00:00',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        return [$admin, $customer, $package];
    }

    private function mockRouterSync(): void
    {
        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')->andReturn([
            'ok' => true,
            'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
        ]);
        $this->app->instance(MikrotikApiService::class, $api);
    }
}
