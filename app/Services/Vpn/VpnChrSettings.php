<?php

namespace App\Services\Vpn;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class VpnChrSettings
{
    public const HOST = 'vpn_chr_host';

    public const PORT = 'vpn_chr_port';

    public const USERNAME = 'vpn_chr_username';

    public const PASSWORD = 'vpn_chr_password';

    public static function configured(): bool
    {
        return self::host() !== ''
            && self::username() !== ''
            && self::password() !== '';
    }

    public static function host(): string
    {
        return trim((string) SiteSetting::getValue(self::HOST, ''));
    }

    public static function port(): int
    {
        $port = (int) SiteSetting::getValue(self::PORT, 22);

        return $port > 0 ? $port : 22;
    }

    public static function username(): string
    {
        return trim((string) SiteSetting::getValue(self::USERNAME, ''));
    }

    public static function password(): string
    {
        $stored = (string) SiteSetting::getValue(self::PASSWORD, '');
        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return '';
        }
    }

    public static function store(string $host, int $port, string $username, string $password): void
    {
        SiteSetting::setMany([
            self::HOST => trim($host),
            self::PORT => (string) $port,
            self::USERNAME => trim($username),
            self::PASSWORD => Crypt::encryptString($password),
        ]);
    }
}
