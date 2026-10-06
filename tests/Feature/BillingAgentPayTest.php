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
                ->where('auth.user.can_grant_grace', false)
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
                ->where('auth.user.can_grant_grace', true)
            );
    }

    #[Test]
    public function selected_agent_can_pay_assigned_invoice(): void
    {
        $agent = User::factory()->agen()->create(['can_pay' => true]);
        $customer = $this->customer(['agent_id' => $agent->id, 'username' => 'agenboleh']);
        $invoice = $this->unpaidInvoice($customer, 'INV-AGEN-BOLEH');

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->post("/admin/billing/invoices/{$invoice->id}/pay", [
                'method' => 'cash',
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);
    }

    #[Test]
    public function selected_agent_cannot_pay_unassigned_invoice(): void
    {
        $agent = User::factory()->agen()->create(['can_pay' => true]);
        $other = User::factory()->agen()->create();
        $customer = $this->customer(['agent_id' => $other->id, 'username' => 'agenlain']);
        $invoice = $this->unpaidInvoice($customer, 'INV-AGEN-LAIN');

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
    }

    #[Test]
    public function agent_without_tolerance_cannot_grant_grace(): void
    {
        $agent = User::factory()->agen()->create();
        $customer = $this->customer(['agent_id' => $agent->id, 'username' => 'agentol']);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->post("/admin/billing/customers/{$customer->id}/grace", [
                'days' => 7,
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $customer->id,
            'grace_until' => null,
        ]);
    }

    #[Test]
    public function selected_agent_can_grant_and_clear_grace_for_assigned_customer(): void
    {
        $agent = User::factory()->agen()->create(['can_grant_grace' => true]);
        $customer = $this->customer(['agent_id' => $agent->id, 'username' => 'agentoleransi']);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->post("/admin/billing/customers/{$customer->id}/grace", [
                'days' => 7,
                'note' => 'Janji bayar',
            ])
            ->assertRedirect('/admin/billing');

        $customer->refresh();
        $this->assertSame(now()->addDays(7)->toDateString(), $customer->grace_until?->toDateString());
        $this->assertSame('Janji bayar', $customer->grace_note);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->delete("/admin/billing/customers/{$customer->id}/grace")
            ->assertRedirect('/admin/billing');

        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $customer->id,
            'grace_until' => null,
        ]);
    }

    #[Test]
    public function selected_agent_cannot_grant_grace_for_unassigned_customer(): void
    {
        $agent = User::factory()->agen()->create(['can_grant_grace' => true]);
        $other = User::factory()->agen()->create();
        $customer = $this->customer(['agent_id' => $other->id, 'username' => 'bukanmilik']);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->post("/admin/billing/customers/{$customer->id}/grace", [
                'days' => 3,
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $customer->id,
            'grace_until' => null,
        ]);
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
