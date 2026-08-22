<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\MessagingIdentity;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Models\User;
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
}
