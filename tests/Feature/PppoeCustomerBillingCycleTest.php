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

class PppoeCustomerBillingCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function storing_customer_keeps_explicit_september_due_when_start_is_august(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $this->mockSecretUpsert();

        $this->actingAs($admin)
            ->from('/admin/customers/pppoe/create')
            ->post('/admin/customers/pppoe', $this->payload($router, $package, [
                'start_date' => '2026-08-20',
                'due_date' => '2026-09-20',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer = PppoeCustomer::query()->where('username', 'budi01')->first();

        $this->assertNotNull($customer);
        $this->assertSame('2026-08-20', $customer->start_date?->toDateString());
        $this->assertSame('2026-09-20', $customer->due_date?->toDateString());
        $this->assertSame(20, $customer->billing_day);

        $invoice = Invoice::query()->where('pppoe_customer_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('prorata', $invoice->type);
        $this->assertSame('2026-09-20', $invoice->due_date?->toDateString());
    }

    #[Test]
    public function updating_due_date_moves_october_back_to_september(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $this->mockSecretUpsert();

        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi Santoso',
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-08-20',
            'billing_day' => 20,
            'due_date' => '2026-10-20',
            'first_bill_amount' => 155000,
            'first_bill_days' => 61,
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'number' => 'INV/2026/09/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'prorata',
            'billing_months' => 1,
            'period_start' => '2026-08-20',
            'period_end' => '2026-10-20',
            'due_date' => '2026-10-20',
            'amount' => 155000,
            'discount' => 0,
            'total' => 155000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $this->actingAs($admin)
            ->from('/admin/customers/pppoe/'.$customer->id.'/edit')
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $package, [
                'start_date' => '2026-08-20',
                'due_date' => '2026-09-20',
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $customer->refresh();
        $this->assertSame('2026-09-20', $customer->due_date?->toDateString());
        $this->assertSame(20, $customer->billing_day);
        $this->assertSame(31, $customer->first_bill_days);

        $invoice = Invoice::query()->where('pppoe_customer_id', $customer->id)->first();
        $this->assertSame('2026-09-20', $invoice?->due_date?->toDateString());
        $this->assertSame('2026-09-20', $invoice?->period_end?->toDateString());
    }

    #[Test]
    public function updating_due_date_to_today_creates_invoice_when_none_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $this->mockSecretUpsert();

        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi Santoso',
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-08-20',
            'billing_day' => 20,
            'due_date' => '2026-10-20',
            'first_bill_amount' => 155000,
            'first_bill_days' => 61,
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);

        $this->assertSame(0, Invoice::query()->count());

        $this->actingAs($admin)
            ->from('/admin/customers/pppoe/'.$customer->id.'/edit')
            ->put('/admin/customers/pppoe/'.$customer->id, $this->payload($router, $package, [
                'start_date' => '2026-08-20',
                'due_date' => '2026-09-20',
                'password' => '',
            ]))
            ->assertRedirect('/admin/customers/pppoe');

        $invoice = Invoice::query()->where('pppoe_customer_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('2026-09-20', $invoice->due_date?->toDateString());
        $this->assertSame('prorata', $invoice->type);
    }

    #[Test]
    public function billing_page_generates_invoice_when_due_date_is_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();

        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi Santoso',
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-08-20',
            'billing_day' => 20,
            'due_date' => '2026-09-20',
            'first_bill_amount' => 150000,
            'first_bill_days' => 31,
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/billing')
            ->assertOk();

        $invoice = Invoice::query()->where('pppoe_customer_id', $customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('2026-09-20', $invoice->due_date?->toDateString());
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

    private function mockSecretUpsert(): void
    {
        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')->andReturn([
            'ok' => true,
            'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
        ]);
        $this->app->instance(MikrotikApiService::class, $api);
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
            'start_date' => '2026-08-20',
            'due_date' => '2026-09-20',
            'billing_day' => 20,
            'overdue_action' => 'bypass',
            'is_active' => 1,
        ], $overrides);
    }
}
