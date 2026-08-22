<?php

namespace Tests\Unit;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Services\GenieAcsService;
use App\Services\Messaging\AdminCustomerLookup;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminCustomerLookupTest extends TestCase
{
    use RefreshDatabase;

    private function lookup(): AdminCustomerLookup
    {
        $genie = Mockery::mock(GenieAcsService::class);
        $genie->shouldReceive('isConfigured')->andReturn(false);

        return new AdminCustomerLookup($genie);
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
    public function it_searches_customers_by_name_username_and_phone(): void
    {
        $this->customer();
        $this->customer([
            'name' => 'Siti Aminah',
            'username' => 'siti01',
            'phone' => '081298765432',
        ]);

        $lookup = $this->lookup();

        $this->assertSame('budi01', $lookup->search('Budi')->first()?->username);
        $this->assertSame('siti01', $lookup->search('siti01')->first()?->username);
        $this->assertSame('siti01', $lookup->search('081298765432')->first()?->username);
        $this->assertCount(0, $lookup->search('xyz'));
        $this->assertCount(0, $lookup->search('a'));
    }

    #[Test]
    public function it_parses_wifi_input_formats(): void
    {
        $lookup = $this->lookup();

        $this->assertSame(
            ['ssid' => 'RumahBudi', 'password' => 'passwordbaru123'],
            $lookup->parseWifiInput('RumahBudi | passwordbaru123'),
        );
        $this->assertSame(
            ['ssid' => 'RumahBudi', 'password' => null],
            $lookup->parseWifiInput('RumahBudi'),
        );
        $this->assertSame(
            ['ssid' => null, 'password' => 'passwordbaru123'],
            $lookup->parseWifiInput('*passwordbaru123'),
        );
        $this->assertArrayHasKey('error', $lookup->parseWifiInput('*short'));
        $this->assertArrayHasKey('error', $lookup->parseWifiInput(''));
    }

    #[Test]
    public function it_parses_callback_payloads(): void
    {
        $lookup = $this->lookup();

        $this->assertSame(
            ['action' => 'wifi', 'customer_id' => 12],
            $lookup->parseCallback('cari:wifi:12'),
        );
        $this->assertNull($lookup->parseCallback('lain:wifi:12'));
        $this->assertNull($lookup->parseCallback('cari:wifi'));
    }

    #[Test]
    public function it_reads_multiple_admin_chat_ids(): void
    {
        \App\Models\SiteSetting::setMany([
            'telegram_admin_chat_id' => '99, 100; 101 102',
        ]);

        $this->assertSame(['99', '100', '101', '102'], AppSettings::telegramAdminChatIds());
        $this->assertTrue(AppSettings::isTelegramAdminChat('100'));
        $this->assertFalse(AppSettings::isTelegramAdminChat('7'));
    }
}
