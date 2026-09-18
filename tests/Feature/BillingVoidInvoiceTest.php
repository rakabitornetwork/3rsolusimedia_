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
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingVoidInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function voiding_paid_invoice_restores_due_date_creates_replacement_and_isolirs(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 11:00:00', 'Asia/Jakarta'));

        [$customer, $package] = $this->isolatedCustomer();

        $invoice = Invoice::query()->create([
            'number' => 'INV/2026/08/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-05-20',
            'period_end' => '2026-06-20',
            'due_date' => '2026-06-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $this->mockProfileSequence(['10Mbps', 'ISOLIR']);

        $billing = app(BillingService::class);
        $billing->markPaid($invoice);

        $customer->refresh();
        $this->assertSame('2026-09-20', $customer->due_date?->toDateString());
        $this->assertSame('active', $customer->status);

        $result = $billing->voidInvoice($invoice->fresh());
        $customer->refresh();

        $this->assertSame('void', $result['invoice']->status);
        $this->assertTrue($result['replacement_created']);
        $this->assertSame('unpaid', $result['replacement']->status);
        $this->assertSame('2026-06-20', $result['replacement']->due_date?->toDateString());
        $this->assertSame(150000, $result['replacement']->total);
        $this->assertSame('monthly', $result['replacement']->type);
        $this->assertSame('2026-06-20', $customer->due_date?->toDateString());
        $this->assertNull($customer->grace_until);
        $this->assertTrue($customer->shouldIsolir());
        $this->assertSame('isolated', $customer->status);
    }

    #[Test]
    public function voiding_unpaid_invoice_does_not_restore_due_or_create_replacement(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 11:00:00', 'Asia/Jakarta'));

        [$customer, $package] = $this->isolatedCustomer();

        $invoice = Invoice::query()->create([
            'number' => 'INV/2026/08/0002',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-05-20',
            'period_end' => '2026-06-20',
            'due_date' => '2026-06-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $result = app(BillingService::class)->voidInvoice($invoice);
        $customer->refresh();

        $this->assertSame('void', $result['invoice']->status);
        $this->assertNull($result['replacement']);
        $this->assertFalse($result['replacement_created']);
        $this->assertSame('2026-06-20', $customer->due_date?->toDateString());
        $this->assertSame('isolated', $customer->status);
        $this->assertSame(0, Invoice::query()->where('status', 'unpaid')->count());
    }

    #[Test]
    public function voiding_older_paid_invoice_keeps_later_paid_coverage(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-10 11:00:00', 'Asia/Jakarta'));

        [$customer, $package] = $this->isolatedCustomer([
            'due_date' => '2026-08-20',
            'status' => 'active',
        ]);

        $older = Invoice::query()->create([
            'number' => 'INV/2026/06/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-05-20',
            'period_end' => '2026-06-20',
            'due_date' => '2026-06-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'paid',
            'paid_at' => '2026-06-20 09:00:00',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        Invoice::query()->create([
            'number' => 'INV/2026/07/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-06-20',
            'period_end' => '2026-07-20',
            'due_date' => '2026-07-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'paid',
            'paid_at' => '2026-07-05 09:00:00',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $this->mockProfileSequence(['10Mbps']);

        $result = app(BillingService::class)->voidInvoice($older);
        $customer->refresh();

        $this->assertSame('void', $result['invoice']->status);
        $this->assertNotNull($result['replacement']);
        $this->assertTrue($result['replacement_created']);
        $this->assertSame('2026-06-20', $result['replacement']->due_date?->toDateString());
        $this->assertSame('2026-08-20', $customer->due_date?->toDateString());
        $this->assertFalse($customer->shouldIsolir());
        $this->assertSame('active', $customer->status);
    }

    #[Test]
    public function combining_months_still_voids_unpaid_without_extra_replacement(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 11:00:00', 'Asia/Jakarta'));

        [$customer, $package] = $this->isolatedCustomer();

        Invoice::query()->create([
            'number' => 'INV/2026/08/0003',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-05-20',
            'period_end' => '2026-06-20',
            'due_date' => '2026-06-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $this->mockProfileSequence(['10Mbps']);

        $combined = app(BillingService::class)->createCombinedMonthlyInvoice($customer, 2);
        $customer->refresh();

        $this->assertSame('multi_month', $combined->type);
        $this->assertSame('unpaid', $combined->status);
        $this->assertSame(1, Invoice::query()->where('status', 'void')->count());
        $this->assertSame(1, Invoice::query()->where('status', 'unpaid')->count());
        $this->assertSame($combined->id, Invoice::query()->where('status', 'unpaid')->value('id'));
        $this->assertSame('2026-06-20', $customer->due_date?->toDateString());
        $this->assertSame('active', $customer->status);
    }

    #[Test]
    public function voiding_paid_invoice_reuses_existing_unpaid_for_same_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 11:00:00', 'Asia/Jakarta'));

        [$customer, $package] = $this->isolatedCustomer([
            'due_date' => '2026-08-20',
            'status' => 'active',
        ]);

        $paid = Invoice::query()->create([
            'number' => 'INV/2026/06/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-05-20',
            'period_end' => '2026-06-20',
            'due_date' => '2026-06-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'paid',
            'paid_at' => '2026-06-20 09:00:00',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $existingUnpaid = Invoice::query()->create([
            'number' => 'INV/2026/06/0002',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-05-20',
            'period_end' => '2026-06-20',
            'due_date' => '2026-06-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $this->mockProfileSequence(['10Mbps']);

        $result = app(BillingService::class)->voidInvoice($paid);

        $this->assertFalse($result['replacement_created']);
        $this->assertSame($existingUnpaid->id, $result['replacement']?->id);
        $this->assertSame(1, Invoice::query()->where('status', 'unpaid')->count());
    }

    #[Test]
    public function voiding_paid_from_admin_redirects_to_replacement_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 11:00:00', 'Asia/Jakarta'));

        [$customer, $package] = $this->isolatedCustomer();

        $invoice = Invoice::query()->create([
            'number' => 'INV/2026/08/0099',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-05-20',
            'period_end' => '2026-06-20',
            'due_date' => '2026-06-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $this->mockProfileSequence(['10Mbps', 'ISOLIR']);
        app(BillingService::class)->markPaid($invoice);

        $admin = User::factory()->superadmin()->create();

        $response = $this->actingAs($admin)
            ->from('/admin/billing?status=paid')
            ->post('/admin/billing/invoices/'.$invoice->id.'/void');

        $replacement = Invoice::query()->where('status', 'unpaid')->first();
        $this->assertNotNull($replacement);
        $response->assertRedirect(route('admin.billing.show', $replacement));
        $response->assertSessionHas('success');
        $this->assertStringContainsString($replacement->number, session('success'));
        $this->assertSame('unpaid', session('admin.list.billing')['status']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: PppoeCustomer, 1: SubscriptionPackage}
     */
    private function isolatedCustomer(array $overrides = []): array
    {
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
            'name' => 'Amanda',
            'username' => 'amanda',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'isolir_profile' => 'ISOLIR',
            'overdue_action' => 'isolir',
            'billing_day' => 20,
            'start_date' => '2026-01-20',
            'due_date' => '2026-06-20',
            'status' => 'isolated',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));

        return [$customer, $package];
    }

    /**
     * @param  list<string>  $profiles
     */
    private function mockProfileSequence(array $profiles): void
    {
        $api = Mockery::mock(MikrotikApiService::class);

        foreach ($profiles as $profile) {
            $api->shouldReceive('upsertPppSecret')
                ->once()
                ->withArgs(function (
                    $passedRouter,
                    string $username,
                    string $password,
                    ?string $targetProfile,
                    ?string $comment,
                    bool $disabled,
                    bool $disconnectActive,
                ) use ($profile) {
                    return $targetProfile === $profile
                        && $disabled === false;
                })
                ->andReturn([
                    'ok' => true,
                    'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
                ]);
        }

        $this->app->instance(MikrotikApiService::class, $api);
    }
}
