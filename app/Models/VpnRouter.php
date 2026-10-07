<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpnRouter extends Model
{
    protected $fillable = [
        'pppoe_customer_id',
        'name',
        'vpn_remote_address',
        'vpn_port_series',
        'billing_day',
        'service_until',
    ];

    protected function casts(): array
    {
        return [
            'vpn_port_series' => 'integer',
            'billing_day' => 'integer',
            'service_until' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PppoeCustomer::class, 'pppoe_customer_id');
    }

    public function portForwards(): HasMany
    {
        return $this->hasMany(VpnPortForward::class)->orderBy('public_port');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isUsable(): bool
    {
        return $this->service_until !== null
            && $this->service_until->copy()->startOfDay()->greaterThanOrEqualTo(now()->startOfDay());
    }
}
