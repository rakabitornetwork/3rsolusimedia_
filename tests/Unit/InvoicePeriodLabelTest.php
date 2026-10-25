<?php

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoicePeriodLabelTest extends TestCase
{
    #[Test]
    public function monthly_invoice_shows_cycle_through_day_before_due(): void
    {
        $invoice = new Invoice([
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-09-20',
            'period_end' => '2026-10-20',
            'due_date' => '2026-10-20',
        ]);

        $this->assertSame('2026-09-20', $invoice->usagePeriodStart()?->toDateString());
        $this->assertSame('2026-10-19', $invoice->usagePeriodEnd()?->toDateString());
        $this->assertSame('2026-09-20 s/d 2026-10-19', $invoice->periodLabel());
        $this->assertSame('20/09/2026 — 19/10/2026', $invoice->periodLabel('d/m/Y', ' — '));
    }

    #[Test]
    public function prorata_invoice_keeps_actual_start_through_day_before_due(): void
    {
        $invoice = new Invoice([
            'type' => 'prorata',
            'billing_months' => 1,
            'period_start' => '2026-10-20',
            'period_end' => '2026-11-11',
            'due_date' => '2026-11-11',
        ]);

        $this->assertSame('2026-10-20', $invoice->usagePeriodStart()?->toDateString());
        $this->assertSame('2026-11-10', $invoice->usagePeriodEnd()?->toDateString());
        $this->assertSame('2026-10-20 s/d 2026-11-10', $invoice->periodLabel());
    }

    #[Test]
    public function multi_month_invoice_shows_covered_span_not_shifted_back(): void
    {
        $invoice = new Invoice([
            'type' => 'multi_month',
            'billing_months' => 3,
            'period_start' => '2026-10-11',
            'period_end' => '2027-01-11',
            'due_date' => '2026-11-11',
        ]);

        $this->assertSame('2026-10-11', $invoice->usagePeriodStart()?->toDateString());
        $this->assertSame('2027-01-10', $invoice->usagePeriodEnd()?->toDateString());
        $this->assertSame('2026-10-11 s/d 2027-01-10', $invoice->periodLabel());
    }

    #[Test]
    public function admin_array_includes_usage_period_label(): void
    {
        $invoice = new Invoice([
            'number' => 'INV/2026/10/0001',
            'type' => 'monthly',
            'billing_months' => 1,
            'period_start' => '2026-09-20',
            'period_end' => '2026-10-20',
            'due_date' => '2026-10-20',
            'amount' => 150000,
            'discount' => 0,
            'total' => 150000,
            'status' => 'unpaid',
        ]);

        $payload = $invoice->toAdminArray();

        $this->assertSame('2026-09-20', $payload['period_start']);
        $this->assertSame('2026-10-20', $payload['period_end']);
        $this->assertSame('2026-09-20', $payload['period_usage_start']);
        $this->assertSame('2026-10-19', $payload['period_usage_end']);
        $this->assertSame('2026-09-20 s/d 2026-10-19', $payload['period_label']);
    }
}
