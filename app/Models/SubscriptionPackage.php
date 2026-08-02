<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    protected $fillable = [
        'mikrotik_router_id',
        'name',
        'price',
        'mikrotik_profile',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'mikrotik_router_id' => 'integer',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(PppoeCustomer::class);
    }

    public function toOptionArray(): array
    {
        return [
            'id' => $this->id,
            'mikrotik_router_id' => $this->mikrotik_router_id,
            'router_name' => $this->router?->name,
            'name' => $this->name,
            'price' => $this->price,
            'price_label' => 'Rp '.number_format($this->price, 0, ',', '.'),
            'mikrotik_profile' => $this->mikrotik_profile,
            'description' => $this->description,
            'is_active' => $this->is_active,
        ];
    }
}
