<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PppoeTrafficCursor extends Model
{
    protected $fillable = [
        'pppoe_customer_id',
        'last_rx_byte',
        'last_tx_byte',
        'sampled_at',
    ];

    protected function casts(): array
    {
        return [
            'last_rx_byte' => 'integer',
            'last_tx_byte' => 'integer',
            'sampled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }
}
