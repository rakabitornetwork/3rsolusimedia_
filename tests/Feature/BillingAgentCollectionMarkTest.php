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

class BillingAgentCollectionMarkTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function agent_can_toggle_cash_and_ready_tf_on_assigned_invoice(): void
    {
        $agent = User::factory()->agen()->create();
        $invoice = $this->unpaidInvoice($agent);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->patch("/admin/billing/invoices/{$invoice->id}/agent-marks", [
                'agent_cash' => true,
            ])
            ->assertRedirect('/admin/billing');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'agent_cash' => 1,
            'agent_ready_tf' => 0,
        ]);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->patch("/admin/billing/invoices/{$invoice->id}/agent-marks", [
                'agent_ready_tf' => true,
            ])
            ->assertRedirect('/admin/billing');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'agent_cash' => 1,
            'agent_ready_tf' => 1,
        ]);
    }

    #[Test]
    public function agent_cannot_mark_invoice_of_another_customer(): void
    {
        $agent = User::factory()->agen()->create();
        $other = User::factory()->agen()->create();
        $invoice = $this->unpaidInvoice($other);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->patch("/admin/billing/invoices/{$invoice->id}/agent-marks", [
                'agent_cash' => true,
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'agent_cash' => 0,
        ]);
    }

    #[Test]
    public function admin_cannot_set_agent_collection_marks(): void
    {
        $admin = User::factory()->create();
        $agent = User::factory()->agen()->create();
        $invoice = $this->unpaidInvoice($agent);

        $this->actingAs($admin)
            ->from('/admin/billing')
            ->patch("/admin/billing/invoices/{$invoice->id}/agent-marks", [
                'agent_cash' => true,
            ])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'agent_cash' => 0,
        ]);
    }

    #[Test]
    public function billing_index_exposes_marks_only_for_agent(): void
    {
        $agent = User::factory()->agen()->create();
        $invoice = $this->unpaidInvoice($agent, ['agent_cash' => true]);

        $this->actingAs($agent)
            ->get('/admin/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Billing/Index')
                ->where('invoices.0.id', $invoice->id)
                ->where('invoices.0.agent_cash', true)
                ->where('invoices.0.agent_ready_tf', false)
            );
    }

    #[Test]
    public function agent_print_shows_checked_cash_and_ready_tf_boxes(): void
    {
        $agent = User::factory()->agen()->create();
        $cash = $this->unpaidInvoice($agent, [
            'number' => 'INV-CASH',
            'agent_cash' => true,
        ], ['name' => 'Pelanggan Cash', 'username' => 'cash01']);
        $tf = $this->unpaidInvoice($agent, [
            'number' => 'INV-TF',
            'agent_ready_tf' => true,
        ], ['name' => 'Pelanggan Transfer', 'username' => 'tf01']);

        $html = $this->actingAs($agent)
            ->get('/admin/billing/print?hide_old_paid=0')
            ->assertOk()
            ->assertSee('Pelanggan Cash')
            ->assertSee('Pelanggan Transfer')
            ->assertSee('Siap TF')
            ->assertSee('class="box is-checked"', false)
            ->assertSee('Cash 1, Siap TF 1')
            ->getContent();

        $this->assertSame(2, substr_count($html, 'class="box is-checked"'));
        $this->assertNotFalse(strpos($html, $cash->customer->name));
        $this->assertNotFalse(strpos($html, $tf->customer->name));
    }

    #[Test]
    public function admin_print_keeps_blank_cash_and_tf_boxes(): void
    {
        $admin = User::factory()->superadmin()->create();
        $agent = User::factory()->agen()->create();
        $this->unpaidInvoice($agent, [
            'agent_cash' => true,
            'agent_ready_tf' => true,
        ], ['name' => 'Pelanggan Cash', 'username' => 'cash01']);

        $this->actingAs($admin)
            ->get('/admin/billing/print?hide_old_paid=0')
            ->assertOk()
            ->assertSee('Pelanggan Cash')
            ->assertSee('>TF</th>', false)
            ->assertDontSee('class="box is-checked"', false)
            ->assertSee('Kolom Ket, Cash, dan TF dikosongkan');
    }

    #[Test]
    public function checking_cash_sends_customer_info_to_admin_telegram(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();

        $agent = User::factory()->agen()->create(['name' => 'Agen Budi']);
        $invoice = $this->unpaidInvoice($agent, [
            'number' => 'INV-CASH-TG',
        ], [
            'name' => 'Sari Pelanggan',
            'username' => 'sari01',
            'phone' => '081298765432',
        ]);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->patch("/admin/billing/invoices/{$invoice->id}/agent-marks", [
                'agent_cash' => true,
            ])
            ->assertRedirect('/admin/billing');

        $this->assertDatabaseHas('message_logs', [
            'channel' => 'telegram',
            'command' => 'agent_cash',
            'status' => 'sent',
            'external_id' => '99',
            'pppoe_customer_id' => $invoice->pppoe_customer_id,
        ]);

        $body = (string) MessageLog::query()->value('body');
        $this->assertStringContainsString('💵 Agen menandai Cash', $body);
        $this->assertStringContainsString('🤝 Agen: Agen Budi', $body);
        $this->assertStringContainsString('✅ Tanda: 💵 Cash', $body);
        $this->assertStringContainsString('👤 Pelanggan: Sari Pelanggan', $body);
        $this->assertStringContainsString('🔑 Akun: sari01', $body);
        $this->assertStringContainsString('📱 HP: 081298765432', $body);
        $this->assertStringContainsString('🧾 Invoice: INV-CASH-TG', $body);
        $this->assertStringContainsString('💰 Total:', $body);
        $this->assertStringContainsString('150.000', $body);
        $this->assertStringContainsString('⏰ Jatuh tempo:', $body);
        $this->assertStringContainsString('📦 Paket:', $body);
    }

    #[Test]
    public function checking_ready_tf_sends_to_admin_telegram(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();

        $agent = User::factory()->agen()->create(['name' => 'Agen Siti']);
        $invoice = $this->unpaidInvoice($agent, ['number' => 'INV-TF-TG'], [
            'name' => 'Andi Transfer',
            'username' => 'andi01',
        ]);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->patch("/admin/billing/invoices/{$invoice->id}/agent-marks", [
                'agent_ready_tf' => true,
            ])
            ->assertRedirect('/admin/billing');

        $this->assertDatabaseHas('message_logs', [
            'channel' => 'telegram',
            'command' => 'agent_ready_tf',
            'status' => 'sent',
        ]);
        $body = (string) MessageLog::query()->value('body');
        $this->assertStringContainsString('🏦 Agen menandai Siap TF', $body);
        $this->assertStringContainsString('✅ Tanda: 🏦 Siap TF', $body);
        $this->assertStringContainsString('👤 Pelanggan: Andi Transfer', $body);
    }

    #[Test]
    public function unchecking_mark_does_not_send_telegram(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();

        $agent = User::factory()->agen()->create();
        $invoice = $this->unpaidInvoice($agent, ['agent_cash' => true]);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->patch("/admin/billing/invoices/{$invoice->id}/agent-marks", [
                'agent_cash' => false,
            ])
            ->assertRedirect('/admin/billing');

        $this->assertDatabaseMissing('message_logs', [
            'channel' => 'telegram',
            'command' => 'agent_cash',
        ]);
    }

    #[Test]
    public function checking_mark_still_saves_when_telegram_is_off(): void
    {
        $agent = User::factory()->agen()->create();
        $invoice = $this->unpaidInvoice($agent);

        $this->actingAs($agent)
            ->from('/admin/billing')
            ->patch("/admin/billing/invoices/{$invoice->id}/agent-marks", [
                'agent_cash' => true,
            ])
            ->assertRedirect('/admin/billing');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'agent_cash' => 1,
        ]);
        $this->assertDatabaseMissing('message_logs', [
            'channel' => 'telegram',
            'command' => 'agent_cash',
        ]);
    }

    private function enableTelegram(): void
    {
        SiteSetting::setMany([
            'telegram_enabled' => '1',
            'telegram_bot_token' => '123456:TESTTOKEN',
            'telegram_admin_chat_id' => '99',
        ]);
    }

    private function fakeTelegram(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 1],
            ], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $invoiceOverrides
     * @param  array<string, mixed>  $customerOverrides
     */
    private function unpaidInvoice(
        User $agent,
        array $invoiceOverrides = [],
        array $customerOverrides = [],
    ): Invoice {
        $router = MikrotikRouter::query()->first() ?? MikrotikRouter::query()->create([
            'name' => 'Router A',
            'host' => '192.168.10.'.random_int(2, 250),
            'port' => 8728,
            'username' => 'admin',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $customer = PppoeCustomer::query()->create(array_merge([
            'mikrotik_router_id' => $router->id,
            'name' => 'Pelanggan Agen',
            'phone' => '081234567890',
            'username' => 'agen'.uniqid(),
            'password' => 'secret',
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
            'agent_id' => $agent->id,
        ], $customerOverrides));

        return Invoice::query()->create(array_merge([
            'number' => 'INV-MARK-'.$customer->id,
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
        ], $invoiceOverrides));
    }
}
