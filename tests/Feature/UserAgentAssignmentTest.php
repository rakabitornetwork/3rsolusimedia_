<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Payment;
use App\Models\PppoeCustomer;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAgentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_form_includes_routeros_and_customers_grouped_by_router(): void
    {
        $admin = User::factory()->superadmin()->create();
        [$routerA, $routerB] = $this->routers();
        $onA = $this->customer($routerA, ['name' => 'Budi', 'username' => 'budi01']);
        $onB = $this->customer($routerB, ['name' => 'Siti', 'username' => 'siti01']);

        $this->actingAs($admin)
            ->get('/admin/users/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Form')
                ->has('routers', 2)
                ->where('routers.0.name', 'Router A')
                ->where('routers.1.name', 'Router B')
                ->has('pppoe_customers', 2)
                ->where('pppoe_customers.0.id', $onA->id)
                ->where('pppoe_customers.0.mikrotik_router_id', $routerA->id)
                ->where('pppoe_customers.0.router_name', 'Router A')
                ->where('pppoe_customers.1.id', $onB->id)
                ->where('pppoe_customers.1.mikrotik_router_id', $routerB->id)
            );
    }

    #[Test]
    public function creating_agent_assigns_selected_customers_from_chosen_router(): void
    {
        $admin = User::factory()->superadmin()->create();
        [$routerA, $routerB] = $this->routers();
        $onA = $this->customer($routerA, ['name' => 'Budi', 'username' => 'budi01']);
        $onB = $this->customer($routerB, ['name' => 'Siti', 'username' => 'siti01']);

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Agen Budi',
                'email' => 'agen.budi@example.com',
                'role' => User::ROLE_AGEN,
                'billing_commission' => 5000,
                'assigned_customer_ids' => [$onA->id],
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ])
            ->assertRedirect('/admin/users');

        $agent = User::query()->where('email', 'agen.budi@example.com')->first();
        $this->assertNotNull($agent);
        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $onA->id,
            'agent_id' => $agent->id,
            'agent_pays_commission' => 0,
        ]);
        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $onB->id,
            'agent_id' => null,
        ]);
    }

    #[Test]
    public function editing_agent_keeps_router_payload_and_can_switch_assigned_customers(): void
    {
        $admin = User::factory()->superadmin()->create();
        $agent = User::factory()->agen()->create(['name' => 'Agen Lama']);
        [$routerA, $routerB] = $this->routers();
        $onA = $this->customer($routerA, [
            'name' => 'Budi',
            'username' => 'budi01',
            'agent_id' => $agent->id,
        ]);
        $onB = $this->customer($routerB, ['name' => 'Siti', 'username' => 'siti01']);

        $this->actingAs($admin)
            ->get('/admin/users/'.$agent->id.'/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Form')
                ->has('routers', 2)
                ->where('user.assigned_customer_ids', [$onA->id])
                ->where('user.commission_customer_ids', [])
            );

        $this->actingAs($admin)
            ->put('/admin/users/'.$agent->id, [
                'name' => $agent->name,
                'email' => $agent->email,
                'role' => User::ROLE_AGEN,
                'billing_commission' => 0,
                'assigned_customer_ids' => [$onB->id],
            ])
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $onA->id,
            'agent_id' => null,
        ]);
        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $onB->id,
            'agent_id' => $agent->id,
            'agent_pays_commission' => 0,
        ]);
    }

    #[Test]
    public function only_specially_marked_customers_earn_agent_commission(): void
    {
        $admin = User::factory()->superadmin()->create();
        [$routerA] = $this->routers();
        $assignedOnly = $this->customer($routerA, ['name' => 'Budi', 'username' => 'budi01']);
        $commissioned = $this->customer($routerA, ['name' => 'Siti', 'username' => 'siti01']);
        $outsider = $this->customer($routerA, ['name' => 'Andi', 'username' => 'andi01']);

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Agen Komisi',
                'email' => 'agen.komisi@example.com',
                'role' => User::ROLE_AGEN,
                'billing_commission' => 7000,
                'assigned_customer_ids' => [$assignedOnly->id, $commissioned->id],
                'commission_customer_ids' => [$commissioned->id, $outsider->id],
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ])
            ->assertRedirect('/admin/users');

        $agent = User::query()->where('email', 'agen.komisi@example.com')->first();
        $this->assertNotNull($agent);
        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $assignedOnly->id,
            'agent_id' => $agent->id,
            'agent_pays_commission' => 0,
        ]);
        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $commissioned->id,
            'agent_id' => $agent->id,
            'agent_pays_commission' => 1,
        ]);
        $this->assertDatabaseHas('pppoe_customers', [
            'id' => $outsider->id,
            'agent_id' => null,
            'agent_pays_commission' => 0,
        ]);

        $billing = app(BillingService::class);
        $uncommissionedInvoice = $this->unpaidInvoice($assignedOnly, 'INV-NO-COMM');
        $commissionedInvoice = $this->unpaidInvoice($commissioned, 'INV-COMM');

        $billing->markPaid($uncommissionedInvoice);
        $billing->markPaid($commissionedInvoice);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $uncommissionedInvoice->id,
            'agent_id' => $agent->id,
            'agent_commission' => 0,
        ]);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $commissionedInvoice->id,
            'agent_id' => $agent->id,
            'agent_commission' => 7000,
        ]);
        $this->assertSame(7000, (int) Payment::query()->sum('agent_commission'));
    }

    #[Test]
    public function user_form_exposes_commission_flag_per_customer(): void
    {
        $admin = User::factory()->superadmin()->create();
        $agent = User::factory()->agen()->create();
        [$routerA] = $this->routers();
        $customer = $this->customer($routerA, [
            'name' => 'Budi',
            'username' => 'budi01',
            'agent_id' => $agent->id,
            'agent_pays_commission' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/users/'.$agent->id.'/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('user.commission_customer_ids', [$customer->id])
                ->where('pppoe_customers.0.agent_pays_commission', true)
            );
    }

    /**
     * @return array{0: MikrotikRouter, 1: MikrotikRouter}
     */
    private function routers(): array
    {
        $routerA = MikrotikRouter::query()->create([
            'name' => 'Router A',
            'host' => '192.168.10.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);
        $routerB = MikrotikRouter::query()->create([
            'name' => 'Router B',
            'host' => '192.168.20.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        return [$routerA, $routerB];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(MikrotikRouter $router, array $overrides = []): PppoeCustomer
    {
        return PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'name' => 'Pelanggan',
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
