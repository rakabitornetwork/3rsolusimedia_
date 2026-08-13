<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'invoice_id',
        'gateway',
        'external_id',
        'gateway_reference',
        'amount',
        'status',
        'checkout_url',
        'raw_request',
        'raw_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'raw_request' => 'array',
            'raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'gateway' => $this->gateway,
            'gateway_label' => match ($this->gateway) {
                'xendit' => 'Xendit',
                'midtrans' => 'Midtrans',
                'duitku' => 'Duitku',
                default => $this->gateway,
            },
            'external_id' => $this->external_id,
            'gateway_reference' => $this->gateway_reference,
            'amount' => $this->amount,
            'amount_label' => 'Rp '.number_format($this->amount, 0, ',', '.'),
            'status' => $this->status,
            'status_label' => match ($this->status) {
                'pending' => 'Menunggu',
                'paid' => 'Lunas',
                'expired' => 'Kedaluwarsa',
                'failed' => 'Gagal',
                'cancelled' => 'Dibatalkan',
                default => $this->status,
            },
            'checkout_url' => $this->checkout_url,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
