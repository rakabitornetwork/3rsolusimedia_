<?php

namespace App\Support;

use App\Models\SiteSetting;

class AppSettings
{
    public const DEFAULT_LOGO_MARK = '/images/brand/logo-mark.png';

    public const DEFAULT_LOGO_FULL = '/images/brand/logo-full.png';

    public const DEFAULT_FAVICON = '/images/brand/favicon.png';

    public const DEFAULTS = [
        'app_panel_name' => 'RT RW Net Manager',
        'app_timezone' => 'Asia/Jakarta',
        'app_currency_label' => 'Rp',
        'app_invoice_prefix' => 'INV',
        'app_billing_generate_days' => '7',
        'app_billing_round_to' => '1000',
        'app_default_billing_day' => '1',
        'app_notif_whatsapp' => '0',
        'app_notif_email' => '0',
        'app_auto_isolir' => '1',
        'app_logo_mark' => self::DEFAULT_LOGO_MARK,
        'app_logo_full' => self::DEFAULT_LOGO_FULL,
        'app_favicon' => self::DEFAULT_FAVICON,
        'genieacs_enabled' => '0',
        'genieacs_nbi_url' => 'http://127.0.0.1:7557',
        'genieacs_ui_url' => 'http://127.0.0.1:3000',
        'genieacs_api_key' => '',
        'genieacs_username' => '',
        'genieacs_password' => '',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function genieAcsConfig(): array
    {
        return [
            'enabled' => self::bool('genieacs_enabled', false),
            'nbi_url' => (string) self::get('genieacs_nbi_url', self::DEFAULTS['genieacs_nbi_url']),
            'ui_url' => (string) self::get('genieacs_ui_url', self::DEFAULTS['genieacs_ui_url']),
            'api_key' => (string) self::get('genieacs_api_key', ''),
            'username' => (string) self::get('genieacs_username', ''),
            'has_password' => trim((string) self::get('genieacs_password', '')) !== '',
            'has_api_key' => trim((string) self::get('genieacs_api_key', '')) !== '',
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return SiteSetting::getValue(
            $key,
            $default ?? (self::DEFAULTS[$key] ?? null)
        );
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $cached = SiteSetting::allCached();
        $result = [];

        foreach (self::DEFAULTS as $key => $default) {
            $result[$key] = (string) ($cached[$key] ?? $default);
        }

        return $result;
    }

    public static function companyName(): string
    {
        $name = trim((string) SiteSetting::getValue('company_name', ''));

        return $name !== '' ? $name : 'Perusahaan';
    }

    public static function branding(): array
    {
        $companyName = self::companyName();

        return [
            'company_name' => $companyName,
            'panel_name' => $companyName,
            'logo_mark' => self::assetUrl('app_logo_mark', self::DEFAULT_LOGO_MARK),
            'logo_full' => self::assetUrl('app_logo_full', self::DEFAULT_LOGO_FULL),
            'favicon' => self::assetUrl('app_favicon', self::DEFAULT_FAVICON),
        ];
    }

    public static function assetUrl(string $key, string $fallback): string
    {
        $value = (string) self::get($key, $fallback);
        $path = $value !== '' ? $value : $fallback;

        // Cache-bust default brand files when updated on disk.
        if (str_starts_with($path, '/images/brand/')) {
            $full = public_path(ltrim($path, '/'));
            if (is_file($full)) {
                return $path.'?v='.filemtime($full);
            }
        }

        return $path;
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, (string) $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key, $default ? '1' : '0');

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function billingGenerateDays(): int
    {
        return max(1, min(31, self::int('app_billing_generate_days', 7)));
    }

    public static function billingRoundTo(): int
    {
        $value = self::int('app_billing_round_to', 1000);

        return $value > 0 ? $value : 1000;
    }
}
