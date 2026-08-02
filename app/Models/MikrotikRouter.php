<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class MikrotikRouter extends Model
{
    protected $fillable = [
        'name',
        'host',
        'port',
        'username',
        'password',
        'use_ssl',
        'is_active',
        'notes',
        'last_checked_at',
        'last_status',
        'last_message',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'use_ssl' => 'boolean',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : '',
            set: fn (?string $value) => $value !== null && $value !== ''
                ? Crypt::encryptString($value)
                : $value,
        );
    }

    public function toSafeArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'use_ssl' => $this->use_ssl,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_status' => $this->last_status,
            'last_message' => $this->last_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function pppoeCustomers(): HasMany
    {
        return $this->hasMany(PppoeCustomer::class);
    }
}
