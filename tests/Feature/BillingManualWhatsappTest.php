<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingManualWhatsappTest extends TestCase
{
    use RefreshDatabase;

    private function enableWhatsapp(): void
    {
        SiteSetting::setMany([
            'whatsapp_enabled' => '1',
            'whatsapp_base_url' => 'http://evolution.test',
            'whatsapp_api_key' => 'evo-key',
            'whatsapp_instance' => 'teslatech',
        ]);
    }

    private function fakeEvolution(): void
    {
        Http::fake([
            'http://evolution.test/*' => Http::response([
                'key' => ['id' => 'BAE1'],
                'status' => 'PENDING',
                'instance' => ['state' => 'open'],
            ], 201),
        ]);
    }

    private function customer(array $overrides = []): PppoeCustomer
    {
        $router = MikrotikRouter::query()->create([
            'name' => 'Router 1',
            'host' => '192.168.88.1',
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        return PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'username' => 'budi01',
            'password' => 'secret',
            'due_date' => now()->addDays(40)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    private function invoice(PppoeCustomer $customer, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'number' => 'INV-WA-MANUAL',
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
    public function billing_index_filters_isolated_customers(): void
    {
        $admin = User::factory()->superadmin()->create();
        $active = $this->customer(['username' => 'aktif01', 'status' => 'active']);
        $isolated = $this->customer([
            'username' => 'isolir01',
            'status' => 'isolated',
            'mikrotik_router_id' => $active->mikrotik_router_id,
        ]);
        $this->invoice($active, ['number' => 'INV-AKTIF']);
        $this->invoice($isolated, ['number' => 'INV-ISOLIR']);

        $this->actingAs($admin)
            ->get('/admin/billing?customer_status=isolated')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Billing/Index')
                ->where('filters.customer_status', 'isolated')
                ->has('invoices', 1)
                ->where('invoices.0.number', 'INV-ISOLIR')
                ->where('invoices.0.customer.status', 'isolated')
                ->where('stats.isolated', 1)
            );
    }

    #[Test]
    public function admin_can_manually_send_reminder_whatsapp(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $admin = User::factory()->superadmin()->create();
        $invoice = $this->invoice($this->customer());

        $this->actingAs($admin)
            ->from('/admin/billing')
            ->post("/admin/billing/invoices/{$invoice->id}/whatsapp", [
                'template' => 'reminder',
            ])
            ->assertRedirect('/admin/billing');

        $this->assertDatabaseHas('message_logs', [
            'channel' => 'whatsapp',
            'command' => 'reminder',
            'status' => 'sent',
            'pppoe_customer_id' => $invoice->pppoe_customer_id,
        ]);
        $this->assertStringContainsString('INV-WA-MANUAL', (string) MessageLog::query()->value('body'));
    }

    #[Test]
    public function welcome_template_cannot_be_sent_from_billing(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $admin = User::factory()->superadmin()->create();
        $invoice = $this->invoice($this->customer());

        $this->actingAs($admin)
            ->post("/admin/billing/invoices/{$invoice->id}/whatsapp", [
                'template' => 'welcome',
            ])
            ->assertSessionHasErrors('template');

        $this->assertDatabaseCount('message_logs', 0);
    }

    #[Test]
    public function bulk_whatsapp_sends_to_selected_invoices(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $admin = User::factory()->superadmin()->create();
        $first = $this->invoice($this->customer(['username' => 'a01']), ['number' => 'INV-A']);
        $second = $this->invoice(
            $this->customer([
                'username' => 'b01',
                'phone' => '081234567891',
                'mikrotik_router_id' => $first->customer->mikrotik_router_id,
            ]),
            ['number' => 'INV-B'],
        );

        $this->actingAs($admin)
            ->from('/admin/billing')
            ->post('/admin/billing/bulk-whatsapp', [
                'ids' => [$first->id, $second->id],
                'template' => 'isolir',
            ])
            ->assertRedirect('/admin/billing');

        $this->assertSame(2, MessageLog::query()->where('command', 'isolir')->where('status', 'sent')->count());
    }
}
