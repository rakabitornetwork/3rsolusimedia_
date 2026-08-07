<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PppoeCustomer extends Model
{
    protected $fillable = [
        'mikrotik_router_id',
        'subscription_package_id',
        'name',
        'phone',
        'address',
        'latitude',
        'longitude',
        'username',
        'password',
        'service_profile',
        'start_date',
        'billing_day',
        'due_date',
        'grace_until',
        'grace_note',
        'first_bill_amount',
        'first_bill_days',
        'overdue_action',
        'isolir_profile',
        'status',
        'sync_status',
        'sync_message',
        'last_synced_at',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'billing_day' => 'integer',
            'due_date' => 'date',
            'grace_until' => 'date',
            'first_bill_amount' => 'integer',
            'first_bill_days' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (! $value) {
                    return '';
                }

                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable) {
                    return $value;
                }
            },
            set: fn (?string $value) => $value !== null && $value !== ''
                ? Crypt::encryptString($value)
                : $value,
        );
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(MikrotikRouter::class, 'mikrotik_router_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'subscription_package_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && ! $this->due_date->isToday();
    }

    public function hasActiveGrace(): bool
    {
        if (! $this->grace_until) {
            return false;
        }

        $until = $this->grace_until->copy()->startOfDay();
        $today = now()->startOfDay();

        return $until->greaterThanOrEqualTo($today);
    }

    /**
     * Apakah pelanggan harus diisolir saat sync (menghormati grace + pengaturan auto isolir).
     */
    public function shouldIsolir(): bool
    {
        if (! \App\Support\AppSettings::bool('app_auto_isolir', true)) {
            return false;
        }

        if ($this->overdue_action !== 'isolir') {
            return false;
        }

        if (! $this->due_date || ! $this->isOverdue()) {
            return false;
        }

        return ! $this->hasActiveGrace();
    }

    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'mikrotik_router_id' => $this->mikrotik_router_id,
            'subscription_package_id' => $this->subscription_package_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'username' => $this->username,
            'service_profile' => $this->service_profile,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'billing_day' => $this->billing_day,
            'due_date' => $this->due_date?->format('Y-m-d'),
            'grace_until' => $this->grace_until?->format('Y-m-d'),
            'grace_note' => $this->grace_note,
            'has_active_grace' => $this->hasActiveGrace(),
            'first_bill_amount' => $this->first_bill_amount,
            'first_bill_amount_label' => $this->first_bill_amount !== null
                ? 'Rp '.number_format($this->first_bill_amount, 0, ',', '.')
                : null,
            'first_bill_days' => $this->first_bill_days,
            'overdue_action' => $this->overdue_action,
            'isolir_profile' => $this->isolir_profile,
            'status' => $this->status,
            'sync_status' => $this->sync_status,
            'sync_message' => $this->sync_message,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'is_overdue' => $this->isOverdue(),
            'router' => $this->router?->only(['id', 'name', 'host', 'port']),
            'package' => $this->package?->toOptionArray(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
