<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessageOutbox;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Messaging\CustomerNotifier;
use App\Services\Messaging\MessageTemplate;
use App\Services\MikrotikApiService;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Mockery;
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

    private function postUpsert(string $text, string $jid = '6281234567890@s.whatsapp.net', bool $fromMe = false): TestResponse
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
    public function unique_phone_is_bound_without_daftar_so_bayar_works(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $customer = $this->customer();
        $this->unpaidInvoice($customer, 'INV-AUTO-PAY');

        $this->artisan('messaging:bind-whatsapp')
            ->expectsOutputToContain('1 baru terhubung')
            ->assertSuccessful();

        $this->assertDatabaseHas('messaging_identities', [
            'channel' => 'whatsapp',
            'external_id' => '6281234567890',
            'pppoe_customer_id' => $customer->id,
        ]);

        $this->postUpsert('tagihan')->assertOk();

        $tagihan = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('INV-AUTO-PAY', (string) $tagihan);

        $this->postUpsert('bayar')->assertOk();

        $bayar = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('/portal', (string) $bayar);
        $this->assertStringNotContainsString('daftar', strtolower((string) $bayar));
    }

    #[Test]
    public function duplicate_phones_are_not_auto_bound(): void
    {
        $this->enableWhatsapp();
        $first = $this->customer();
        $this->customer([
            'mikrotik_router_id' => $first->mikrotik_router_id,
            'name' => 'Siti Aminah',
            'username' => 'siti01',
            'phone' => '6281234567890',
        ]);

        $this->artisan('messaging:bind-whatsapp')->assertSuccessful();

        $this->assertDatabaseCount('messaging_identities', 0);
    }

    #[Test]
    public function unknown_whatsapp_number_still_needs_daftar(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->customer();
        $this->artisan('messaging:bind-whatsapp')->assertSuccessful();

        $this->postUpsert('bayar', '6289999999999@s.whatsapp.net')->assertOk();

        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('daftar', strtolower((string) $body));
        $this->assertDatabaseMissing('messaging_identities', [
            'external_id' => '6289999999999',
        ]);
    }

    #[Test]
    public function admin_can_bind_existing_whatsapp_numbers(): void
    {
        $this->enableWhatsapp();
        $customer = $this->customer();
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->from('/admin/messaging')
            ->post('/admin/messaging/whatsapp/bind')
            ->assertRedirect('/admin/messaging');

        $this->assertDatabaseHas('messaging_identities', [
            'channel' => 'whatsapp',
            'external_id' => '6281234567890',
            'pppoe_customer_id' => $customer->id,
        ]);
    }

    #[Test]
    public function whatsapp_bantuan_uses_company_name_from_site_settings(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        SiteSetting::setValue('company_name', 'Tesla Tech');
        $this->postUpsert('bantuan')->assertOk();

        $body = MessageLog::query()->where('direction', 'outbound')->value('body');
        $this->assertStringContainsString('Tesla Tech', (string) $body);
        $this->assertStringContainsString('Bot pelanggan', (string) $body);
        $this->assertStringNotContainsString('3R Solusi Media', (string) $body);

        $this->assertSame(
            "Tesla Tech\nBot pelanggan — ketik salah satu perintah:",
            AppSettings::replaceLegacyBrand("3R Solusi Media\nBot pelanggan — ketik salah satu perintah:"),
        );

        Http::assertSent(function ($request) {
            $text = (string) ($request['text'] ?? '');

            return str_contains($request->url(), '/message/sendText/teslatech')
                && str_starts_with($text, 'Tesla Tech')
                && str_contains($text, 'Bot pelanggan — ketik salah satu perintah:')
                && ! str_contains($text, '3R Solusi Media');
        });

        SiteSetting::setValue('company_name', 'Net Desa Maju');
        $this->postUpsert('bantuan')->assertOk();

        Http::assertSent(function ($request) {
            $text = (string) ($request['text'] ?? '');

            return str_contains($request->url(), '/message/sendText/teslatech')
                && str_starts_with($text, 'Net Desa Maju')
                && ! str_contains($text, 'Tesla Tech')
                && ! str_contains($text, '3R Solusi Media');
        });
    }

    #[Test]
    public function lid_remote_jid_uses_whatsapp_alt_number_for_tagihan(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $customer = $this->customer(['phone' => '087778888820']);

        Invoice::query()->create([
            'number' => 'INV-LID',
            'pppoe_customer_id' => $customer->id,
            'type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 109000,
            'discount' => 0,
            'total' => 109000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
        ]);

        $this->postJson('/webhooks/evolution?token=wa-secret-token', [
            'event' => 'messages.upsert',
            'instance' => 'teslatech',
            'data' => [
                'key' => [
                    'remoteJid' => '89374763012229@lid',
                    'remoteJidAlt' => '6287778888820@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => 'LID1',
                    'addressingMode' => 'lid',
                ],
                'pushName' => 'Nurohman',
                'message' => [
                    'conversation' => 'tagihan',
                ],
            ],
            'sender' => '6285119888820@s.whatsapp.net',
        ])->assertOk();

        $this->assertDatabaseHas('messaging_identities', [
            'channel' => 'whatsapp',
            'external_id' => '6287778888820',
            'pppoe_customer_id' => $customer->id,
        ]);

        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('INV-LID', (string) $body);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/message/sendText/teslatech')
                && ($request['number'] ?? null) === '6287778888820';
        });
    }

    #[Test]
    public function first_prorata_invoice_does_not_send_invoice_whatsapp(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();

        $customer = $this->customer([
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'billing_day' => 1,
            'first_bill_amount' => 75000,
        ]);

        $invoice = app(BillingService::class)->createProrataInvoice($customer);

        $this->assertNotNull($invoice);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/message/sendText/'));
    }

    #[Test]
    public function invoice_notification_is_queued_then_sent_with_pacing(): void
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

        $notifier = app(CustomerNotifier::class);
        $notifier->notifyInvoice($invoice);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/message/sendText/'));
        $this->assertDatabaseHas('message_outbox', [
            'template' => 'invoice',
            'status' => MessageOutbox::STATUS_PENDING,
            'invoice_id' => $invoice->id,
            'external_id' => '6281234567890',
        ]);
        $this->assertDatabaseHas('messaging_identities', [
            'channel' => 'whatsapp',
            'external_id' => '6281234567890',
            'pppoe_customer_id' => $customer->id,
        ]);

        $notifier->dispatchWhatsappOutbox();

        Http::assertSent(function ($request) {
            $text = (string) ($request['text'] ?? '');

            return str_contains($request->url(), '/message/sendText/teslatech')
                && ($request['number'] ?? null) === '6281234567890'
                && str_contains($text, 'INV-NOTIF')
                && str_contains($text, '/portal')
                && str_contains($text, 'budi01')
                && str_contains($text, '081234567890')
                && str_contains($text, 'Bayar di portal pelanggan')
                && isset($request['delay']);
        });
        $this->assertDatabaseHas('message_outbox', [
            'invoice_id' => $invoice->id,
            'status' => MessageOutbox::STATUS_SENT,
        ]);
    }

    #[Test]
    public function invoice_whatsapp_is_skipped_when_customer_has_no_phone(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $customer = $this->customer(['phone' => null]);
        $invoice = $this->unpaidInvoice($customer, 'INV-NO-PHONE');

        $notifier = app(CustomerNotifier::class);
        $notifier->notifyInvoice($invoice);

        $this->assertDatabaseMissing('message_outbox', ['invoice_id' => $invoice->id]);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/message/sendText/'));
    }

    #[Test]
    public function stored_legacy_invoice_template_upgrades_to_portal_login(): void
    {
        SiteSetting::setValue('msg_tpl_invoice', implode("\n", [
            '🧾 *Tagihan baru*',
            '',
            'Halo {{nama}}, tagihan layanan internet Anda sudah terbit.',
            '',
            '🧾 Invoice: {{nomor}}',
            '💰 Total: {{total}}',
            '📅 Jatuh tempo: {{jatuh_tempo}}',
            '📦 Paket: {{paket}}',
            '🔐 Akun: {{username}}',
            '',
            '💬 Ketik *tagihan* atau *bayar* di chat ini.',
            '',
            '{{rekening}}',
            '',
            '— {{perusahaan}}',
        ]));

        $body = MessageTemplate::get(MessageTemplate::INVOICE);

        $this->assertStringContainsString('{{portal}}', $body);
        $this->assertStringContainsString('Username: {{username}}', $body);
        $this->assertStringContainsString('Nomor HP: {{phone}}', $body);
        $this->assertStringContainsString('Bayar di portal pelanggan', $body);
    }

    #[Test]
    public function stored_template_hardcoded_3rsolusimedia_follows_site_company_name(): void
    {
        SiteSetting::setMany([
            'company_name' => 'Tesla Tech',
            'msg_tpl_invoice' => implode("\n", [
                'Halo {{nama}}, tagihan dari 3rsolusimedia.',
                'Invoice {{nomor}} {{total}}',
                '— 3R Solusi Media',
            ]),
        ]);

        $text = MessageTemplate::render(MessageTemplate::INVOICE, [
            'nama' => 'Budi Santoso',
            'nomor' => 'INV-BRAND',
            'total' => 'Rp 150.000',
        ]);

        $this->assertStringNotContainsString('3rsolusimedia', $text);
        $this->assertStringNotContainsString('3R Solusi Media', $text);
        $this->assertStringContainsString('Tesla Tech', $text);
        $this->assertStringContainsString('INV-BRAND', $text);

        $emailKept = MessageTemplate::withCompanyPlaceholder('CS: halo@3rsolusimedia.id');
        $this->assertSame('CS: halo@3rsolusimedia.id', $emailKept);
    }

    #[Test]
    public function new_invoice_whatsapp_messages_are_staggered(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        SiteSetting::setMany([
            'whatsapp_send_delay_min' => '25',
            'whatsapp_send_delay_max' => '40',
            'whatsapp_send_batch' => '1',
        ]);

        $firstCustomer = $this->customer(['username' => 'a01']);
        $secondCustomer = $this->customer([
            'username' => 'b01',
            'phone' => '081234567891',
            'mikrotik_router_id' => $firstCustomer->mikrotik_router_id,
        ]);

        $first = $this->unpaidInvoice($firstCustomer, 'INV-A');
        $second = $this->unpaidInvoice($secondCustomer, 'INV-B');

        $notifier = app(CustomerNotifier::class);
        $notifier->notifyInvoice($first);
        $notifier->notifyInvoice($second);

        $rows = MessageOutbox::query()->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertTrue(
            $rows[1]->available_at->greaterThan($rows[0]->available_at),
            'Pesan kedua harus dijadwalkan setelah pesan pertama.',
        );
        $this->assertGreaterThanOrEqual(
            25,
            $rows[1]->available_at->getTimestamp() - $rows[0]->available_at->getTimestamp(),
        );

        $notifier->dispatchWhatsappOutbox();
        $this->assertSame(1, MessageLog::query()->where('command', 'invoice')->where('status', 'sent')->count());
        $this->assertSame(MessageOutbox::STATUS_PENDING, $rows[1]->fresh()->status);

        $this->travel(60)->seconds();
        $notifier->dispatchWhatsappOutbox();
        $this->assertSame(2, MessageLog::query()->where('command', 'invoice')->where('status', 'sent')->count());
    }

    #[Test]
    public function daily_whatsapp_limit_postpones_remaining_invoice_queue(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        SiteSetting::setMany([
            'whatsapp_send_delay_min' => '8',
            'whatsapp_send_delay_max' => '8',
            'whatsapp_send_batch' => '2',
            'whatsapp_send_daily_limit' => '1',
        ]);

        $firstCustomer = $this->customer(['username' => 'a01']);
        $secondCustomer = $this->customer([
            'username' => 'b01',
            'phone' => '081234567891',
            'mikrotik_router_id' => $firstCustomer->mikrotik_router_id,
        ]);

        $notifier = app(CustomerNotifier::class);
        $notifier->notifyInvoice($this->unpaidInvoice($firstCustomer, 'INV-LIMIT-1'));
        $notifier->dispatchWhatsappOutbox();
        $this->assertSame(1, MessageLog::query()->where('command', 'invoice')->where('status', 'sent')->count());

        $second = $this->unpaidInvoice($secondCustomer, 'INV-LIMIT-2');
        $notifier->notifyInvoice($second);
        $this->travel(15)->seconds();
        $notifier->dispatchWhatsappOutbox();

        $queued = MessageOutbox::query()->where('invoice_id', $second->id)->first();
        $this->assertNotNull($queued);
        $this->assertSame(MessageOutbox::STATUS_PENDING, $queued->status);
        $this->assertTrue($queued->available_at->greaterThan(now()->addHours(6)));
        $this->assertSame(1, MessageLog::query()->where('command', 'invoice')->where('status', 'sent')->count());
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
        $this->assertStringContainsString('/portal', (string) $body);
        $this->assertStringContainsString('081234567890', (string) $body);
    }

    #[Test]
    public function marking_invoice_paid_sends_paid_whatsapp(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->mockRouterSync();
        $customer = $this->customer(['billing_day' => 1]);
        $invoice = $this->unpaidInvoice($customer, 'INV-LUNAS');

        app(BillingService::class)->markPaid($invoice);

        $log = MessageLog::query()->where('command', 'paid')->first();
        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status, (string) $log->error_message);

        $body = (string) MessageLog::query()->where('command', 'paid')->value('body');
        $this->assertStringContainsString('Pembayaran diterima', $body);
        $this->assertStringContainsString('INV-LUNAS', $body);
        $this->assertStringContainsString('Budi Santoso', $body);
        $this->assertDatabaseMissing('message_logs', ['command' => 'restore']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/message/sendText/teslatech')
                && ($request['number'] ?? null) === '6281234567890'
                && str_contains((string) ($request['text'] ?? ''), 'INV-LUNAS');
        });
    }

    #[Test]
    public function marking_invoice_paid_skips_whatsapp_when_billing_toggle_is_off(): void
    {
        $this->enableWhatsapp();
        SiteSetting::setValue('app_notif_whatsapp', '0');
        $this->fakeEvolution();
        $this->mockRouterSync();
        $invoice = $this->unpaidInvoice($this->customer(['billing_day' => 1]), 'INV-SKIP');

        app(BillingService::class)->markPaid($invoice);

        $this->assertDatabaseMissing('message_logs', ['command' => 'paid']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/message/sendText/'));
    }

    #[Test]
    public function paying_isolated_customer_sends_paid_and_restore_whatsapp(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        $this->mockRouterSync();
        $customer = $this->customer([
            'billing_day' => 20,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => 'isolated',
            'overdue_action' => 'isolir',
            'service_profile' => '10Mbps',
            'isolir_profile' => 'ISOLIR',
        ]);
        $invoice = $this->unpaidInvoice($customer, 'INV-RESTORE');

        app(BillingService::class)->markPaid($invoice);

        $this->assertDatabaseHas('message_logs', ['command' => 'paid', 'status' => 'sent']);
        $this->assertDatabaseHas('message_logs', ['command' => 'restore', 'status' => 'sent']);
        $this->assertStringContainsString('aktif kembali', (string) MessageLog::query()->where('command', 'restore')->value('body'));
    }

    private function unpaidInvoice(PppoeCustomer $customer, string $number): Invoice
    {
        return Invoice::query()->create([
            'number' => $number,
            'pppoe_customer_id' => $customer->id,
            'type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => $customer->due_date?->toDateString() ?? now()->toDateString(),
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
        ]);
    }

    private function mockRouterSync(): void
    {
        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')->andReturn([
            'ok' => true,
            'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
        ]);
        $this->app->instance(MikrotikApiService::class, $api);
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
        $this->assertStringContainsString('🧾', $text);
        $this->assertStringNotContainsString('{{nama}}', $text);
    }

    #[Test]
    public function template_render_injects_bank_account_from_settings(): void
    {
        SiteSetting::setMany([
            'company_name' => 'TeslaTech',
            'bank_name' => 'BCA',
            'bank_account_name' => 'PT Tesla Tech',
            'bank_account_number' => '1234567890',
            'bank_note' => 'Konfirmasi ke WA',
        ]);

        $text = MessageTemplate::render('invoice', [
            'nama' => 'Budi',
            'nomor' => 'INV-1',
            'total' => 'Rp 10.000',
            'jatuh_tempo' => '01/09/2026',
            'paket' => '10 Mbps',
            'username' => 'budi01',
        ]);

        $this->assertStringContainsString('Transfer ke:', $text);
        $this->assertStringContainsString('BCA', $text);
        $this->assertStringContainsString('a.n. PT Tesla Tech', $text);
        $this->assertStringContainsString('1234567890', $text);
        $this->assertStringContainsString('Konfirmasi ke WA', $text);
        $this->assertStringNotContainsString('{{rekening}}', $text);
        $this->assertStringNotContainsString('{{nomor_rekening}}', $text);
    }

    #[Test]
    public function template_render_lists_all_bank_accounts(): void
    {
        SiteSetting::setMany(AppSettings::bankAccountSettingValues([
            [
                'bank_name' => 'BCA',
                'bank_account_name' => 'PT Tesla Tech',
                'bank_account_number' => '1234567890',
                'bank_note' => 'Utama',
            ],
            [
                'bank_name' => 'Mandiri',
                'bank_account_name' => 'PT Tesla Tech',
                'bank_account_number' => '9876543210',
                'bank_note' => '',
            ],
        ]));

        $text = MessageTemplate::render('invoice', [
            'nama' => 'Budi',
            'nomor' => 'INV-1',
            'total' => 'Rp 10.000',
            'jatuh_tempo' => '01/09/2026',
            'paket' => '10 Mbps',
            'username' => 'budi01',
        ]);

        $this->assertStringContainsString('Transfer ke:', $text);
        $this->assertStringContainsString('BCA', $text);
        $this->assertStringContainsString('1234567890', $text);
        $this->assertStringContainsString('Mandiri', $text);
        $this->assertStringContainsString('9876543210', $text);
        $this->assertStringContainsString('Utama', $text);
    }

    #[Test]
    public function stored_legacy_templates_are_upgraded_with_icons(): void
    {
        SiteSetting::setValue(
            'msg_tpl_isolir',
            "Halo {{nama}},\n\nLayanan {{username}} diisolir karena tagihan belum lunas.\nSegera lunasi agar koneksi aktif kembali. Ketik bayar.\n\n— {{perusahaan}}",
        );

        $text = MessageTemplate::render('isolir', [
            'nama' => 'Budi',
            'username' => 'budi01',
            'perusahaan' => 'TeslaTech',
        ]);

        $this->assertStringContainsString('⛔', $text);
        $this->assertStringContainsString('diisolir', $text);
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
                'messaging_notify_invoice' => '1',
                'messaging_notify_reminder' => '1',
                'messaging_notify_paid' => '1',
                'messaging_notify_isolir' => '1',
                'msg_tpl_invoice' => 'Halo {{nama}} tagihan {{nomor}}',
                'whatsapp_send_delay_min' => 20,
                'whatsapp_send_delay_max' => 45,
                'whatsapp_send_batch' => 1,
                'whatsapp_send_daily_limit' => 50,
            ])
            ->assertRedirect('/admin/messaging');

        $this->assertSame('1', SiteSetting::getValue('app_notif_whatsapp'));
        $this->assertSame('1', SiteSetting::getValue('messaging_notify_invoice'));
        $this->assertSame('1', SiteSetting::getValue('messaging_notify_reminder'));
        $this->assertSame('1', SiteSetting::getValue('messaging_notify_paid'));
        $this->assertSame('Halo {{nama}} tagihan {{nomor}}', SiteSetting::getValue('msg_tpl_invoice'));
        $this->assertSame('20', SiteSetting::getValue('whatsapp_send_delay_min'));
        $this->assertSame('45', SiteSetting::getValue('whatsapp_send_delay_max'));
        $this->assertSame('1', SiteSetting::getValue('whatsapp_send_batch'));
        $this->assertSame('50', SiteSetting::getValue('whatsapp_send_daily_limit'));
    }

    #[Test]
    public function admin_can_save_billing_notification_toggles_independently(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->post('/admin/messaging/templates', [
                'messaging_notify_invoice' => '0',
                'messaging_notify_reminder' => '0',
                'messaging_notify_paid' => '1',
                'messaging_notify_isolir' => '0',
            ])
            ->assertRedirect('/admin/messaging');

        $this->assertSame('0', SiteSetting::getValue('messaging_notify_invoice'));
        $this->assertSame('0', SiteSetting::getValue('messaging_notify_reminder'));
        $this->assertSame('1', SiteSetting::getValue('messaging_notify_paid'));
        $this->assertSame('1', SiteSetting::getValue('app_notif_whatsapp'));
        $this->assertFalse(AppSettings::notifyInvoice());
        $this->assertFalse(AppSettings::notifyReminder());
        $this->assertTrue(AppSettings::notifyPaid());
    }

    #[Test]
    public function invoice_and_reminder_toggles_do_not_block_paid_whatsapp(): void
    {
        $this->enableWhatsapp();
        SiteSetting::setMany([
            'messaging_notify_invoice' => '0',
            'messaging_notify_reminder' => '0',
            'messaging_notify_paid' => '1',
        ]);
        $this->fakeEvolution();
        $this->mockRouterSync();

        $customer = $this->customer(['billing_day' => 1]);
        $invoice = $this->unpaidInvoice($customer, 'INV-PAID-ONLY');
        $notifier = app(CustomerNotifier::class);

        $notifier->notifyInvoice($invoice);
        $this->assertDatabaseMissing('message_outbox', ['template' => 'invoice']);

        $this->artisan('messaging:remind-invoices')
            ->expectsOutput('Pengingat tagihan nonaktif (Notifikasi & Bot).')
            ->assertSuccessful();

        app(BillingService::class)->markPaid($invoice);

        $this->assertDatabaseHas('message_logs', ['command' => 'paid', 'status' => 'sent']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/message/sendText/'));
    }

    #[Test]
    public function welcome_notification_sends_complete_customer_info(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        SiteSetting::setMany([
            'messaging_notify_welcome' => '1',
            'company_name' => 'TeslaTech',
            'whatsapp' => '628111000111',
        ]);

        $customer = $this->customer([
            'address' => 'Jl. Melati 1',
            'start_date' => '2026-08-23',
            'billing_day' => 5,
            'first_bill_amount' => 75000,
        ]);

        $invoice = Invoice::query()->create([
            'number' => 'INV-WELCOME',
            'pppoe_customer_id' => $customer->id,
            'type' => 'monthly',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 75000,
            'discount' => 0,
            'total' => 75000,
            'status' => 'unpaid',
            'package_name' => '10 Mbps',
        ]);

        app(CustomerNotifier::class)->notifyWelcome($customer, $invoice);

        Http::assertSent(function ($request) {
            $text = (string) ($request['text'] ?? '');

            return str_contains($request->url(), '/message/sendText/teslatech')
                && ($request['number'] ?? null) === '6281234567890'
                && str_contains($text, 'Selamat datang')
                && str_contains($text, 'Budi Santoso')
                && str_contains($text, 'budi01')
                && str_contains($text, 'secret')
                && str_contains($text, 'Jl. Melati 1')
                && str_contains($text, 'INV-WELCOME')
                && str_contains($text, 'tagihan')
                && str_contains($text, '/portal')
                && str_contains($text, 'Masuk dengan username PPPoE dan nomor HP')
                && str_contains($text, 'Nomor HP: 081234567890');
        });

        $log = MessageLog::query()->where('command', 'welcome')->value('body');
        $this->assertStringNotContainsString('secret', (string) $log);
    }

    #[Test]
    public function welcome_address_falls_back_to_gps_coordinates(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        SiteSetting::setValue('messaging_notify_welcome', '1');

        $customer = $this->customer([
            'address' => null,
            'latitude' => -6.175392,
            'longitude' => 106.827153,
        ]);

        app(CustomerNotifier::class)->notifyWelcome($customer);

        Http::assertSent(function ($request) {
            $text = (string) ($request['text'] ?? '');

            return str_contains($request->url(), '/message/sendText/teslatech')
                && str_contains($text, '-6.175392, 106.827153')
                && ! str_contains($text, 'maps.google.com')
                && str_contains($text, '/portal');
        });
    }

    #[Test]
    public function welcome_notification_is_skipped_when_toggle_is_off(): void
    {
        $this->enableWhatsapp();
        $this->fakeEvolution();
        SiteSetting::setValue('messaging_notify_welcome', '0');

        app(CustomerNotifier::class)->notifyWelcome($this->customer());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/message/sendText/'));
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

    #[Test]
    public function whatsapp_status_repairs_webhook_pointing_at_old_domain(): void
    {
        $this->enableWhatsapp();
        Http::fake([
            'http://evolution.test/instance/connectionState/teslatech' => Http::response([
                'instance' => ['instanceName' => 'teslatech', 'state' => 'open'],
            ], 200),
            'http://evolution.test/webhook/find/teslatech' => Http::response([
                'url' => 'https://3rsolusimedia.my.id/webhooks/evolution?token=old',
                'enabled' => true,
            ], 200),
            'http://evolution.test/webhook/set/teslatech' => Http::response(['ok' => true], 201),
        ]);

        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->getJson('/admin/messaging/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('webhook_repaired', true)
            ->assertJsonPath('remote_webhook_url', '3rsolusimedia.my.id/webhooks/evolution');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/webhook/set/teslatech'));
    }
}
