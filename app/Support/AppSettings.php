<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Services\Messaging\MessageTemplate;
use Illuminate\Support\Facades\Cache;

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
        'messaging_notify_invoice' => '0',
        'messaging_notify_reminder' => '0',
        'messaging_notify_paid' => '0',
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
        'pppoe_webhook_secret' => '',
        'whatsapp_enabled' => '0',
        'whatsapp_base_url' => 'http://127.0.0.1:8080',
        'whatsapp_api_key' => '',
        'whatsapp_instance' => 'teslatech',
        'whatsapp_webhook_secret' => '',
        'whatsapp_test_number' => '',
        'whatsapp_send_delay_min' => '25',
        'whatsapp_send_delay_max' => '50',
        'whatsapp_send_batch' => '2',
        'whatsapp_send_daily_limit' => '80',
        'messaging_notify_isolir' => '0',
        'messaging_notify_welcome' => '1',
        'messaging_notify_pppoe_session' => '0',
        'messaging_pppoe_session_debounce' => '3',
        'msg_tpl_invoice' => '',
        'msg_tpl_reminder' => '',
        'msg_tpl_paid' => '',
        'msg_tpl_isolir' => '',
        'msg_tpl_restore' => '',
        'msg_tpl_welcome' => '',
        'bank_name' => '',
        'bank_account_name' => '',
        'bank_account_number' => '',
        'bank_note' => '',
        'bank_accounts' => '[]',
    ];

    public const MAX_BANK_ACCOUNTS = 10;

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

    public static function pppoeWebhookSecret(): string
    {
        return trim((string) self::get('pppoe_webhook_secret', ''));
    }

    /**
     * Chat ID admin/teknisi Telegram (bisa beberapa, dipisah koma/spasi/titik koma).
     *
     * @return list<string>
     */
    public static function telegramAdminChatIds(): array
    {
        $raw = trim((string) self::get('telegram_admin_chat_id', ''));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            trim(...),
            preg_split('/[\s,;]+/', $raw) ?: [],
        ), fn (string $id) => $id !== ''));
    }

    public static function isTelegramAdminChat(string $chatId): bool
    {
        return in_array($chatId, self::telegramAdminChatIds(), true);
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
            'notify_invoice' => self::notifyInvoice(),
            'notify_reminder' => self::notifyReminder(),
            'notify_paid' => self::notifyPaid(),
            'notify_isolir' => self::bool('messaging_notify_isolir', false),
            'notify_welcome' => self::bool('messaging_notify_welcome', true),
            'notify_pppoe_session' => self::bool('messaging_notify_pppoe_session', false),
            'pppoe_session_debounce' => max(0, min(30, self::int('messaging_pppoe_session_debounce', 3))),
            'whatsapp_send_delay_min' => self::whatsappDelayMin(),
            'whatsapp_send_delay_max' => self::whatsappDelayMax(),
            'whatsapp_send_batch' => self::whatsappSendBatch(),
            'whatsapp_send_daily_limit' => self::whatsappDailyLimit(),
            'templates' => MessageTemplate::all(),
        ];
    }

    /**
     * Jeda minimum antar pesan WhatsApp blast (detik).
     */
    public static function whatsappDelayMin(): int
    {
        return max(8, min(180, self::int('whatsapp_send_delay_min', 25)));
    }

    /**
     * Jeda maksimum antar pesan WhatsApp blast (detik).
     */
    public static function whatsappDelayMax(): int
    {
        $min = self::whatsappDelayMin();

        return max($min, min(300, self::int('whatsapp_send_delay_max', 50)));
    }

    /**
     * Maksimum pesan WhatsApp yang diproses per jalankan scheduler.
     */
    public static function whatsappSendBatch(): int
    {
        return max(1, min(10, self::int('whatsapp_send_batch', 2)));
    }

    /**
     * Batas kirim WhatsApp per hari (0 = tanpa batas).
     */
    public static function whatsappDailyLimit(): int
    {
        return max(0, min(500, self::int('whatsapp_send_daily_limit', 80)));
    }

    public static function notifyInvoice(): bool
    {
        return self::billingNotifyFlag('messaging_notify_invoice');
    }

    public static function notifyReminder(): bool
    {
        return self::billingNotifyFlag('messaging_notify_reminder');
    }

    public static function notifyPaid(): bool
    {
        return self::billingNotifyFlag('messaging_notify_paid');
    }

    /**
     * @return array<string, string>
     */
    public static function billingNotifyValues(bool $invoice, bool $reminder, bool $paid): array
    {
        return [
            'messaging_notify_invoice' => $invoice ? '1' : '0',
            'messaging_notify_reminder' => $reminder ? '1' : '0',
            'messaging_notify_paid' => $paid ? '1' : '0',
            'app_notif_whatsapp' => ($invoice || $reminder || $paid) ? '1' : '0',
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

        $legacy = in_array($result['app_notif_whatsapp'], ['1', 'true', 'yes', 'on'], true);
        foreach (['messaging_notify_invoice', 'messaging_notify_reminder', 'messaging_notify_paid'] as $key) {
            if (! array_key_exists($key, $cached)) {
                $result[$key] = $legacy ? '1' : '0';
            }
        }

        return $result;
    }

    public static function companyName(): string
    {
        $fresh = '';
        try {
            $fresh = trim((string) SiteSetting::query()->where('key', 'company_name')->value('value'));
        } catch (\Throwable) {
            $fresh = '';
        }

        $cached = trim((string) SiteSetting::getValue('company_name', ''));
        if ($fresh !== '' && $fresh !== $cached) {
            Cache::forget('site_settings');
        }

        $name = $fresh !== '' ? $fresh : $cached;

        return $name !== '' ? $name : 'Perusahaan';
    }

    /**
     * Nama brand lama di teks outbound diganti Nama Perusahaan dari Pengaturan Situs.
     */
    public static function replaceLegacyBrand(string $text): string
    {
        $company = self::companyName();
        $text = preg_replace('/3R\s+Solusi\s+Media/i', $company, $text) ?? $text;
        $text = preg_replace('/(?<![@\/])3rsolusimedia/i', $company, $text) ?? $text;

        return $text;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{bank_name: string, bank_account_name: string, bank_account_number: string, bank_note: string}
     */
    public static function normalizeBankAccount(array $row): array
    {
        return [
            'bank_name' => trim((string) ($row['bank_name'] ?? '')),
            'bank_account_name' => trim((string) ($row['bank_account_name'] ?? '')),
            'bank_account_number' => trim((string) ($row['bank_account_number'] ?? '')),
            'bank_note' => trim((string) ($row['bank_note'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function isFilledBankAccount(array $row): bool
    {
        $bank = self::normalizeBankAccount($row);

        return $bank['bank_name'] !== ''
            || $bank['bank_account_name'] !== ''
            || $bank['bank_account_number'] !== '';
    }

    /**
     * @param  list<mixed>|null  $rows
     * @return list<array{bank_name: string, bank_account_name: string, bank_account_number: string, bank_note: string}>
     */
    public static function normalizeBankAccounts(?array $rows): array
    {
        if ($rows === null) {
            return [];
        }

        $accounts = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $account = self::normalizeBankAccount($row);
            if (! self::isFilledBankAccount($account)) {
                continue;
            }

            $accounts[] = $account;
            if (count($accounts) >= self::MAX_BANK_ACCOUNTS) {
                break;
            }
        }

        return $accounts;
    }

    /**
     * @return list<array{bank_name: string, bank_account_name: string, bank_account_number: string, bank_note: string}>
     */
    public static function bankAccounts(): array
    {
        $decoded = json_decode((string) self::get('bank_accounts', '[]'), true);
        $fromJson = is_array($decoded) ? self::normalizeBankAccounts($decoded) : [];
        if ($fromJson !== []) {
            return $fromJson;
        }

        $legacy = self::normalizeBankAccount([
            'bank_name' => (string) self::get('bank_name', ''),
            'bank_account_name' => (string) self::get('bank_account_name', ''),
            'bank_account_number' => (string) self::get('bank_account_number', ''),
            'bank_note' => (string) self::get('bank_note', ''),
        ]);

        return self::isFilledBankAccount($legacy) ? [$legacy] : [];
    }

    /**
     * Rekening utama (pertama). Dipakai placeholder tunggal seperti {{nama_bank}}.
     *
     * @return array{bank_name: string, bank_account_name: string, bank_account_number: string, bank_note: string}
     */
    public static function bankAccount(): array
    {
        return self::bankAccounts()[0] ?? self::normalizeBankAccount([]);
    }

    public static function hasBankAccount(): bool
    {
        return self::bankAccounts() !== [];
    }

    /**
     * @param  list<array{bank_name: string, bank_account_name: string, bank_account_number: string, bank_note: string}>  $accounts
     * @return array<string, string>
     */
    public static function bankAccountSettingValues(array $accounts): array
    {
        $primary = $accounts[0] ?? self::normalizeBankAccount([]);

        return [
            'bank_accounts' => json_encode($accounts, JSON_UNESCAPED_UNICODE),
            'bank_name' => $primary['bank_name'],
            'bank_account_name' => $primary['bank_account_name'],
            'bank_account_number' => $primary['bank_account_number'],
            'bank_note' => $primary['bank_note'],
        ];
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

    /**
     * Toggle otomatis tagihan. Kunci baru belum tersimpan → ikut app_notif_whatsapp.
     */
    private static function billingNotifyFlag(string $key): bool
    {
        $cached = SiteSetting::allCached();
        if (array_key_exists($key, $cached)) {
            return in_array((string) $cached[$key], ['1', 'true', 'yes', 'on'], true);
        }

        return self::bool('app_notif_whatsapp', false);
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
