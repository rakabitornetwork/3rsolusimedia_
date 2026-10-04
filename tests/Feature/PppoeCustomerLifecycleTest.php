<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\MikrotikApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PppoeCustomerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function package_change_on_the_due_date_disconnects_onto_the_new_profile(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-03 09:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $upgrade = $this->upgradePackage($router, 250000, '20Mbps');
        $customer = $this->customer($router, $package, [
            'start_date' => '2026-01-03',
            'due_date' => '2026-10-03',
            'billing_day' => 3,
            'status' => 'active',
        ]);
        $this->monthlyInvoice($customer, $package, '2026-09-03', '2026-10-03', 150000);

        $this->mockSecret(profile: '20Mbps', disabled: false, disconnect: true);

        $this->actingAs($admin)
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $upgrade, [
                'start_date' => '2026-01-03',
                'due_date' => '2026-10-03',
                'service_profile' => '20Mbps',
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer->refresh();
        $this->assertSame('20Mbps', $customer->service_profile);
        $this->assertSame('2026-11-03', $customer->due_date?->toDateString());
        $this->assertSame('active', $customer->status);

        $replacement = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->first();
        $this->assertSame(250000, $replacement?->total);
        $this->assertSame('2026-11-03', $replacement?->due_date?->toDateString());
    }

    #[Test]
    public function package_change_while_isolated_restores_the_new_profile_and_disconnects(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-03 09:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $upgrade = $this->upgradePackage($router, 250000, '20Mbps');
        $customer = $this->customer($router, $package, [
            'start_date' => '2026-01-03',
            'due_date' => '2026-09-03',
            'billing_day' => 3,
            'status' => 'isolated',
            'isolir_profile' => 'ISOLIR',
            'overdue_action' => 'isolir',
        ]);

        $this->mockSecret(profile: '20Mbps', disabled: false, disconnect: true);

        $this->actingAs($admin)
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $upgrade, [
                'start_date' => '2026-01-03',
                'due_date' => '2026-09-03',
                'service_profile' => '20Mbps',
                'password' => '',
                'overdue_action' => 'isolir',
                'isolir_profile' => 'ISOLIR',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer->refresh();
        $this->assertSame('20Mbps', $customer->service_profile);
        $this->assertSame('active', $customer->status);
        $this->assertSame('2026-11-03', $customer->due_date?->toDateString());
    }

    #[Test]
    public function mid_cycle_upgrade_bills_the_price_difference_and_disconnects(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $upgrade = $this->upgradePackage($router, 250000, '20Mbps');
        $customer = $this->customer($router, $package, [
            'due_date' => '2026-10-20',
            'billing_day' => 20,
        ]);
        $this->paidInvoice($customer, $package, '2026-08-20', '2026-09-20', 150000, 'INV/2026/09/0101');
        $open = $this->monthlyInvoice($customer, $package, '2026-09-20', '2026-10-20', 150000);

        $this->mockSecret(profile: '20Mbps', disabled: false, disconnect: true);

        $this->actingAs($admin)
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $upgrade, [
                'start_date' => '2026-01-20',
                'due_date' => '2026-10-20',
                'service_profile' => '20Mbps',
                'service_change_date' => '2026-10-05',
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer->refresh();
        $open->refresh();

        $this->assertSame('2026-10-20', $customer->due_date?->toDateString());
        $this->assertSame('2026-01-20', $customer->start_date?->toDateString());
        $this->assertSame(150000, $customer->first_bill_amount);
        $this->assertSame('unpaid', $open->status);
        $this->assertSame(150000, $open->total);

        $adjustment = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('type', 'adjustment')
            ->where('status', 'unpaid')
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(50000, $adjustment->total);
        $this->assertSame('2026-10-05', $adjustment->period_start?->toDateString());
        $this->assertSame('2026-10-20', $adjustment->due_date?->toDateString());
    }

    #[Test]
    public function mid_cycle_downgrade_credits_the_open_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $downgrade = $this->upgradePackage($router, 100000, '5Mbps', '5 Mbps');
        $customer = $this->customer($router, $package, [
            'due_date' => '2026-10-20',
            'billing_day' => 20,
        ]);
        $this->paidInvoice($customer, $package, '2026-08-20', '2026-09-20', 150000, 'INV/2026/09/0102');
        $open = $this->monthlyInvoice($customer, $package, '2026-09-20', '2026-10-20', 150000);

        $this->mockSecret(profile: '5Mbps', disabled: false, disconnect: true);

        $this->actingAs($admin)
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $downgrade, [
                'start_date' => '2026-01-20',
                'due_date' => '2026-10-20',
                'service_profile' => '5Mbps',
                'service_change_date' => '2026-10-05',
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $open->refresh();
        $customer->refresh();

        $this->assertSame(25000, $open->discount);
        $this->assertSame(125000, $open->total);
        $this->assertSame(0, (int) $customer->billing_credit);
        $this->assertSame(0, Invoice::query()->where('type', 'adjustment')->count());
    }

    #[Test]
    public function changing_due_date_after_payment_starts_from_the_last_paid_due(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $customer = $this->customer($router, $package, [
            'start_date' => '2026-01-20',
            'due_date' => '2026-10-20',
            'billing_day' => 20,
            'first_bill_amount' => 90000,
            'first_bill_days' => 18,
        ]);
        $paid = $this->paidInvoice($customer, $package, '2026-08-20', '2026-09-20', 150000, 'INV/2026/09/0103');
        $open = $this->monthlyInvoice($customer, $package, '2026-09-20', '2026-10-20', 150000);

        $this->mockSecret();

        $this->actingAs($admin)
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $package, [
                'start_date' => '2026-01-20',
                'due_date' => '2026-11-05',
                'billing_day' => 5,
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer->refresh();
        $paid->refresh();
        $open->refresh();

        $this->assertSame('2026-01-20', $customer->start_date?->toDateString());
        $this->assertSame(90000, $customer->first_bill_amount);
        $this->assertSame('2026-11-05', $customer->due_date?->toDateString());
        $this->assertSame(5, $customer->billing_day);
        $this->assertSame('paid', $paid->status);
        $this->assertSame(150000, $paid->total);
        $this->assertSame('void', $open->status);

        $replacement = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->first();

        $this->assertSame('2026-09-20', $replacement?->period_start?->toDateString());
        $this->assertSame('2026-11-05', $replacement?->due_date?->toDateString());
        $this->assertSame(223000, $replacement?->total);
        $this->assertNotSame('2026-01-20', $replacement?->period_start?->toDateString());
    }

    #[Test]
    public function stopping_before_the_due_date_replaces_the_open_invoice_with_usage(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $customer = $this->customer($router, $package, [
            'due_date' => '2026-10-20',
            'billing_day' => 20,
        ]);
        $open = $this->monthlyInvoice($customer, $package, '2026-09-20', '2026-10-20', 150000);

        $this->mockSecret(profile: '10Mbps', disabled: true, disconnect: false);

        $this->actingAs($admin)
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $package, [
                'start_date' => '2026-01-20',
                'due_date' => '2026-10-20',
                'is_active' => 0,
                'stop_date' => '2026-10-10',
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer->refresh();
        $open->refresh();

        $this->assertFalse($customer->is_active);
        $this->assertSame('disabled', $customer->status);
        $this->assertSame('2026-10-10', $customer->stopped_at?->toDateString());
        $this->assertSame('void', $open->status);

        $settlement = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->first();

        $this->assertSame('adjustment', $settlement?->type);
        $this->assertSame(100000, $settlement?->total);
        $this->assertSame('2026-09-20', $settlement?->period_start?->toDateString());
        $this->assertSame('2026-10-10', $settlement?->period_end?->toDateString());
    }

    #[Test]
    public function stopping_a_paid_cycle_early_stores_credit_for_unused_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 09:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $customer = $this->customer($router, $package, [
            'due_date' => '2026-10-20',
            'billing_day' => 20,
        ]);
        $paid = $this->paidInvoice($customer, $package, '2026-09-20', '2026-10-20', 150000, 'INV/2026/10/0104');

        $this->mockSecret(profile: '10Mbps', disabled: true, disconnect: false);

        $this->actingAs($admin)
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $package, [
                'start_date' => '2026-01-20',
                'due_date' => '2026-10-20',
                'is_active' => 0,
                'stop_date' => '2026-10-10',
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer->refresh();
        $paid->refresh();

        $this->assertSame('paid', $paid->status);
        $this->assertSame(50000, (int) $customer->billing_credit);
        $this->assertSame(0, Invoice::query()->where('status', 'unpaid')->count());
    }

    #[Test]
    public function reactivation_prorates_from_the_reactivation_date_to_the_chosen_due_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $customer = $this->customer($router, $package, [
            'start_date' => '2026-01-05',
            'due_date' => '2026-10-05',
            'billing_day' => 5,
            'first_bill_amount' => 150000,
            'first_bill_days' => 31,
            'is_active' => false,
            'status' => 'disabled',
            'stopped_at' => '2026-09-20',
        ]);
        $this->paidInvoice($customer, $package, '2026-08-05', '2026-09-05', 150000, 'INV/2026/09/0105');

        $this->mockSecret(profile: '10Mbps', disabled: false, disconnect: true);

        $this->actingAs($admin)
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $package, [
                'start_date' => '2026-01-05',
                'due_date' => '2026-11-05',
                'billing_day' => 5,
                'is_active' => 1,
                'reactivate_date' => '2026-10-12',
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer->refresh();

        $this->assertTrue($customer->is_active);
        $this->assertSame('active', $customer->status);
        $this->assertSame('2026-01-05', $customer->start_date?->toDateString());
        $this->assertSame(150000, $customer->first_bill_amount);
        $this->assertSame('2026-11-05', $customer->due_date?->toDateString());
        $this->assertNull($customer->stopped_at);
        $this->assertSame('2026-10-12', $customer->reactivated_at?->toDateString());

        $invoice = Invoice::query()
            ->where('pppoe_customer_id', $customer->id)
            ->where('status', 'unpaid')
            ->first();

        $this->assertSame('prorata', $invoice?->type);
        $this->assertSame('2026-10-12', $invoice?->period_start?->toDateString());
        $this->assertSame('2026-11-05', $invoice?->due_date?->toDateString());
        $this->assertSame(117000, $invoice?->total);
        $this->assertStringContainsString('Aktivasi kembali', (string) $invoice?->notes);
    }

    /**
     * @return array{0: User, 1: MikrotikRouter, 2: SubscriptionPackage}
     */
    private function setupAdminRouter(): array
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

        return [$admin, $router, $package];
    }

    private function upgradePackage(
        MikrotikRouter $router,
        int $price,
        string $profile,
        string $name = '20 Mbps',
    ): SubscriptionPackage {
        return SubscriptionPackage::query()->create([
            'mikrotik_router_id' => $router->id,
            'name' => $name,
            'price' => $price,
            'mikrotik_profile' => $profile,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(MikrotikRouter $router, SubscriptionPackage $package, array $overrides = []): PppoeCustomer
    {
        return PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi Santoso',
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-01-20',
            'billing_day' => 20,
            'due_date' => '2026-10-20',
            'first_bill_amount' => 150000,
            'first_bill_days' => 31,
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    private function monthlyInvoice(
        PppoeCustomer $customer,
        SubscriptionPackage $package,
        string $periodStart,
        string $due,
        int $total,
    ): Invoice {
        return Invoice::query()->create([
            'number' => 'INV/2026/10/'.str_pad((string) (Invoice::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => $periodStart,
            'period_end' => $due,
            'due_date' => $due,
            'amount' => $total,
            'discount' => 0,
            'total' => $total,
            'status' => 'unpaid',
            'package_name' => $package->name,
            'package_price' => $package->price,
        ]);
    }

    private function paidInvoice(
        PppoeCustomer $customer,
        SubscriptionPackage $package,
        string $periodStart,
        string $due,
        int $total,
        string $number,
    ): Invoice {
        return Invoice::query()->create([
            'number' => $number,
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => $periodStart,
            'period_end' => $due,
            'due_date' => $due,
            'amount' => $total,
            'discount' => 0,
            'total' => $total,
            'status' => 'paid',
            'paid_at' => now(),
            'package_name' => $package->name,
            'package_price' => $package->price,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(MikrotikRouter $router, SubscriptionPackage $package, array $overrides = []): array
    {
        return array_merge([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi Santoso',
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-01-20',
            'due_date' => '2026-10-20',
            'billing_day' => 20,
            'overdue_action' => 'bypass',
            'is_active' => 1,
        ], $overrides);
    }

    private function mockSecret(
        string $profile = '10Mbps',
        bool $disabled = false,
        bool $disconnect = false,
    ): void {
        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->once()
            ->withArgs(function (...$args) use ($profile, $disabled, $disconnect) {
                return ($args[3] ?? null) === $profile
                    && ($args[5] ?? null) === $disabled
                    && ($args[6] ?? null) === $disconnect;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);
    }
}
