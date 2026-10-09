<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Services\BillingService;
use App\Services\MikrotikApiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingEarlyPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function automatic_generate_waits_but_prepare_creates_the_current_cycle_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Jakarta'));

        [$admin, $customer] = $this->customerDueOn('2026-10-20');

        $this->actingAs($admin)
            ->get('/admin/billing')
            ->assertOk();

        $this->assertSame(1, Invoice::query()->count());

        $this->actingAs($admin)
            ->post('/admin/billing/prepare', ['username' => 'budi01'])
            ->assertRedirect();

        $invoice = Invoice::query()->where('status', 'unpaid')->first();
        $this->assertNotNull($invoice);
        $this->assertSame('monthly', $invoice->type);
        $this->assertSame('2026-09-20', $invoice->period_start?->toDateString());
        $this->assertSame('2026-10-20', $invoice->due_date?->toDateString());
        $this->assertSame(150000, $invoice->total);

        $this->actingAs($admin)
            ->post('/admin/billing/prepare', ['username' => 'budi01'])
            ->assertRedirect('/admin/billing/invoices/'.$invoice->id);

        $this->assertSame(1, Invoice::query()->where('status', 'unpaid')->count());
    }

    #[Test]
    public function shared_names_must_be_chosen_before_an_invoice_is_created(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Jakarta'));

        [$admin, $first] = $this->customerDueOn('2026-10-20');
        $first->update([
            'phone' => '081111111111',
            'address' => 'Jl. Mawar 1',
        ]);
        $second = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $first->mikrotik_router_id,
            'subscription_package_id' => $first->subscription_package_id,
            'name' => 'Budi Santoso',
            'phone' => '082222222222',
            'address' => 'Jl. Melati 9',
            'username' => 'budi02',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-01-20',
            'billing_day' => 20,
            'due_date' => '2026-10-20',
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);
        Invoice::query()->create([
            'number' => 'INV/2026/09/0002',
            'pppoe_customer_id' => $second->id,
            'subscription_package_id' => $first->subscription_package_id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-08-20',
            'period_end' => '2026-09-20',
            'due_date' => '2026-09-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'paid',
            'paid_at' => '2026-09-18 10:00:00',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        $this->actingAs($admin)
            ->from('/admin/billing')
            ->post('/admin/billing/prepare', ['username' => 'Budi Santoso'])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('early_customers', function (array $customers) {
                $usernames = collect($customers)->pluck('username')->sort()->values()->all();

                return count($customers) === 2
                    && $usernames === ['budi01', 'budi02']
                    && collect($customers)->pluck('phone')->sort()->values()->all() === ['081111111111', '082222222222'];
            });

        $this->assertSame(0, Invoice::query()->where('status', 'unpaid')->count());

        $this->actingAs($admin)
            ->post('/admin/billing/prepare', [
                'customer_id' => $second->id,
                'username' => 'Budi Santoso',
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('status', 'unpaid')->first();
        $this->assertNotNull($invoice);
        $this->assertSame($second->id, $invoice->pppoe_customer_id);
        $this->assertSame(1, Invoice::query()->where('status', 'unpaid')->count());
    }

    #[Test]
    public function prepare_refuses_the_next_cycle_before_it_starts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Jakarta'));

        [$admin] = $this->customerDueOn('2026-11-20');

        $this->actingAs($admin)
            ->from('/admin/billing')
            ->post('/admin/billing/prepare', ['username' => 'budi01'])
            ->assertRedirect('/admin/billing')
            ->assertSessionHas('error');

        $this->assertSame(0, Invoice::query()->where('status', 'unpaid')->count());
    }

    #[Test]
    public function portal_shows_the_current_bill_before_the_generate_window_and_not_the_following_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Jakarta'));

        [, $customer] = $this->customerDueOn('2026-10-20');
        $token = Str::lower(Str::random(48));
        Cache::put('portal_pay:'.$token, $customer->id, now()->addHours(2));

        $this->get('/portal/'.$token.'/tagihan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/Pay/Show', false)
                ->has('unpaid', 1)
                ->where('unpaid.0.due_date', '2026-10-20')
                ->where('unpaid.0.total', 150000)
            );

        $invoice = Invoice::query()->where('status', 'unpaid')->first();
        $this->assertNotNull($invoice);

        $api = Mockery::mock(MikrotikApiService::class);
        $api->shouldReceive('upsertPppSecret')->andReturn([
            'ok' => true,
            'message' => 'Secret PPPoE berhasil diperbarui di RouterOS.',
        ]);
        $this->app->instance(MikrotikApiService::class, $api);

        app(BillingService::class)->markPaid($invoice);
        $customer->refresh();
        $this->assertSame('2026-11-20', $customer->due_date?->toDateString());

        $this->get('/portal/'.$token.'/tagihan')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('unpaid', 0)
            );

        $this->assertSame(0, Invoice::query()->where('status', 'unpaid')->count());
    }

    /**
     * @return array{0: User, 1: PppoeCustomer}
     */
    private function customerDueOn(string $dueDate): array
    {
        $admin = User::factory()->superadmin()->create();
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
            'mikrotik_profile' => '10Mbps',
            'is_active' => true,
        ]);
        $customer = PppoeCustomer::query()->create([
            'mikrotik_router_id' => $router->id,
            'subscription_package_id' => $package->id,
            'name' => 'Budi Santoso',
            'username' => 'budi01',
            'password' => 'secret',
            'service_profile' => '10Mbps',
            'start_date' => '2026-01-20',
            'billing_day' => 20,
            'due_date' => $dueDate,
            'overdue_action' => 'bypass',
            'status' => 'active',
            'sync_status' => 'synced',
            'is_active' => true,
        ]);

        Invoice::query()->create([
            'number' => 'INV/2026/09/0001',
            'pppoe_customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-08-20',
            'period_end' => '2026-09-20',
            'due_date' => '2026-09-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'paid',
            'paid_at' => '2026-09-18 10:00:00',
            'package_name' => '10 Mbps',
            'package_price' => 150000,
        ]);

        return [$admin, $customer];
    }
}
