<?php

namespace App\Models;

use App\Services\BillingCycleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'pppoe_customer_id',
        'vpn_router_id',
        'subscription_package_id',
        'type',
        'billing_months',
        'period_start',
        'period_end',
        'due_date',
        'amount',
        'discount',
        'total',
        'status',
        'paid_at',
        'package_name',
        'package_price',
        'notes',
        'agent_cash',
        'agent_ready_tf',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'amount' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'package_price' => 'integer',
            'billing_months' => 'integer',
            'paid_at' => 'datetime',
            'agent_cash' => 'boolean',
            'agent_ready_tf' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    public function isOverdue(): bool
    {
        return $this->isUnpaid()
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    /**
     * Awal periode pemakaian untuk ditampilkan.
     *
     * Tagihan bulanan penuh (period_start…period_end = satu siklus ke jatuh tempo)
     * menagih siklus sebelumnya: bayar di November untuk pemakaian Sep→Okt.
     */
    public function usagePeriodStart(): ?Carbon
    {
        return $this->usagePeriodBounds()[0];
    }

    /**
     * Akhir periode pemakaian untuk ditampilkan (inklusif).
     */
    public function usagePeriodEnd(): ?Carbon
    {
        return $this->usagePeriodBounds()[1];
    }

    /**
     * Label periode pemakaian, mis. "2026-09-11 s/d 2026-10-10".
     */
    public function periodLabel(string $format = 'Y-m-d', string $separator = ' s/d '): ?string
    {
        [$start, $end] = $this->usagePeriodBounds();
        if (! $start || ! $end) {
            return null;
        }

        return $start->format($format).$separator.$end->format($format);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function usagePeriodBounds(): array
    {
        if (! $this->period_start || ! $this->period_end) {
            return [null, null];
        }

        $storedStart = $this->period_start->copy()->startOfDay();
        $storedEnd = $this->period_end->copy()->startOfDay();
        $cycle = app(BillingCycleService::class);
        $billingDay = $this->billingDayForPeriod();
        $months = max(1, (int) ($this->billing_months ?: 1));

        $isFullSingleCycle = $months === 1
            && $this->type !== 'vpn_router'
            && $cycle->periodStart($storedEnd, $billingDay)->equalTo($storedStart);

        if ($isFullSingleCycle) {
            // Siklus tersimpan Oct→Nov due Nov = tagihan untuk pemakaian Sep→Okt.
            return [
                $cycle->periodStart($storedStart, $billingDay),
                $storedStart->copy()->subDay(),
            ];
        }

        $end = $storedEnd->copy()->subDay();
        if ($end->lessThan($storedStart)) {
            $end = $storedEnd->copy();
        }

        return [$storedStart, $end];
    }

    private function billingDayForPeriod(): int
    {
        if ($this->relationLoaded('customer') && $this->customer?->billing_day) {
            return app(BillingCycleService::class)
                ->normalizeBillingDay((int) $this->customer->billing_day);
        }

        $anchor = $this->due_date ?? $this->period_end;

        return app(BillingCycleService::class)
            ->normalizeBillingDay((int) ($anchor?->day ?? 1));
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'pppoe_customer_id' => $this->pppoe_customer_id,
            'subscription_package_id' => $this->subscription_package_id,
            'type' => $this->type,
            'billing_months' => (int) ($this->billing_months ?: 1),
            'type_label' => match ($this->type) {
                'prorata' => 'Prorata',
                'monthly' => 'Bulanan',
                'multi_month' => 'Gabungan '.((int) ($this->billing_months ?: 2)).' bulan',
                'adjustment' => 'Penyesuaian',
                'vpn_router' => 'Router VPN',
                default => $this->type,
            },
            'period_start' => $this->period_start?->format('Y-m-d'),
            'period_end' => $this->period_end?->format('Y-m-d'),
            'period_usage_start' => $this->usagePeriodStart()?->format('Y-m-d'),
            'period_usage_end' => $this->usagePeriodEnd()?->format('Y-m-d'),
            'period_label' => $this->periodLabel(),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'amount' => $this->amount,
            'discount' => $this->discount,
            'total' => $this->total,
            'total_label' => 'Rp '.number_format($this->total, 0, ',', '.'),
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'unpaid' => 'Belum bayar',
                'paid' => 'Lunas',
                'void' => 'Dibatalkan',
                default => $this->status,
            },
            'paid_at' => $this->paid_at?->toIso8601String(),
            'package_name' => $this->package_name,
            'package_price' => $this->package_price,
            'package_price_label' => $this->package_price !== null
                ? 'Rp '.number_format($this->package_price, 0, ',', '.')
                : null,
            'notes' => $this->notes,
            'agent_cash' => (bool) $this->agent_cash,
            'agent_ready_tf' => (bool) $this->agent_ready_tf,
            'is_overdue' => $this->isOverdue(),
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'username' => $this->customer->username,
                'phone' => $this->customer->phone,
                'due_date' => $this->customer->due_date?->format('Y-m-d'),
                'status' => $this->customer->status,
                'billing_day' => $this->customer->billing_day,
                'grace_until' => $this->customer->grace_until?->format('Y-m-d'),
                'grace_note' => $this->customer->grace_note,
                'has_active_grace' => $this->customer->hasActiveGrace(),
                'package_price' => $this->customer->relationLoaded('package')
                    ? $this->customer->package?->price
                    : ($this->package_price ?? null),
                'router' => $this->customer->relationLoaded('router') && $this->customer->router
                    ? [
                        'id' => $this->customer->router->id,
                        'name' => $this->customer->router->name,
                    ]
                    : null,
            ] : null,
            'payments' => $this->relationLoaded('payments')
                ? $this->payments->map(fn (Payment $payment) => $payment->toAdminArray())->values()->all()
                : [],
            'online_transactions' => $this->relationLoaded('paymentTransactions')
                ? $this->paymentTransactions
                    ->sortByDesc('id')
                    ->take(5)
                    ->values()
                    ->map(fn (PaymentTransaction $tx) => $tx->toAdminArray())
                    ->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
