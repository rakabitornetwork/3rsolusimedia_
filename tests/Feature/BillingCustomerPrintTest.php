<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingCustomerPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function print_lists_unique_customers_from_filtered_unpaid_invoices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'Asia/Jakarta'));

        $admin = User::factory()->superadmin()->create();
        $router = $this->router();
        $andi = $this->customer($router, ['name' => 'Andi Wijaya', 'username' => 'andi01']);
        $budi = $this->customer($router, ['name' => 'Budi Santoso', 'username' => 'budi01']);
        $citra = $this->customer($router, ['name' => 'Citra Lestari', 'username' => 'citra01']);

        $this->invoice($andi, ['number' => 'INV-UNPAID-ANDI', 'status' => 'unpaid', 'total' => 150000]);
        $this->invoice($andi, ['number' => 'INV-PAID-ANDI', 'status' => 'paid', 'total' => 140000, 'paid_at' => now()]);
        $this->invoice($budi, ['number' => 'INV-UNPAID-BUDI', 'status' => 'unpaid', 'total' => 175000]);
        $this->invoice($citra, ['number' => 'INV-PAID-CITRA', 'status' => 'paid', 'total' => 160000, 'paid_at' => now()]);

        $html = $this->actingAs($admin)
            ->get('/admin/billing/print?status=unpaid&hide_old_paid=0')
            ->assertOk()
            ->assertSee('Daftar Tagihan Pelanggan')
            ->assertSee('Andi Wijaya')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Citra Lestari')
            ->getContent();

        $this->assertTrue(strpos($html, 'Andi Wijaya') < strpos($html, 'Budi Santoso'));
        $this->assertSame(1, substr_count($html, 'Andi Wijaya'));
    }

    #[Test]
    public function print_respects_overdue_and_search_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 13:00:00', 'Asia/Jakarta'));

        $admin = User::factory()->superadmin()->create();
        $router = $this->router();
        $late = $this->customer($router, ['name' => 'Lina Overdue', 'username' => 'lina01']);
        $soon = $this->customer($router, ['name' => 'Sari OnTime', 'username' => 'sari01']);

        $this->invoice($late, [
            'number' => 'INV-LATE',
            'status' => 'unpaid',
            'due_date' => '2026-09-10',
        ]);
        $this->invoice($soon, [
            'number' => 'INV-SOON',
            'status' => 'unpaid',
            'due_date' => '2026-09-20',
        ]);

        $this->actingAs($admin)
            ->get('/admin/billing/print?overdue=1&hide_old_paid=0')
            ->assertOk()
            ->assertSee('Lina Overdue')
            ->assertDontSee('Sari OnTime');

        $this->actingAs($admin)
            ->get('/admin/billing/print?q=Sari&hide_old_paid=0')
            ->assertOk()
            ->assertSee('Sari OnTime')
            ->assertDontSee('Lina Overdue');
    }

    #[Test]
    public function agen_only_prints_assigned_customers(): void
    {
        $agen = User::factory()->agen()->create();
        $router = $this->router();
        $mine = $this->customer($router, [
            'name' => 'Pelanggan Agen',
            'username' => 'agen01',
            'agent_id' => $agen->id,
        ]);
        $other = $this->customer($router, [
            'name' => 'Pelanggan Lain',
            'username' => 'lain01',
        ]);

        $this->invoice($mine, ['number' => 'INV-AGEN', 'status' => 'unpaid']);
        $this->invoice($other, ['number' => 'INV-LAIN', 'status' => 'unpaid']);

        $this->actingAs($agen)
            ->get('/admin/billing/print?hide_old_paid=0')
            ->assertOk()
            ->assertSee('Pelanggan Agen')
            ->assertDontSee('Pelanggan Lain');
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
            'username' => 'user'.uniqid(),
            'password' => 'secret',
            'due_date' => now()->toDateString(),
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
}
