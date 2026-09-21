<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingAgentPayTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function agent_cannot_mark_assigned_invoice_paid(): void
    {
        $agent = User::factory()->agen()->create();
        $customer = $this->customer(['agent_id' => $agent->id, 'username' => 'agenpay']);
        $invoice = $this->unpaidInvoice($customer, 'INV-AGEN-PAY');

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->post("/admin/billing/invoices/{$invoice->id}/pay", [
                'method' => 'cash',
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'unpaid',
        ]);
        $this->assertDatabaseMissing('payments', [
            'invoice_id' => $invoice->id,
        ]);
    }

    #[Test]
    public function agent_cannot_bulk_pay_invoices(): void
    {
        $agent = User::factory()->agen()->create();
        $customer = $this->customer(['agent_id' => $agent->id, 'username' => 'agenbulk']);
        $invoice = $this->unpaidInvoice($customer, 'INV-AGEN-BULK');

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->post('/admin/billing/bulk-pay', [
                'ids' => [$invoice->id],
                'method' => 'cash',
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'unpaid',
        ]);
    }

    #[Test]
    public function billing_page_hides_payment_capability_for_agent(): void
    {
        $agent = User::factory()->agen()->create();

        $this->actingAs($agent)
            ->get('/admin/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Billing/Index')
                ->where('auth.user.role', User::ROLE_AGEN)
                ->where('auth.user.can_record_payment', false)
                ->where('auth.user.can_write', true)
            );
    }

    #[Test]
    public function admin_billing_page_keeps_payment_capability(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.can_record_payment', true)
            );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(array $overrides = []): PppoeCustomer
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router A',
            'host' => '192.168.10.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        return PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'name' => 'Pelanggan Agen',
            'phone' => '081234567890',
            'username' => 'user01',
            'password' => 'secret',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    private function unpaidInvoice(PppoeCustomer $customer, string $number): Invoice
    {
        return Invoice::query()->create([
            'number' => $number,
            'pppoe_customer_id' => $customer->id,
            'type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
        ]);
    }
}
