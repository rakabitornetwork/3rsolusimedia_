<?php

namespace App\Support;

class PhoneNumber
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '62') && strlen($digits) > 10) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    public static function matches(string $stored, string $input): bool
    {
        $storedDigits = self::normalize($stored);
        $inputDigits = self::normalize($input);

        if ($storedDigits === '' || $inputDigits === '' || strlen($inputDigits) < 8) {
            return false;
        }

        return $storedDigits === $inputDigits
            || str_ends_with($storedDigits, $inputDigits)
            || str_ends_with($inputDigits, $storedDigits);
    }

    /**
     * Format internasional tanpa plus, untuk Evolution API (62812…).
     */
    public static function toInternational(string $phone): string
    {
        $jid = strstr($phone, '@', true);
        $digits = self::normalize($jid !== false ? $jid : $phone);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }
}
