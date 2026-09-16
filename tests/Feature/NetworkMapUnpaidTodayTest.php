<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\User;
use App\Services\MikrotikApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NetworkMapUnpaidTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mockSessions(): void
    {
        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('listPppActiveSessions')->andReturn([
            'ok' => true,
            'sessions' => [],
        ]);
        $this->app->instance(MikrotikApiService::class, $api);
    }

    private function router(): MikrotikRouter
    {
        return MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
    }

    private function customer(MikrotikRouter $router, array $overrides = []): PppoeCustomer
    {
        return PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'name' => 'Pelanggan',
            'username' => 'user01',
            'password' => 'secret',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    private function invoice(PppoeCustomer $customer, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'number' => 'INV-'.$customer->username,
            'pppoe_customer_id' => $customer->id,
            'type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->toDateString(),
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
        ], $overrides));
    }

    #[Test]
    public function map_marks_customers_who_have_not_paid_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', 'Asia/Jakarta'));
        $this->mockSessions();

        $admin = User::factory()->superadmin()->create();
        $router = $this->router();

        $unpaid = $this->customer($router, [
            'name' => 'Andi Unpaid',
            'username' => 'andi01',
        ]);
        $unpaidInvoice = $this->invoice($unpaid);

        $paidToday = $this->customer($router, [
            'name' => 'Budi Paid',
            'username' => 'budi01',
            'due_date' => now()->addMonth()->toDateString(),
        ]);
        $this->invoice($paidToday, [
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $dueToday = $this->customer($router, [
            'name' => 'Citra Due',
            'username' => 'citra01',
            'due_date' => now()->toDateString(),
        ]);

        $current = $this->customer($router, [
            'name' => 'Dewi Current',
            'username' => 'dewi01',
            'due_date' => now()->addDays(20)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/network/map')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Network/Map')
                ->where('stats.unpaid_today', 2)
                ->where('customers.0.id', $unpaid->id)
                ->where('customers.0.unpaid_today', true)
                ->where('customers.1.id', $paidToday->id)
                ->where('customers.1.unpaid_today', false)
                ->where('customers.2.id', $dueToday->id)
                ->where('customers.2.unpaid_today', true)
                ->where('customers.3.id', $current->id)
                ->where('customers.3.unpaid_today', false)
                ->where('customers.0.unpaid_invoices.0.id', $unpaidInvoice->id)
                ->where('customers.0.unpaid_invoices.0.status', 'unpaid')
                ->where('customers.1.unpaid_invoices', [])
                ->where('customers.2.unpaid_invoices', [])
                ->where('customers.3.unpaid_invoices', [])
                ->has('payment_methods', 4)
                ->where('payment_methods.0.value', 'cash')
                ->has('routers', 1)
                ->where('filters.router_id', '')
            );
    }

    #[Test]
    public function map_can_filter_customers_by_selected_routeros(): void
    {
        $this->mockSessions();

        $admin = User::factory()->superadmin()->create();
        $routerA = $this->router();
        $routerB = MikrotikRouter::query()->create([
            'name' => 'Router 2',
            'host' => '192.168.88.2',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $onRouterA = $this->customer($routerA, [
            'name' => 'Pelanggan A',
            'username' => 'user-a',
        ]);
        $this->customer($routerB, [
            'name' => 'Pelanggan B',
            'username' => 'user-b',
        ]);

        $this->actingAs($admin)
            ->get('/admin/network/map?router_id='.$routerA->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Network/Map')
                ->where('filters.router_id', (string) $routerA->id)
                ->has('customers', 1)
                ->where('customers.0.id', $onRouterA->id)
                ->where('stats.total', 1)
                ->has('routers', 2)
            );
    }
}
