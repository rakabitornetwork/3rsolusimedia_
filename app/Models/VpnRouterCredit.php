<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpnRouterCredit extends Model
{
    protected $fillable = [
        'pppoe_customer_id',
        'billing_day',
        'included',
        'service_until',
        'invoice_ids',
    ];

    protected function casts(): array
    {
        return [
            'billing_day' => 'integer',
            'included' => 'boolean',
            'service_until' => 'date',
            'invoice_ids' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }
}
