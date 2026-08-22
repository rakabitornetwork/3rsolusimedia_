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
}
