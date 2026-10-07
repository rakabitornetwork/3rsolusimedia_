<?php

namespace App\Services\Vpn;

use App\Models\MikrotikRouter;
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

    /**
     * RouterOS yang dipakai pelanggan VPN Tunnel.
     * Catatan router yang berisi "VPN TUNNEL" menang; kalau belum ada, nama yang berisi "CHR".
     */
    public static function mikrotikRouterId(): ?int
    {
        $byNotes = MikrotikRouter::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(COALESCE(notes, "")) LIKE ?', ['%vpn tunnel%'])
            ->orderBy('id')
            ->value('id');

        if ($byNotes) {
            return (int) $byNotes;
        }

        $byName = MikrotikRouter::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) LIKE ?', ['%chr%'])
            ->orderBy('id')
            ->value('id');

        return $byName ? (int) $byName : null;
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
