<?php

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicePeriodLabelTest extends TestCase
{
    #[Test]
    public function period_label_shows_usage_through_the_day_before_period_end(): void
    {
        $invoice = new Invoice([
            'period_start' => '2026-10-11',
            'period_end' => '2026-11-11',
            'due_date' => '2026-11-11',
        ]);

        $this->assertSame('2026-11-10', $invoice->usagePeriodEnd()?->toDateString());
        $this->assertSame('2026-10-11 s/d 2026-11-10', $invoice->periodLabel());
        $this->assertSame('11/10/2026 — 10/11/2026', $invoice->periodLabel('d/m/Y', ' — '));
    }

    #[Test]
    public function period_label_does_not_go_before_period_start(): void
    {
        $invoice = new Invoice([
            'period_start' => '2026-11-11',
            'period_end' => '2026-11-11',
            'due_date' => '2026-11-11',
        ]);

        $this->assertSame('2026-11-11', $invoice->usagePeriodEnd()?->toDateString());
        $this->assertSame('2026-11-11 s/d 2026-11-11', $invoice->periodLabel());
    }

    #[Test]
    public function admin_array_includes_period_label(): void
    {
        $invoice = new Invoice([
            'number' => 'INV/2026/11/0001',
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-10-11',
            'period_end' => '2026-11-11',
            'due_date' => '2026-11-11',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
        ]);

        $payload = $invoice->toAdminArray();

        $this->assertSame('2026-10-11', $payload['period_start']);
        $this->assertSame('2026-11-11', $payload['period_end']);
        $this->assertSame('2026-11-10', $payload['period_usage_end']);
        $this->assertSame('2026-10-11 s/d 2026-11-10', $payload['period_label']);
    }
}
