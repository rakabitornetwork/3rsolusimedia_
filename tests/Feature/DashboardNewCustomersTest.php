<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\GitUpdateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardNewCustomersTest extends TestCase
{
    use RefreshDatabase;

    private string $previousTimezone = 'UTC';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousTimezone = date_default_timezone_get();
        $this->mock(GitUpdateService::class, function ($mock) {
            $mock->shouldReceive('dashboardNotice')->andReturn(null);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->previousTimezone);
        parent::tearDown();
    }

    #[Test]
    public function dashboard_shows_new_customer_chart_and_resets_the_list_next_month(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();

        $this->customer($router, $package, [
            'name' => 'Masuk awal',
            'username' => 'baru-awal',
            'start_date' => '2026-10-01',
        ]);
        $today = $this->customer($router, $package, [
            'name' => 'Masuk hari ini',
            'username' => 'baru-hari-ini',
            'start_date' => '2026-10-04',
        ]);
        $this->customer($router, $package, [
            'name' => 'Pelanggan lama',
            'username' => 'lama',
            'start_date' => '2026-09-15',
        ]);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('new_customers.month_label', 'Oktober 2026')
                ->where('new_customers.total_this_month', 2)
                ->has('new_customers.recent', 2)
                ->where('new_customers.recent.0.id', $today->id)
                ->where('new_customers.recent.0.username', 'baru-hari-ini')
                ->where('new_customers.recent.0.package', '10 Mbps')
                ->where('new_customers.recent.0.start_label', '4 Okt 2026')
                ->where('new_customers.recent.0.status_label', 'Aktif')
                ->where('new_customers.recent.1.username', 'baru-awal')
                ->where('new_customers.charts.daily.points.0.total', 1)
                ->where('new_customers.charts.daily.points.3.total', 1)
                ->where('new_customers.charts.daily.points.2.total', 0)
                ->where('new_customers.charts.monthly.points.4.total', 1)
                ->where('new_customers.charts.monthly.points.4.label', 'Sep 2026')
                ->where('new_customers.charts.monthly.points.5.total', 2)
                ->where('new_customers.charts.monthly.points.5.label', 'Okt 2026')
            );

        Carbon::setTestNow(Carbon::parse('2026-11-01 00:05:00', 'Asia/Jakarta'));

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('new_customers.month_label', 'November 2026')
                ->where('new_customers.total_this_month', 0)
                ->has('new_customers.recent', 0)
                ->where('new_customers.charts.monthly.points.4.total', 2)
                ->where('new_customers.charts.monthly.points.4.label', 'Okt 2026')
                ->where('new_customers.charts.monthly.points.5.total', 0)
                ->where('new_customers.charts.monthly.points.5.label', 'Nov 2026')
            );
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
}
