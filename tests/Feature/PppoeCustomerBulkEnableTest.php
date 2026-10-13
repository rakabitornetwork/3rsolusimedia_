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

class PppoeCustomerBulkEnableTest extends TestCase
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
    public function selected_inactive_pppoe_customers_are_enabled_and_their_secrets_are_turned_on(): void
    {
        [$admin, $router, $package] = $this->setupAdminRouter();
        $first = $this->customer($router, $package, [
            'name' => 'Satu',
            'username' => 'mati-satu',
            'is_active' => false,
            'status' => 'disabled',
            'stopped_at' => '2026-09-01',
        ]);
        $second = $this->customer($router, $package, [
            'name' => 'Dua',
            'username' => 'mati-dua',
            'is_active' => false,
            'status' => 'disabled',
            'stopped_at' => '2026-09-15',
            'due_date' => '2026-09-20',
        ]);
        $alreadyOn = $this->customer($router, $package, [
            'name' => 'Sudah hidup',
            'username' => 'sudah-hidup',
        ]);
        $vpn = $this->customer($router, $package, [
            'name' => 'VPN',
            'username' => 'vpn-user',
            'ppp_service' => PppoeCustomer::SERVICE_L2TP,
            'is_active' => false,
            'status' => 'disabled',
        ]);

        $open = $this->invoice($first, $package, 'monthly', 'unpaid');

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->twice()
            ->withArgs(function (...$args) {
                return in_array($args[1] ?? null, ['mati-satu', 'mati-dua'], true)
                    && ($args[5] ?? null) === false
                    && ($args[6] ?? null) === true;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->actingAs($admin)
            ->post('/admin/customers/pppoe/bulk-enable', [
                'ids' => [$first->id, $second->id, $alreadyOn->id, $vpn->id],
                'create_invoice' => 1,
            ])
            ->assertRedirect('/admin/customers/pppoe')
            ->assertSessionHas('success');

        $first->refresh();
        $second->refresh();
        $alreadyOn->refresh();
        $vpn->refresh();
        $open->refresh();

        $this->assertTrue($first->is_active);
        $this->assertSame('active', $first->status);
        $this->assertNull($first->stopped_at);
        $this->assertSame('2026-10-12', $first->reactivated_at?->toDateString());
        $this->assertSame('2026-10-20', $first->due_date?->toDateString());
        $this->assertSame('synced', $first->sync_status);
        $this->assertTrue($second->is_active);
        $this->assertSame('2026-10-20', $second->due_date?->toDateString());
        $this->assertSame('2026-10-12', $second->reactivated_at?->toDateString());
        $this->assertTrue($alreadyOn->is_active);
        $this->assertFalse($vpn->is_active);
        $this->assertSame('unpaid', $open->status);
        $this->assertSame(2, Invoice::query()->where('type', 'prorata')->where('notes', 'Aktivasi kembali (prorata)')->count());
        $this->assertSame(
            1,
            Invoice::query()->where('pppoe_customer_id', $first->id)->where('notes', 'Aktivasi kembali (prorata)')->count(),
        );
        $secondInvoice = Invoice::query()
            ->where('pppoe_customer_id', $second->id)
            ->where('type', 'prorata')
            ->first();
        $this->assertSame('2026-10-12', $secondInvoice?->period_start?->toDateString());
        $this->assertSame('2026-10-20', $secondInvoice?->due_date?->toDateString());
    }

    #[Test]
    public function enabling_without_invoice_leaves_existing_invoices_and_due_date_unchanged(): void
    {
        [$admin, $router, $package] = $this->setupAdminRouter();
        $customer = $this->customer($router, $package, [
            'name' => 'Satu',
            'username' => 'mati-satu',
            'is_active' => false,
            'status' => 'disabled',
            'stopped_at' => '2026-09-01',
            'due_date' => '2026-09-20',
        ]);
        $open = $this->invoice($customer, $package, 'monthly', 'unpaid');

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->once()
            ->withArgs(function (...$args) {
                return ($args[1] ?? null) === 'mati-satu'
                    && ($args[5] ?? null) === false;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->actingAs($admin)
            ->post('/admin/customers/pppoe/bulk-enable', [
                'ids' => [$customer->id],
                'create_invoice' => 0,
            ])
            ->assertRedirect('/admin/customers/pppoe')
            ->assertSessionHas('success');

        $customer->refresh();
        $open->refresh();

        $this->assertTrue($customer->is_active);
        $this->assertSame('active', $customer->status);
        $this->assertNull($customer->stopped_at);
        $this->assertSame('2026-09-20', $customer->due_date?->toDateString());
        $this->assertSame('unpaid', $open->status);
        $this->assertSame(150000, (int) $open->total);
        $this->assertSame(0, Invoice::query()->where('pppoe_customer_id', $customer->id)->where('type', 'prorata')->count());
    }

    #[Test]
    public function agent_cannot_enable_customers_in_bulk(): void
    {
        $agent = User::factory()->agen()->create();
        [$admin, $router, $package] = $this->setupAdminRouter();
        unset($admin);
        $customer = $this->customer($router, $package, [
            'username' => 'milik-agen',
            'agent_id' => $agent->id,
            'is_active' => false,
            'status' => 'disabled',
        ]);

        $this->actingAs($agent)
            ->post('/admin/customers/pppoe/bulk-enable', [
                'ids' => [$customer->id],
            ])
            ->assertRedirect('/admin/customers/pppoe')
            ->assertSessionHas('error');

        $this->assertFalse($customer->fresh()->is_active);
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
        string $status,
    ): Invoice {
        return Invoice::query()->create([
            'number' => 'INV/2026/10/'.str_pad((string) (Invoice::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => $type,
            'billing_months' => 1,
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
