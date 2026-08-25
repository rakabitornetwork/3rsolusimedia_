<?php

namespace Tests\Feature;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SiteSetting;
use App\Services\MikrotikApiService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncOverduePppoeCustomersTest extends TestCase
{
    use RefreshDatabase;

    private function freezeJakarta(string $datetime): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse($datetime, 'Asia/Jakarta'));
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
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'isolir_profile' => 'ISOLIR',
            'overdue_action' => 'isolir',
            'due_date' => '2026-08-25',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function customer_is_not_isolated_on_due_date(): void
    {
        $this->freezeJakarta('2026-08-25 23:59:00');
        SiteSetting::setValue('app_auto_isolir', '1');

        $customer = $this->customer();

        $this->assertFalse($customer->isOverdue());
        $this->assertFalse($customer->shouldIsolir());
    }

    #[Test]
    public function customer_is_isolated_after_midnight_past_due_date(): void
    {
        $this->freezeJakarta('2026-08-26 00:00:00');
        SiteSetting::setValue('app_auto_isolir', '1');

        $customer = $this->customer(['due_date' => '2026-08-25']);

        $this->assertTrue($customer->isOverdue());
        $this->assertTrue($customer->shouldIsolir());

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')
            ->once()
            ->withArgs(function (
                $router,
                string $username,
                string $password,
                ?string $profile,
                ?string $comment,
                bool $disabled,
                bool $disconnectActive,
            ) {
                return $username === 'budi01'
                    && $profile === 'ISOLIR'
                    && $disabled === false
                    && $disconnectActive === true;
            })
            ->andReturn([
                'ok' => true,
                'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
            ]);
        $this->app->instance(MikrotikApiService::class, $api);

        $this->artisan('pppoe:sync-overdue')
            ->expectsOutputToContain('1 diisolir')
            ->assertSuccessful();

        $this->assertSame('isolated', $customer->fresh()->status);
    }

    #[Test]
    public function overdue_sync_is_scheduled_daily_at_midnight(): void
    {
        $events = collect(app(Schedule::class)->events());
        $event = $events->first(
            fn ($scheduled) => str_contains((string) $scheduled->command, 'pppoe:sync-overdue')
        );

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', (string) $event->timezone);
    }
}
