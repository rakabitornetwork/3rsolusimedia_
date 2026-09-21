<?php

namespace App\Services\Messaging;

use App\Models\MessagingIdentity;
use App\Models\PppoeCustomer;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;

class WhatsAppIdentityBinder
{
    /**
     * Ikatkan nomor HP pelanggan ke bot WhatsApp tanpa perintah daftar.
     * Hanya nomor yang unik di data pelanggan.
     */
    public function bindCustomer(PppoeCustomer $customer, ?string $displayName = null): ?MessagingIdentity
    {
        $intl = PhoneNumber::toInternational((string) $customer->phone);
        if ($intl === '' || strlen($intl) < 10) {
            return null;
        }

        $unique = $this->uniqueCustomerForNumber($intl);
        if (! $unique || (int) $unique->id !== (int) $customer->id) {
            return null;
        }

        $existingByNumber = $this->identityByNumber($intl);
        if ($existingByNumber && (int) $existingByNumber->pppoe_customer_id !== (int) $customer->id) {
            return null;
        }

        $existingByCustomer = MessagingIdentity::query()
            ->where('channel', 'whatsapp')
            ->where('pppoe_customer_id', $customer->id)
            ->first();

        if ($existingByCustomer) {
            if (! PhoneNumber::matches((string) $existingByCustomer->external_id, $intl)) {
                return $existingByCustomer;
            }

            $updates = [];
            if ($existingByCustomer->external_id !== $intl) {
                $updates['external_id'] = $intl;
            }
            if ($displayName && $existingByCustomer->display_name !== $displayName) {
                $updates['display_name'] = $displayName;
            }
            if ($existingByCustomer->verified_at === null) {
                $updates['verified_at'] = now();
            }
            if ($updates !== []) {
                $existingByCustomer->forceFill($updates)->save();
            }

            return $existingByCustomer;
        }

        try {
            return MessagingIdentity::query()->create([
                'channel' => 'whatsapp',
                'external_id' => $intl,
                'pppoe_customer_id' => $customer->id,
                'display_name' => $displayName,
                'verified_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->identityByNumber($intl)
                ?? MessagingIdentity::query()
                    ->where('channel', 'whatsapp')
                    ->where('pppoe_customer_id', $customer->id)
                    ->first();
        }
    }

    public function bindFromIncomingNumber(string $incoming, ?string $displayName = null): ?MessagingIdentity
    {
        $customer = $this->uniqueCustomerForNumber($incoming);
        if (! $customer) {
            return null;
        }

        $intl = PhoneNumber::toInternational($incoming);
        $existing = MessagingIdentity::query()
            ->where('channel', 'whatsapp')
            ->where('pppoe_customer_id', $customer->id)
            ->first();

        if ($existing && $intl !== '' && ! PhoneNumber::matches((string) $existing->external_id, $intl)) {
            return null;
        }

        return $this->bindCustomer($customer, $displayName);
    }

    /**
     * @return array{bound: int, existing: int, skipped: int, conflicts: int}
     */
    public function syncAll(): array
    {
        $bound = 0;
        $existing = 0;
        $skipped = 0;
        $conflicts = 0;

        PppoeCustomer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($customers) use (&$bound, &$existing, &$skipped, &$conflicts) {
                foreach ($customers as $customer) {
                    $hadIdentity = MessagingIdentity::query()
                        ->where('channel', 'whatsapp')
                        ->where('pppoe_customer_id', $customer->id)
                        ->exists();

                    $identity = $this->bindCustomer($customer);
                    if ($identity) {
                        if ($hadIdentity) {
                            $existing++;
                        } else {
                            $bound++;
                        }

                        continue;
                    }

                    $intl = PhoneNumber::toInternational((string) $customer->phone);
                    if ($intl === '' || strlen($intl) < 10) {
                        $skipped++;

                        continue;
                    }

                    if ($this->uniqueCustomerForNumber($intl) === null) {
                        $conflicts++;

                        continue;
                    }

                    $skipped++;
                }
            });

        return compact('bound', 'existing', 'skipped', 'conflicts');
    }

    public function uniqueCustomerForNumber(string $number): ?PppoeCustomer
    {
        $intl = PhoneNumber::toInternational($number);
        if ($intl === '' || strlen($intl) < 10) {
            return null;
        }

        $tail = substr(PhoneNumber::normalize($intl), -8);
        if (strlen($tail) < 8) {
            return null;
        }

        $matches = PppoeCustomer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('phone', 'like', '%'.$tail)
            ->get()
            ->filter(fn (PppoeCustomer $row) => PhoneNumber::matches((string) $row->phone, $intl))
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function identityByNumber(string $number): ?MessagingIdentity
    {
        $intl = PhoneNumber::toInternational($number);
        $local = PhoneNumber::normalize($number);
        $ids = array_values(array_unique(array_filter([$intl, $local, $number])));

        if ($ids === []) {
            return null;
        }

        return MessagingIdentity::query()
            ->where('channel', 'whatsapp')
            ->whereIn('external_id', $ids)
            ->first();
    }
}
