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

class PppoeCustomerBulkDisableTest extends TestCase
{
    use RefreshDatabase;

    private string $previousTimezone = 'UTC';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousTimezone = date_default_timezone_get();
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse('2026-10-12 09:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->previousTimezone);
        parent::tearDown();
    }

    #[Test]
    public function selected_pppoe_customers_are_disabled_and_their_secrets_are_turned_off(): void
    {
        [$admin, $router, $package] = $this->setupAdminRouter();
        $first = $this->customer($router, $package, ['name' => 'Satu', 'username' => 'aktif-satu']);
        $second = $this->customer($router, $package, ['name' => 'Dua', 'username' => 'aktif-dua']);
        $alreadyOff = $this->customer($router, $package, [
            'name' => 'Sudah mati',
            'username' => 'sudah-mati',
            'is_active' => false,
            'status' => 'disabled',
            'stopped_at' => '2026-09-01',
        ]);
        $blocked = $this->customer($router, $package, ['name' => 'Gabungan', 'username' => 'gabungan']);
        $vpn = $this->customer($router, $package, [
            'name' => 'VPN',
            'username' => 'vpn-user',
            'ppp_service' => PppoeCustomer::SERVICE_L2TP,
        ]);

        $open = $this->invoice($first, $package, 'monthly', 1, 'unpaid');
        $this->invoice($blocked, $package, 'multi_month', 2, 'unpaid');

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->twice()
            ->withArgs(function (...$args) {
                return in_array($args[1] ?? null, ['aktif-satu', 'aktif-dua'], true)
                    && ($args[5] ?? null) === true
                    && ($args[6] ?? null) === false;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->actingAs($admin)
            ->post('/admin/customers/pppoe/bulk-disable', [
                'ids' => [$first->id, $second->id, $alreadyOff->id, $blocked->id, $vpn->id],
                'settle_billing' => 1,
            ])
            ->assertRedirect('/admin/customers/pppoe')
            ->assertSessionHas('error');

        $first->refresh();
        $second->refresh();
        $alreadyOff->refresh();
        $blocked->refresh();
        $vpn->refresh();
        $open->refresh();

        $this->assertFalse($first->is_active);
        $this->assertSame('disabled', $first->status);
        $this->assertSame('2026-10-12', $first->stopped_at?->toDateString());
        $this->assertSame('synced', $first->sync_status);
        $this->assertFalse($second->is_active);
        $this->assertSame('disabled', $second->status);
        $this->assertSame('2026-09-01', $alreadyOff->stopped_at?->toDateString());
        $this->assertTrue($blocked->is_active);
        $this->assertSame('active', $blocked->status);
        $this->assertTrue($vpn->is_active);
        $this->assertSame('void', $open->status);
        $this->assertSame(1, Invoice::query()->where('pppoe_customer_id', $first->id)->where('type', 'adjustment')->count());
    }

    #[Test]
    public function disabling_without_billing_leaves_existing_invoices_unchanged(): void
    {
        [$admin, $router, $package] = $this->setupAdminRouter();
        $customer = $this->customer($router, $package, ['name' => 'Satu', 'username' => 'aktif-satu']);
        $open = $this->invoice($customer, $package, 'monthly', 1, 'unpaid');

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->once()
            ->withArgs(function (...$args) {
                return ($args[1] ?? null) === 'aktif-satu'
                    && ($args[5] ?? null) === true;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->actingAs($admin)
            ->post('/admin/customers/pppoe/bulk-disable', [
                'ids' => [$customer->id],
                'settle_billing' => 0,
            ])
            ->assertRedirect('/admin/customers/pppoe')
            ->assertSessionHas('success');

        $customer->refresh();
        $open->refresh();

        $this->assertFalse($customer->is_active);
        $this->assertSame('disabled', $customer->status);
        $this->assertSame('2026-10-12', $customer->stopped_at?->toDateString());
        $this->assertSame('unpaid', $open->status);
        $this->assertSame(150000, (int) $open->total);
        $this->assertSame(0, Invoice::query()->where('pppoe_customer_id', $customer->id)->where('type', 'adjustment')->count());
    }

    #[Test]
    public function agent_cannot_disable_customers_in_bulk(): void
    {
        $agent = User::factory()->agen()->create();
        [$admin, $router, $package] = $this->setupAdminRouter();
        unset($admin);
        $customer = $this->customer($router, $package, ['username' => 'milik-agen', 'agent_id' => $agent->id]);

        $this->actingAs($agent)
            ->post('/admin/customers/pppoe/bulk-disable', [
                'ids' => [$customer->id],
            ])
            ->assertRedirect('/admin/customers/pppoe')
            ->assertSessionHas('error');

        $this->assertTrue($customer->fresh()->is_active);
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
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    private function invoice(
        PppoeCustomer $customer,
        SubscriptionPackage $package,
        string $type,
        int $months,
        string $status,
    ): Invoice {
        return Invoice::query()->create([
            'number' => 'INV/2026/10/'.str_pad((string) (Invoice::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => $type,
            'billing_months' => $months,
            'period_start' => '2026-09-20',
            'period_end' => '2026-10-20',
            'due_date' => '2026-10-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => $status,
            'package_name' => $package->name,
            'package_price' => $package->price,
        ]);
    }
}
