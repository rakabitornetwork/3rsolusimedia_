<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PppoeDailyUsage extends Model
{
    protected $fillable = [
        'pppoe_customer_id',
        'usage_date',
        'rx_bytes',
        'tx_bytes',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'rx_bytes' => 'integer',
            'tx_bytes' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }
}
