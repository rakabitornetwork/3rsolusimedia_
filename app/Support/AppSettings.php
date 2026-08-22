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
        'pg_default' => 'xendit',
        'xendit_enabled' => '0',
        'xendit_secret_key' => '',
        'xendit_callback_token' => '',
        'xendit_mode' => 'sandbox',
        'midtrans_enabled' => '0',
        'midtrans_server_key' => '',
        'midtrans_client_key' => '',
        'midtrans_mode' => 'sandbox',
        'duitku_enabled' => '0',
        'duitku_merchant_code' => '',
        'duitku_api_key' => '',
        'duitku_mode' => 'sandbox',
        'telegram_enabled' => '0',
        'telegram_bot_token' => '',
        'telegram_bot_username' => '',
        'telegram_webhook_secret' => '',
        'telegram_admin_chat_id' => '',
        'whatsapp_enabled' => '0',
        'whatsapp_base_url' => 'http://127.0.0.1:8080',
        'whatsapp_api_key' => '',
        'whatsapp_instance' => 'teslatech',
        'whatsapp_webhook_secret' => '',
        'whatsapp_test_number' => '',
        'messaging_notify_isolir' => '0',
        'msg_tpl_invoice' => '',
        'msg_tpl_reminder' => '',
        'msg_tpl_isolir' => '',
        'msg_tpl_restore' => '',
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

    /**
     * @return array<string, mixed>
     */
    public static function paymentGatewayConfig(): array
    {
        $default = (string) self::get('pg_default', 'xendit');
        if (! in_array($default, ['xendit', 'midtrans', 'duitku'], true)) {
            $default = 'xendit';
        }

        return [
            'default' => $default,
            'xendit' => [
                'enabled' => self::bool('xendit_enabled', false),
                'mode' => (string) self::get('xendit_mode', 'sandbox'),
                'has_secret_key' => trim((string) self::get('xendit_secret_key', '')) !== '',
                'has_callback_token' => trim((string) self::get('xendit_callback_token', '')) !== '',
            ],
            'midtrans' => [
                'enabled' => self::bool('midtrans_enabled', false),
                'mode' => (string) self::get('midtrans_mode', 'sandbox'),
                'client_key' => (string) self::get('midtrans_client_key', ''),
                'has_server_key' => trim((string) self::get('midtrans_server_key', '')) !== '',
                'has_client_key' => trim((string) self::get('midtrans_client_key', '')) !== '',
            ],
            'duitku' => [
                'enabled' => self::bool('duitku_enabled', false),
                'mode' => (string) self::get('duitku_mode', 'sandbox'),
                'merchant_code' => (string) self::get('duitku_merchant_code', ''),
                'has_api_key' => trim((string) self::get('duitku_api_key', '')) !== '',
                'has_merchant_code' => trim((string) self::get('duitku_merchant_code', '')) !== '',
            ],
        ];
    }

    public static function xenditSecretKey(): string
    {
        return trim((string) self::get('xendit_secret_key', ''));
    }

    public static function xenditCallbackToken(): string
    {
        return trim((string) self::get('xendit_callback_token', ''));
    }

    public static function midtransServerKey(): string
    {
        return trim((string) self::get('midtrans_server_key', ''));
    }

    public static function duitkuApiKey(): string
    {
        return trim((string) self::get('duitku_api_key', ''));
    }

    public static function duitkuMerchantCode(): string
    {
        return trim((string) self::get('duitku_merchant_code', ''));
    }

    public static function telegramBotToken(): string
    {
        return trim((string) self::get('telegram_bot_token', ''));
    }

    public static function telegramWebhookSecret(): string
    {
        return trim((string) self::get('telegram_webhook_secret', ''));
    }

    public static function whatsappBaseUrl(): string
    {
        return rtrim(trim((string) self::get('whatsapp_base_url', self::DEFAULTS['whatsapp_base_url'])), '/');
    }

    public static function whatsappApiKey(): string
    {
        return trim((string) self::get('whatsapp_api_key', ''));
    }

    public static function whatsappInstance(): string
    {
        $name = trim((string) self::get('whatsapp_instance', 'teslatech'));

        return $name !== '' ? $name : 'teslatech';
    }

    public static function whatsappWebhookSecret(): string
    {
        return trim((string) self::get('whatsapp_webhook_secret', ''));
    }

    /**
     * @return array<string, mixed>
     */
    public static function messagingConfig(): array
    {
        $username = ltrim((string) self::get('telegram_bot_username', ''), '@');

        return [
            'telegram' => [
                'enabled' => self::bool('telegram_enabled', false),
                'username' => $username,
                'bot_link' => $username !== '' ? 'https://t.me/'.$username : null,
                'admin_chat_id' => (string) self::get('telegram_admin_chat_id', ''),
                'has_bot_token' => self::telegramBotToken() !== '',
                'has_webhook_secret' => self::telegramWebhookSecret() !== '',
            ],
            'whatsapp' => [
                'enabled' => self::bool('whatsapp_enabled', false),
                'base_url' => self::whatsappBaseUrl(),
                'instance' => self::whatsappInstance(),
                'test_number' => (string) self::get('whatsapp_test_number', ''),
                'has_api_key' => self::whatsappApiKey() !== '',
                'has_webhook_secret' => self::whatsappWebhookSecret() !== '',
            ],
            'notify_invoice' => self::bool('app_notif_whatsapp', false),
            'notify_isolir' => self::bool('messaging_notify_isolir', false),
            'templates' => \App\Services\Messaging\MessageTemplate::all(),
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
