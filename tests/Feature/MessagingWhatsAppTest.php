<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessagingIdentity;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Messaging\MessageTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessagingWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function enableWhatsapp(): void
    {
        SiteSetting::setMany([
            'whatsapp_enabled' => '1',
            'whatsapp_base_url' => 'http://evolution.test',
            'whatsapp_api_key' => 'evo-key',
            'whatsapp_instance' => 'teslatech',
            'whatsapp_webhook_secret' => 'wa-secret-token',
            'app_notif_whatsapp' => '1',
            'messaging_notify_isolir' => '1',
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
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    private function postUpsert(string $text, string $jid = '6281234567890@s.whatsapp.net', bool $fromMe = false): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/evolution?token=wa-secret-token', [
            'event' => 'MESSAGES_UPSERT',
            'instance' => 'teslatech',
            'data' => [
                'key' => [
                    'remoteJid' => $jid,
                    'fromMe' => $fromMe,
                    'id' => 'ABC',
                ],
                'pushName' => 'Budi',
                'message' => [
                    'conversation' => $text,
                ],
            ],
        ]);
    }

    #[Test]
    public function webhook_rejects_invalid_token(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();

        $this->postJson('/webhooks/evolution', [
            'event' => 'MESSAGES_UPSERT',
            'data' => [
                'key' => ['remoteJid' => '6281234567890@s.whatsapp.net', 'fromMe' => false],
                'message' => ['conversation' => 'tagihan'],
            ],
        ])->assertForbidden();
    }

    #[Test]
    public function from_me_messages_are_ignored(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->customer();

        $this->postUpsert('tagihan', '6281234567890@s.whatsapp.net', true)->assertOk();
        $this->assertDatabaseCount('message_logs', 0);
    }

    #[Test]
    public function matching_phone_auto_binds_and_tagihan_without_slash_works(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $customer = $this->customer();

        Invoice::query()->create([
            'number' => 'INV-WA-1',
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

        $this->postUpsert('tagihan')->assertOk();

        $this->assertDatabaseHas('messaging_identities', [
            'channel' => 'whatsapp',
            'external_id' => '6281234567890',
            'pppoe_customer_id' => $customer->id,
        ]);

        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('INV-WA-1', (string) $body);
    }

    #[Test]
    public function invoice_notification_is_sent_when_toggle_is_on(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $customer = $this->customer();

        $invoice = Invoice::query()->create([
            'number' => 'INV-NOTIF',
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

        app(CustomerNotifier::class)->notifyInvoice($invoice);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/message/sendText/teslatech')
                && ($request['number'] ?? null) === '6281234567890'
                && str_contains((string) ($request['text'] ?? ''), 'INV-NOTIF');
        });
    }

    #[Test]
    public function isolir_notification_uses_template(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $customer = $this->customer();

        app(CustomerNotifier::class)->notifyIsolir($customer);

        $body = MessageLog::query()->where('command', 'isolir')->value('body');
        $this->assertStringContainsString('Budi Santoso', (string) $body);
        $this->assertStringContainsString('budi01', (string) $body);
        $this->assertStringContainsString('diisolir', (string) $body);
    }

    #[Test]
    public function template_render_replaces_placeholders(): void
    {
        $text = MessageTemplate::render('invoice', [
            'nama' => 'Budi',
            'nomor' => 'INV-1',
            'total' => 'Rp 10.000',
            'jatuh_tempo' => '01/09/2026',
            'paket' => '10 Mbps',
            'username' => 'budi01',
            'perusahaan' => 'TeslaTech',
        ]);

        $this->assertStringContainsString('Budi', $text);
        $this->assertStringContainsString('INV-1', $text);
        $this->assertStringContainsString('TeslaTech', $text);
        $this->assertStringNotContainsString('{{nama}}', $text);
    }

    #[Test]
    public function admin_can_save_whatsapp_settings_and_templates(): void
    {
        $this->fakeEvolution();
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->post('/admin/messaging', [
                'telegram_enabled' => '0',
                'whatsapp_enabled' => '1',
                'whatsapp_base_url' => 'http://evolution.test',
                'whatsapp_api_key' => 'evo-key',
                'whatsapp_instance' => 'teslatech',
                'whatsapp_test_number' => '628111',
            ])
            ->assertRedirect('/admin/messaging');

        $this->assertSame('1', SiteSetting::getValue('whatsapp_enabled'));
        $this->assertSame('http://evolution.test', SiteSetting::getValue('whatsapp_base_url'));
        $this->assertSame('evo-key', SiteSetting::getValue('whatsapp_api_key'));

        $this->actingAs($admin)
            ->post('/admin/messaging/templates', [
                'app_notif_whatsapp' => '1',
                'messaging_notify_isolir' => '1',
                'msg_tpl_invoice' => 'Halo {{nama}} tagihan {{nomor}}',
            ])
            ->assertRedirect('/admin/messaging');

        $this->assertSame('1', SiteSetting::getValue('app_notif_whatsapp'));
        $this->assertSame('Halo {{nama}} tagihan {{nomor}}', SiteSetting::getValue('msg_tpl_invoice'));
    }

    #[Test]
    public function connect_reads_v2_qr_payload_without_unknown_state(): void
    {
        $this->enableWhatsapp();
        Http::fake([
            'http://evolution.test/instance/connectionState/teslatech' => Http::response([
                'instance' => [
                    'instanceName' => 'teslatech',
                    'state' => 'connecting',
                ],
            ], 200),
            'http://evolution.test/webhook/set/teslatech' => Http::response(['ok' => true], 201),
            'http://evolution.test/instance/connect/teslatech' => Http::response([
                'pairingCode' => null,
                'code' => '2@abc,1,TEST',
                'base64' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                'count' => 1,
            ], 200),
        ]);

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/messaging/whatsapp/connect')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('state', 'connecting')
            ->assertJsonPath('message', 'Scan QR di WhatsApp (Perangkat tertaut).')
            ->assertJsonFragment(['qr_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==']);
    }
}
