<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'received_by',
        'amount',
        'method',
        'paid_at',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'amount' => $this->amount,
            'amount_label' => 'Rp '.number_format($this->amount, 0, ',', '.'),
            'method' => $this->method,
            'method_label' => match ($this->method) {
                'cash' => 'Tunai',
                'transfer' => 'Transfer',
                'qris' => 'QRIS',
                'other' => 'Lainnya',
                'xendit' => 'Xendit',
                'midtrans' => 'Midtrans',
                'duitku' => 'Duitku',
                default => $this->method,
            },
            'paid_at' => $this->paid_at?->toIso8601String(),
            'reference' => $this->reference,
            'notes' => $this->notes,
            'received_by' => $this->received_by,
            'receiver_name' => $this->relationLoaded('receiver')
                ? $this->receiver?->name
                : null,
        ];
    }
}
