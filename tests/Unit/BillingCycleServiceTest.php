<?php

namespace Tests\Unit;

use App\Services\BillingCycleService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingCycleServiceTest extends TestCase
{
    private BillingCycleService $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cycle = new BillingCycleService;
        Carbon::setTestNow(Carbon::parse('2026-08-22')->startOfDay());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function payment_advances_from_invoice_due_when_result_is_still_in_the_future(): void
    {
        $next = $this->cycle->dueDateAfterPayment('2026-08-15', 15, 2);

        $this->assertSame('2026-10-15', $next->toDateString());
    }

    #[Test]
    public function two_month_extension_for_isolated_customer_starts_from_today(): void
    {
        // Due lama 20 Juni + 2 bulan = 20 Agustus, masih lewat (hari ini 22 Agu).
        // Harus dihitung ulang dari hari ini agar isolir terbuka.
        $next = $this->cycle->dueDateAfterPayment('2026-06-20', 20, 2);

        $this->assertSame('2026-10-20', $next->toDateString());
        $this->assertTrue($next->greaterThan(now()->startOfDay()));
    }

    #[Test]
    public function two_month_extension_landing_on_today_is_reanchored(): void
    {
        // Due 22 Juni + 2 bulan = 22 Agustus (hari ini) — masih harus dihitung ulang.
        $next = $this->cycle->dueDateAfterPayment('2026-06-22', 22, 2);

        $this->assertSame('2026-10-22', $next->toDateString());
    }

    #[Test]
    public function on_time_one_month_payment_keeps_the_regular_cycle(): void
    {
        $next = $this->cycle->dueDateAfterPayment('2026-08-22', 22, 1);

        $this->assertSame('2026-09-22', $next->toDateString());
    }

    #[Test]
    public function first_due_honors_explicit_september_date_when_start_is_august(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $due = $this->cycle->firstDueDate('2026-08-20', 20, '2026-09-20');

        $this->assertSame('2026-09-20', $due->toDateString());
    }

    #[Test]
    public function first_due_does_not_skip_to_october_when_today_is_the_billing_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20')->startOfDay());

        $due = $this->cycle->firstDueDate('2026-08-20', 20, '2026-09-20');

        $this->assertSame('2026-09-20', $due->toDateString());
        $this->assertNotSame('2026-10-20', $due->toDateString());
    }

    #[Test]
    public function next_due_date_from_august_20_is_september_not_october(): void
    {
        $next = $this->cycle->nextDueDate('2026-08-20', 20);

        $this->assertSame('2026-09-20', $next->toDateString());
    }
}
