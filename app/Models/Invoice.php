<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'pppoe_customer_id',
        'subscription_package_id',
        'type',
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
            'paid_at' => 'datetime',
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

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'pppoe_customer_id' => $this->pppoe_customer_id,
            'subscription_package_id' => $this->subscription_package_id,
            'type' => $this->type,
            'type_label' => match ($this->type) {
                'prorata' => 'Prorata',
                'monthly' => 'Bulanan',
                'adjustment' => 'Penyesuaian',
                default => $this->type,
            },
            'period_start' => $this->period_start?->format('Y-m-d'),
            'period_end' => $this->period_end?->format('Y-m-d'),
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
            'is_overdue' => $this->isOverdue(),
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'username' => $this->customer->username,
                'phone' => $this->customer->phone,
                'due_date' => $this->customer->due_date?->format('Y-m-d'),
                'status' => $this->customer->status,
                'billing_day' => $this->customer->billing_day,
            ] : null,
            'payments' => $this->relationLoaded('payments')
                ? $this->payments->map(fn (Payment $payment) => $payment->toAdminArray())->values()->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
