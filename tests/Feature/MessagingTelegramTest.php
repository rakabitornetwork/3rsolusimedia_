<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessagingIdentity;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\GenieAcsService;
use App\Services\PaymentGateway\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessagingTelegramTest extends TestCase
{
    use RefreshDatabase;

    private function enableTelegram(): void
    {
        SiteSetting::setMany([
            'telegram_enabled' => '1',
            'telegram_bot_token' => '123456:TESTTOKEN',
            'telegram_webhook_secret' => 'webhook-secret-token',
            'telegram_bot_username' => 'teslatechbot',
        ]);
    }

    private function fakeTelegram(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 1,
                    'username' => 'teslatechbot',
                    'url' => 'https://example.test/webhooks/telegram',
                    'pending_update_count' => 0,
                ],
            ], 200),
        ]);
    }

    private function postUpdate(string $text, int|string $chatId = 99, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/telegram', [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => [
                    'id' => $chatId,
                    'username' => 'budiwa',
                    'first_name' => 'Budi',
                ],
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'text' => $text,
            ],
        ], array_merge([
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret-token',
        ], $headers));
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

    #[Test]
    public function guests_cannot_open_messaging_page(): void
    {
        $this->get('/admin/messaging')->assertRedirect('/admin/login');
    }

    #[Test]
    public function admin_can_open_messaging_page_without_calling_channel_apis(): void
    {
        $this->enableTelegram();
        SiteSetting::setMany([
            'whatsapp_enabled' => '1',
            'whatsapp_base_url' => 'http://127.0.0.1:9',
            'whatsapp_api_key' => 'evo-key',
            'whatsapp_instance' => 'teslatech',
        ]);

        Http::fake(function () {
            $this->fail('Membuka halaman Notifikasi & Bot tidak boleh memanggil API kanal.');
        });

        $this->actingAs(User::factory()->superadmin()->create())
            ->get('/admin/messaging')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Messaging/Index')
                ->has('config.telegram')
                ->has('config.whatsapp')
                ->has('identities')
                ->has('logs'));
    }

    #[Test]
    public function admin_can_open_and_save_telegram_settings(): void
    {
        $this->fakeTelegram();
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->get('/admin/messaging')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Messaging/Index'));

        $this->actingAs($admin)
            ->post('/admin/messaging', [
                'telegram_enabled' => '1',
                'telegram_bot_token' => '123456:NEWTOKEN',
                'telegram_admin_chat_id' => '99',
            ])
            ->assertRedirect('/admin/messaging');

        $this->assertSame('1', SiteSetting::getValue('telegram_enabled'));
        $this->assertSame('123456:NEWTOKEN', SiteSetting::getValue('telegram_bot_token'));
        $this->assertSame('99', SiteSetting::getValue('telegram_admin_chat_id'));
    }

    #[Test]
    public function telegram_status_endpoint_reads_webhook_info(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();

        $this->actingAs(User::factory()->superadmin()->create())
            ->getJson('/admin/messaging/telegram/status')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    #[Test]
    public function webhook_rejects_invalid_secret(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();

        $this->postJson('/webhooks/telegram', [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => 1, 'type' => 'private'],
                'text' => '/bantuan',
            ],
        ])->assertForbidden();
    }

    #[Test]
    public function bantuan_replies_with_available_commands(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();

        $this->postUpdate('/bantuan')->assertOk();

        $this->assertDatabaseHas('message_logs', [
            'channel' => 'telegram',
            'direction' => 'outbound',
            'command' => 'bantuan',
            'status' => 'sent',
            'external_id' => '99',
        ]);

        $body = MessageLog::query()->where('direction', 'outbound')->value('body');
        $this->assertStringContainsString('/daftar', (string) $body);
        $this->assertStringContainsString('/tagihan', (string) $body);
        $this->assertStringContainsString('/bayar', (string) $body);
        $this->assertStringNotContainsString('/cari', (string) $body);
    }

    #[Test]
    public function daftar_with_username_and_phone_binds_customer(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();
        $this->customer();

        $this->postUpdate('/daftar budi01 081234567890')->assertOk();

        $this->assertDatabaseHas('messaging_identities', [
            'channel' => 'telegram',
            'external_id' => '99',
            'username' => 'budiwa',
        ]);

        $identity = MessagingIdentity::query()->first();
        $this->assertSame('budi01', $identity?->customer?->username);
    }

    #[Test]
    public function daftar_rejects_wrong_phone(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();
        $this->customer();

        $this->postUpdate('/daftar budi01 081111111111')->assertOk();

        $this->assertDatabaseCount('messaging_identities', 0);
        $body = MessageLog::query()->where('direction', 'outbound')->value('body');
        $this->assertStringContainsString('tidak cocok', (string) $body);
    }

    #[Test]
    public function two_step_daftar_then_phone_binds(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();
        $this->customer();

        $this->postUpdate('/daftar budi01')->assertOk();
        $this->assertDatabaseCount('messaging_identities', 0);

        $this->postUpdate('081234567890')->assertOk();
        $this->assertDatabaseHas('messaging_identities', [
            'channel' => 'telegram',
            'external_id' => '99',
        ]);
    }

    #[Test]
    public function tagihan_requires_binding_then_lists_unpaid_invoices(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();
        $customer = $this->customer();

        $this->postUpdate('/tagihan')->assertOk();
        $unbound = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('/daftar', (string) $unbound);

        MessagingIdentity::query()->create([
            'pppoe_customer_id' => $customer->id,
            'channel' => 'telegram',
            'external_id' => '99',
            'verified_at' => now(),
        ]);

        Invoice::query()->create([
            'number' => 'INV-100',
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

        $this->postUpdate('/tagihan')->assertOk();
        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('INV-100', (string) $body);
        $this->assertStringContainsString('150.000', (string) $body);
        $this->assertStringContainsString('/bayar', (string) $body);
    }

    #[Test]
    public function bayar_sends_checkout_url_when_gateway_ready(): void
    {
        $this->enableTelegram();
        $this->fakeTelegram();
        $customer = $this->customer();

        MessagingIdentity::query()->create([
            'pppoe_customer_id' => $customer->id,
            'channel' => 'telegram',
            'external_id' => '99',
            'verified_at' => now(),
        ]);

        $invoice = Invoice::query()->create([
            'number' => 'INV-200',
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

        $gateways = Mockery::mock(PaymentGatewayManager::class);
        $gateways->shouldReceive('hasEnabledGateway')->andReturn(true);
        $gateways->shouldReceive('createPayment')
            ->once()
            ->withArgs(fn ($passed) => $passed->is($invoice))
            ->andReturn([
                'checkout_url' => 'https://pay.example/abc',
                'transaction' => Mockery::mock(),
            ]);
        $this->app->instance(PaymentGatewayManager::class, $gateways);

        $this->postUpdate('/bayar')->assertOk();

        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('https://pay.example/abc', (string) $body);
        $this->assertStringContainsString('INV-200', (string) $body);
    }

    #[Test]
    public function admin_can_unbind_identity(): void
    {
        $admin = User::factory()->superadmin()->create();
        $customer = $this->customer();
        $identity = MessagingIdentity::query()->create([
            'pppoe_customer_id' => $customer->id,
            'channel' => 'telegram',
            'external_id' => '99',
            'verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete('/admin/messaging/identities/'.$identity->id)
            ->assertRedirect();

        $this->assertDatabaseCount('messaging_identities', 0);
    }

    private function enableAdminChat(int|string $chatId = 99): void
    {
        SiteSetting::setMany([
            'telegram_admin_chat_id' => (string) $chatId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastTelegramPayload(string $method = 'sendMessage'): ?array
    {
        foreach (Http::recorded()->reverse() as [$request]) {
            if (str_contains($request->url(), $method)) {
                return $request->data();
            }
        }

        return null;
    }

    private function postCallback(string $data, int|string $chatId = 99): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/webhooks/telegram', [
            'update_id' => 2,
            'callback_query' => [
                'id' => 'cb-1',
                'from' => [
                    'id' => $chatId,
                    'username' => 'adminwa',
                    'first_name' => 'Admin',
                ],
                'message' => [
                    'message_id' => 20,
                    'chat' => [
                        'id' => $chatId,
                        'type' => 'private',
                    ],
                ],
                'data' => $data,
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'webhook-secret-token',
        ]);
    }

    private function mockGenieDevice(array $device = []): void
    {
        $genie = Mockery::mock(GenieAcsService::class);
        $genie->shouldReceive('isConfigured')->andReturn(true);
        $genie->shouldReceive('findDeviceByPppoeUsername')->andReturn([
            'ok' => true,
            'device' => array_merge([
                'id' => 'DEV1',
                'manufacturer' => 'ZTE',
                'model' => 'F670L',
                'serial' => 'SN123',
                'ssid' => 'RumahBudi',
                'ssid_password' => 'wifiRahasia',
                'rx_power' => -18.5,
                'rx_power_label' => '-18.5 dBm',
                'tx_power_label' => '2.1 dBm',
                'redaman_label' => '20.6 dB',
                'temperature' => 48,
                'temperature_label' => '48 °C',
                'online' => true,
                'last_inform_label' => '23/08/2026 01:20',
                'connected_count' => 2,
                'connected_clients' => [
                    [
                        'name' => 'iPhone-Budi',
                        'hostname' => 'iPhone-Budi',
                        'mac' => 'AA:BB:CC:DD:EE:01',
                        'ip' => '192.168.1.10',
                        'ssid' => 'RumahBudi',
                    ],
                    [
                        'name' => 'Laptop-Siti',
                        'hostname' => 'Laptop-Siti',
                        'mac' => 'AA:BB:CC:DD:EE:02',
                        'ip' => '192.168.1.11',
                        'ssid' => 'RumahBudi',
                    ],
                ],
            ], $device),
        ]);
        $genie->shouldReceive('updateWifi')->andReturn([
            'ok' => true,
            'message' => 'SSID dan password diantrikan ke perangkat.',
        ]);
        $this->app->instance(GenieAcsService::class, $genie);
    }

    #[Test]
    public function admin_bantuan_lists_cari_command(): void
    {
        $this->enableTelegram();
        $this->enableAdminChat();
        $this->fakeTelegram();

        $this->postUpdate('/bantuan')->assertOk();

        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('/cari', (string) $body);
    }

    #[Test]
    public function cari_is_rejected_for_non_admin(): void
    {
        $this->enableTelegram();
        $this->enableAdminChat(55);
        $this->fakeTelegram();
        $this->customer();

        $this->postUpdate('/cari Budi', 99)->assertOk();

        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('khusus admin', (string) $body);
        $this->assertStringContainsString('99', (string) $body);
    }

    #[Test]
    public function admin_cari_shows_customer_menu(): void
    {
        $this->enableTelegram();
        $this->enableAdminChat();
        $this->fakeTelegram();
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
            'mikrotik_profile' => 'paket-10m',
            'is_active' => true,
        ]);
        $customer = $this->customer([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'service_profile' => 'paket-10m',
        ]);

        $this->postUpdate('/cari Budi Santoso')->assertOk();

        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('Pelanggan ditemukan', (string) $body);
        $this->assertStringContainsString('Budi Santoso', (string) $body);
        $this->assertStringContainsString('10 Mbps', (string) $body);

        $payload = $this->lastTelegramPayload();
        $this->assertNotNull($payload);
        $this->assertArrayHasKey('reply_markup', $payload);
        $buttons = collect($payload['reply_markup']['inline_keyboard'])->flatten(1)->pluck('text');
        $this->assertTrue($buttons->contains(fn ($text) => str_contains((string) $text, 'Profil layanan')));
        $this->assertTrue($buttons->contains(fn ($text) => str_contains((string) $text, 'RX Power')));
        $this->assertTrue($buttons->contains(fn ($text) => str_contains((string) $text, 'Suhu')));
        $this->assertTrue($buttons->contains(fn ($text) => str_contains((string) $text, 'Lihat SSID & Password')));
        $this->assertTrue($buttons->contains(fn ($text) => str_contains((string) $text, 'Edit SSID & Password')));
        $this->assertTrue($buttons->contains(fn ($text) => str_contains((string) $text, 'Perangkat terhubung')));
        $this->assertTrue($buttons->contains(fn ($text) => str_contains((string) $text, 'Tagihan')));
        $this->assertSame('cari:prof:'.$customer->id, $payload['reply_markup']['inline_keyboard'][0][0]['callback_data']);
    }

    #[Test]
    public function admin_cari_lists_multiple_matches(): void
    {
        $this->enableTelegram();
        $this->enableAdminChat();
        $this->fakeTelegram();
        $this->customer();
        $this->customer([
            'name' => 'Budi Hartono',
            'username' => 'budi02',
            'phone' => '081200000002',
        ]);

        $this->postUpdate('/cari Budi')->assertOk();

        $body = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('Ditemukan 2 pelanggan', (string) $body);
        $this->assertStringContainsString('Budi Hartono', (string) $body);
    }

    #[Test]
    public function admin_callback_shows_profile_and_bills(): void
    {
        $this->enableTelegram();
        $this->enableAdminChat();
        $this->fakeTelegram();
        $customer = $this->customer(['service_profile' => 'paket-10m']);

        Invoice::query()->create([
            'number' => 'INV-300',
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

        $this->postCallback('cari:prof:'.$customer->id)->assertOk();
        $profile = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('Profil layanan', (string) $profile);
        $this->assertStringContainsString('paket-10m', (string) $profile);

        $this->postCallback('cari:bill:'.$customer->id)->assertOk();
        $bill = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('INV-300', (string) $bill);
        $this->assertStringContainsString('150.000', (string) $bill);
    }

    #[Test]
    public function admin_callback_shows_rx_power_and_wifi(): void
    {
        $this->enableTelegram();
        $this->enableAdminChat();
        $this->fakeTelegram();
        $this->mockGenieDevice();
        $customer = $this->customer();

        $this->postCallback('cari:rx:'.$customer->id)->assertOk();
        $rx = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('-18.5 dBm', (string) $rx);
        $this->assertStringContainsString('SN123', (string) $rx);

        $this->postCallback('cari:wifi:'.$customer->id)->assertOk();
        $wifi = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('RumahBudi', (string) $wifi);
        $this->assertStringContainsString('••••••••', (string) $wifi);
        $this->assertStringContainsString('Jumlah perangkat terhubung: 2', (string) $wifi);
        $this->assertStringContainsString('iPhone-Budi', (string) $wifi);
        $this->assertStringNotContainsString('wifiRahasia', (string) $wifi);

        $wifiPayload = $this->lastTelegramPayload('editMessageText') ?? $this->lastTelegramPayload();
        $this->assertStringContainsString('wifiRahasia', (string) ($wifiPayload['text'] ?? ''));

        $this->postCallback('cari:dev:'.$customer->id)->assertOk();
        $devices = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('Perangkat terhubung', (string) $devices);
        $this->assertStringContainsString('Laptop-Siti', (string) $devices);
        $this->assertStringContainsString('192.168.1.10', (string) $devices);
    }

    #[Test]
    public function admin_can_edit_wifi_after_prompt(): void
    {
        $this->enableTelegram();
        $this->enableAdminChat();
        $this->fakeTelegram();
        $this->mockGenieDevice();
        $customer = $this->customer();

        $this->postCallback('cari:ewifi:'.$customer->id)->assertOk();
        $prompt = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('Edit WiFi', (string) $prompt);

        $this->postUpdate('RumahBaru | passwordbaru123')->assertOk();
        $done = MessageLog::query()->where('direction', 'outbound')->latest('id')->value('body');
        $this->assertStringContainsString('WiFi berhasil diubah', (string) $done);
        $this->assertStringContainsString('RumahBaru', (string) $done);
        $this->assertStringContainsString('••••••••', (string) $done);
    }
}
