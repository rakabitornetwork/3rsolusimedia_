<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\PppoeMonthlyUsage;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\MikrotikApiService;
use App\Services\PppoeMonthlyUsageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PppoeMonthlyUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function usage_accumulates_deltas_and_resets_on_the_next_month(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        $customer = $this->customer();
        $service = app(PppoeMonthlyUsageService::class);

        $service->applySample($customer, 1_000, 5_000, Carbon::parse('2026-10-07 10:00:00', 'Asia/Jakarta'));

        $october = PppoeMonthlyUsage::query()->where('period', '2026-10')->first();
        $this->assertNotNull($october);
        $this->assertSame(0, (int) $october->rx_bytes);
        $this->assertSame(0, (int) $october->tx_bytes);

        $service->applySample($customer, 1_500, 8_000, Carbon::parse('2026-10-07 10:05:00', 'Asia/Jakarta'));

        $october->refresh();
        $this->assertSame(3_000, (int) $october->rx_bytes);
        $this->assertSame(500, (int) $october->tx_bytes);

        $service->applySample($customer, 200, 100, Carbon::parse('2026-11-01 00:05:00', 'Asia/Jakarta'));

        $october->refresh();
        $november = PppoeMonthlyUsage::query()->where('period', '2026-11')->first();
        $this->assertSame(3_000, (int) $october->rx_bytes);
        $this->assertSame(500, (int) $october->tx_bytes);
        $this->assertNotNull($november);
        $this->assertSame(100, (int) $november->rx_bytes);
        $this->assertSame(200, (int) $november->tx_bytes);
    }

    #[Test]
    public function collect_reads_router_counters_for_matching_customers_only(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-10-07 11:00:00', 'Asia/Jakarta'));

        $router = $this->router();
        $customer = $this->customer($router, ['username' => 'Budi01']);
        $otherRouter = $this->router('Router 2', '10.0.0.2');
        $otherRouter->update(['is_active' => false]);
        $other = $this->customer($otherRouter, ['username' => 'lain']);

        $api = $this->mock(MikrotikApiService::class);
        $api->shouldReceive('pppoeInterfaceBytesMap')
            ->twice()
            ->andReturn(
                ['budi01' => ['rx_byte' => 1000, 'tx_byte' => 4000]],
                ['budi01' => ['rx_byte' => 1800, 'tx_byte' => 9000]],
            );

        $service = app(PppoeMonthlyUsageService::class);
        $service->collect();
        $service->collect();

        $usage = PppoeMonthlyUsage::query()->where('pppoe_customer_id', $customer->id)->first();
        $this->assertSame(5_000, (int) $usage?->rx_bytes);
        $this->assertSame(800, (int) $usage?->tx_bytes);
        $this->assertNull(PppoeMonthlyUsage::query()->where('pppoe_customer_id', $other->id)->first());
    }

    #[Test]
    public function customer_list_and_portal_show_the_current_month_usage(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-10-07 11:00:00', 'Asia/Jakarta'));

        $this->mock(MikrotikApiService::class, function ($mock) {
            $mock->shouldReceive('listPppProfiles')->andReturn([
                'ok' => true,
                'profiles' => [],
                'isolir_profiles' => [],
            ]);
        });

        $admin = User::factory()->superadmin()->create();
        $customer = $this->customer();
        $service = app(PppoeMonthlyUsageService::class);
        $service->applySample($customer, 0, 0, now());
        $service->applySample($customer, 1024, 2048, now()->addMinute());

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Pppoe/Index', false)
                ->where('customers.0.monthly_usage.rx_bytes', 2048)
                ->where('customers.0.monthly_usage.tx_bytes', 1024)
                ->where('customers.0.monthly_usage.period', '2026-10')
                ->where('customers.0.monthly_usage.has_sample', true)
            );

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe/'.$customer->id.'/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Pppoe/Form', false)
                ->where('customer.monthly_usage.rx_label', '2 KB')
                ->where('customer.monthly_usage.tx_label', '1 KB')
            );

        $token = Str::lower(Str::random(48));
        Cache::put('portal_pay:'.$token, $customer->id, now()->addHour());

        $this->get('/portal/'.$token)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Home', false)
                ->where('usage.rx_bytes', 2048)
                ->where('usage.tx_bytes', 1024)
                ->where('usage.period_label', 'Oktober 2026')
            );
    }

    #[Test]
    public function customer_list_sorts_by_current_month_usage_total(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        Carbon::setTestNow(Carbon::parse('2026-10-07 11:00:00', 'Asia/Jakarta'));

        $admin = User::factory()->superadmin()->create();
        $router = $this->router();
        $light = $this->customer($router, ['name' => 'Ringan', 'username' => 'ringan']);
        $heavy = $this->customer($router, ['name' => 'Berat', 'username' => 'berat']);
        $none = $this->customer($router, ['name' => 'Kosong', 'username' => 'kosong']);

        PppoeMonthlyUsage::query()->create([
            'pppoe_customer_id' => $light->id,
            'period' => '2026-10',
            'rx_bytes' => 100,
            'tx_bytes' => 50,
        ]);
        PppoeMonthlyUsage::query()->create([
            'pppoe_customer_id' => $heavy->id,
            'period' => '2026-09',
            'rx_bytes' => 9_000_000,
            'tx_bytes' => 9_000_000,
        ]);
        PppoeMonthlyUsage::query()->create([
            'pppoe_customer_id' => $heavy->id,
            'period' => '2026-10',
            'rx_bytes' => 8_000,
            'tx_bytes' => 2_000,
        ]);

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe?sort=usage&direction=desc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Pppoe/Index', false)
                ->where('filters.sort', 'usage')
                ->where('filters.direction', 'desc')
                ->where('customers.0.username', $heavy->username)
                ->where('customers.1.username', $light->username)
                ->where('customers.2.username', $none->username)
            );

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe?sort=usage&direction=asc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Pppoe/Index', false)
                ->where('customers.0.username', $none->username)
                ->where('customers.1.username', $light->username)
                ->where('customers.2.username', $heavy->username)
            );
    }

    private function router(string $name = 'Router 1', string $host = '192.168.88.1'): MikrotikRouter
    {
        return MikrotikRouter::query()->create([
            'name' => $name,
            'host' => $host,
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(?MikrotikRouter $router = null, array $overrides = []): PppoeCustomer
    {
        $router ??= $this->router();
        $package = SubscriptionPackage::query()->firstOrCreate(
            [
                'mikrotik_router_id' => $router->id,
                'name' => '10 Mbps',
            ],
            [
                'price' => 150000,
                'mikrotik_profile' => '10Mbps',
                'is_active' => true,
            ],
        );

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
}
