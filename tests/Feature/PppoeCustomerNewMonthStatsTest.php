<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PppoeCustomerNewMonthStatsTest extends TestCase
{
    use RefreshDatabase;

    private string $previousTimezone = 'UTC';

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->previousTimezone);
        parent::tearDown();
    }

    #[Test]
    public function new_customer_card_counts_only_the_current_month_and_resets_next_month(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse('2026-10-04 10:00:00', 'Asia/Jakarta'));

        [$admin, $router, $package] = $this->setupAdminRouter();
        $otherRouter = MikrotikRouter::query()->create([
            'name' => 'Router 2',
            'host' => '192.168.88.2',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $this->customer($router, $package, [
            'name' => 'Masuk awal bulan',
            'username' => 'baru-awal',
            'start_date' => '2026-10-01',
        ]);
        $this->customer($router, $package, [
            'name' => 'Masuk hari ini',
            'username' => 'baru-hari-ini',
            'start_date' => '2026-10-04',
        ]);
        $this->customer($router, $package, [
            'name' => 'Pelanggan lama',
            'username' => 'lama',
            'start_date' => '2026-09-30',
        ]);
        $this->customer($otherRouter, $package, [
            'name' => 'Router lain',
            'username' => 'router-lain',
            'mikrotik_router_id' => $otherRouter->id,
            'start_date' => '2026-10-02',
        ]);

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Customers/Pppoe/Index')
                ->where('stats.total', 4)
                ->where('stats.new_this_month', 3)
                ->where('stats.new_month_label', 'Oktober 2026')
            );

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe?router_id='.$router->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 3)
                ->where('stats.new_this_month', 2)
                ->where('stats.new_month_label', 'Oktober 2026')
            );

        Carbon::setTestNow(Carbon::parse('2026-11-01 00:05:00', 'Asia/Jakarta'));

        $this->actingAs($admin)
            ->get('/admin/customers/pppoe?router_id=')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 4)
                ->where('stats.new_this_month', 0)
                ->where('stats.new_month_label', 'November 2026')
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
