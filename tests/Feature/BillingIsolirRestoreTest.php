<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Services\BillingService;
use App\Services\MikrotikApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingIsolirRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function paying_two_months_at_once_restores_isolated_customer_to_service_profile(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 11:00:00', 'Asia/Jakarta'));

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

        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi Santoso',
            'username' => 'budi01',
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
        ]);

        $this->assertTrue($customer->shouldIsolir());

        $invoice = Invoice::query()->create([
            'number' => 'INV/2026/08/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'multi_month',
            'billing_months' => 2,
            'period_start' => '2026-05-20',
            'period_end' => '2026-07-20',
            'due_date' => '2026-06-20',
            'amount' => 300000,
            'discount' => 0,
            'total' => 300000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->once()
            ->withArgs(function (
                $passedRouter,
                string $username,
                string $password,
                ?string $profile,
                ?string $comment,
                bool $disabled,
                bool $disconnectActive,
            ) {
                return $username === 'budi01'
                    && $profile === '10Mbps'
                    && $disabled === false
                    && $disconnectActive === true;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $result = app(BillingService::class)->markPaid($invoice);

        $customer->refresh();

        $this->assertSame('2026-10-20', $result['next_due_date']);
        $this->assertSame('2026-10-20', $customer->due_date?->toDateString());
        $this->assertFalse($customer->shouldIsolir());
        $this->assertSame('active', $customer->status);
    }

    #[Test]
    public function two_month_tempo_without_payment_restores_isolated_profile(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 11:00:00', 'Asia/Jakarta'));

        [$customer] = $this->isolatedCustomer();

        $this->mockProfileRestore('10Mbps');

        $updated = app(BillingService::class)->grantGrace(
            $customer,
            now()->startOfDay()->addMonthsNoOverflow(2),
            'Tempo 2 bulan',
        );

        $this->assertSame('2026-10-22', $updated->grace_until?->toDateString());
        $this->assertSame('2026-06-20', $updated->due_date?->toDateString());
        $this->assertTrue($updated->hasActiveGrace());
        $this->assertFalse($updated->shouldIsolir());
        $this->assertSame('active', $updated->status);
        $this->assertFalse(
            Invoice::query()->where('pppoe_customer_id', $customer->id)->where('status', 'paid')->exists()
        );
    }

    #[Test]
    public function combining_two_months_for_isolated_customer_grants_tempo_and_restores_profile(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 11:00:00', 'Asia/Jakarta'));

        [$customer] = $this->isolatedCustomer();

        $this->mockProfileRestore('10Mbps');

        $invoice = app(BillingService::class)->createCombinedMonthlyInvoice($customer, 2);
        $customer->refresh();

        $this->assertSame('multi_month', $invoice->type);
        $this->assertSame(2, $invoice->billing_months);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('2026-10-22', $customer->grace_until?->toDateString());
        $this->assertSame('2026-06-20', $customer->due_date?->toDateString());
        $this->assertFalse($customer->shouldIsolir());
        $this->assertSame('active', $customer->status);
    }

    #[Test]
    public function grace_until_a_later_cycle_bills_every_due_date_at_the_package_price(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 01:00:00', 'Asia/Jakarta'));

        [$customer, $package] = $this->isolatedCustomer([
            'due_date' => '2026-11-03',
            'billing_day' => 3,
            'status' => 'isolated',
        ]);
        $package->update(['price' => 120000]);

        Invoice::query()->create([
            'number' => 'INV/2026/09/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-08-03',
            'period_end' => '2026-09-03',
            'due_date' => '2026-09-03',
            'amount' => 120000,
            'discount' => 0,
            'total' => 120000,
            'status' => 'paid',
            'paid_at' => '2026-09-04 10:00:00',
            'package_name' => '10 Mbps',
            'package_price' => 120000,
        ]);

        Invoice::query()->create([
            'number' => 'INV/2026/10/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-10-03',
            'period_end' => '2026-11-03',
            'due_date' => '2026-11-03',
            'amount' => 120000,
            'discount' => 0,
            'total' => 120000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 120000,
        ]);

        $this->mockProfileRestore('10Mbps');

        $updated = app(BillingService::class)->grantGrace(
            $customer->fresh('package'),
            Carbon::parse('2026-11-11'),
            'Sampai 11 November',
            coverOpenInvoice: true,
        );

        $open = Invoice::query()->where('pppoe_customer_id', $customer->id)->where('status', 'unpaid')->first();

        $this->assertSame('2026-11-11', $updated->grace_until?->toDateString());
        $this->assertSame('2026-11-03', $updated->due_date?->toDateString());
        $this->assertNotNull($open);
        $this->assertSame(2, $open->billing_months);
        $this->assertSame(240000, $open->total);
        $this->assertSame('2026-10-03', $open->due_date?->toDateString());
        $this->assertSame('2026-09-03', $open->period_start?->toDateString());
        $this->assertSame('2026-11-03', $open->period_end?->toDateString());
        $this->assertSame(1, Invoice::query()->where('pppoe_customer_id', $customer->id)->where('status', 'void')->count());
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

    private function mockProfileRestore(string $profile): void
    {
        $api = Mockery::mock(MikrotikApiService::class);
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
                    && $disabled === false
                    && $disconnectActive === true;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);
    }
}
