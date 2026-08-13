<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\PppoeCustomer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait ResolvesPortalCustomer
{
    protected function makePortalToken(int $customerId): string
    {
        $token = Str::lower(Str::random(48));
        Cache::put($this->portalTokenCacheKey($token), $customerId, now()->addHours(2));

        return $token;
    }

    protected function customerFromPortalToken(string $token): ?PppoeCustomer
    {
        if (! preg_match('/^[a-z0-9]{32,64}$/', $token)) {
            return null;
        }

        $customerId = Cache::get($this->portalTokenCacheKey($token));
        if (! $customerId) {
            return null;
        }

        Cache::put($this->portalTokenCacheKey($token), $customerId, now()->addHours(2));

        return PppoeCustomer::query()->find((int) $customerId);
    }

    protected function portalTokenCacheKey(string $token): string
    {
        return 'portal_pay:'.$token;
    }

    protected function normalizePortalPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '62') && strlen($digits) > 10) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    /**
     * @return array{name: string, username: string, phone: ?string, due_date: ?string, status: ?string}
     */
    protected function portalCustomerPayload(PppoeCustomer $customer): array
    {
        return [
            'name' => $customer->name,
            'username' => $customer->username,
            'phone' => $customer->phone,
            'due_date' => $customer->due_date?->format('Y-m-d'),
            'status' => $customer->status,
        ];
    }
}
