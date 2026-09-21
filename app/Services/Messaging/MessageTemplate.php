<?php

namespace App\Services\Messaging;

use App\Support\AppSettings;

class MessageTemplate
{
    public const INVOICE = 'invoice';

    public const REMINDER = 'reminder';

    public const PAID = 'paid';

    public const ISOLIR = 'isolir';

    public const RESTORE = 'restore';

    public const WELCOME = 'welcome';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            self::INVOICE => implode("\n", [
                '🧾 *Tagihan baru*',
                '',
                'Halo {{nama}}, tagihan layanan internet Anda sudah terbit.',
                '',
                '🧾 Invoice: {{nomor}}',
                '💰 Total: {{total}}',
                '📅 Jatuh tempo: {{jatuh_tempo}}',
                '📦 Paket: {{paket}}',
                '',
                ...self::portalLoginLines('Bayar di portal pelanggan'),
                '',
                '💬 Atau ketik *tagihan* / *bayar* di chat ini.',
                '',
                '{{rekening}}',
                '',
                '— {{perusahaan}}',
            ]),
            self::REMINDER => implode("\n", [
                '⏰ *Pengingat tagihan*',
                '',
                'Halo {{nama}}, tagihan berikut belum lunas.',
                '',
                '🧾 Invoice: {{nomor}}',
                '💰 Total: {{total}}',
                '📅 Jatuh tempo: {{jatuh_tempo}}',
                '',
                ...self::portalLoginLines('Bayar di portal pelanggan'),
                '',
                '💬 Atau ketik *bayar* di chat ini.',
                '',
                '{{rekening}}',
                '',
                '— {{perusahaan}}',
            ]),
            self::PAID => implode("\n", [
                '✅ *Pembayaran diterima*',
                '',
                'Halo {{nama}}, terima kasih. Tagihan *{{nomor}}* sebesar {{total}} sudah lunas.',
                '',
                '📦 Paket: {{paket}}',
                '🔐 Akun: {{username}}',
                '📅 Jatuh tempo berikutnya: {{jatuh_tempo}}',
                '',
                '💬 Ketik *tagihan* jika ingin cek tagihan.',
                '',
                '— {{perusahaan}}',
            ]),
            self::ISOLIR => implode("\n", [
                '⛔ *Layanan diisolir*',
                '',
                'Halo {{nama}}, layanan *{{username}}* diisolir karena tagihan belum lunas.',
                '',
                'Segera lunasi agar koneksi aktif kembali.',
                '',
                ...self::portalLoginLines('Bayar di portal pelanggan'),
                '',
                '💬 Atau ketik *bayar* di chat ini.',
                '',
                '{{rekening}}',
                '',
                '— {{perusahaan}}',
            ]),
            self::RESTORE => implode("\n", [
                '✅ *Layanan aktif kembali*',
                '',
                'Halo {{nama}}, layanan *{{username}}* sudah aktif kembali. Terima kasih.',
                '',
                '💬 Ketik *tagihan* jika ingin cek tagihan.',
                '',
                '— {{perusahaan}}',
            ]),
            self::WELCOME => implode("\n", [
                '🎉 *Selamat datang di {{perusahaan}}!*',
                '',
                'Pendaftaran layanan internet Anda sudah berhasil. Simpan pesan ini sebagai acuan.',
                '',
                ...self::portalLoginLines(),
                '',
                '👤 *Data pelanggan*',
                'Nama: {{nama}}',
                'HP: {{phone}}',
                'Alamat: {{alamat}}',
                '',
                '📦 *Layanan*',
                'Paket: {{paket}}',
                'Harga/bulan: {{harga_paket}}',
                'Mulai aktif: {{tanggal_mulai}}',
                '',
                '🔐 *Akun PPPoE* (isi di modem/router)',
                'Username: {{username}}',
                'Password: {{password}}',
                '',
                '🧾 *Tagihan*',
                'Tagihan pertama: {{tagihan_pertama}}',
                'Nomor invoice: {{nomor}}',
                'Jatuh tempo: {{jatuh_tempo}}',
                'Hari tagihan: setiap tanggal {{hari_tagihan}}',
                '',
                '💬 *Bot WhatsApp* (ketik di chat ini)',
                '• tagihan — cek tagihan belum lunas',
                '• bayar — tautan pembayaran',
                '• bantuan — daftar perintah',
                '',
                '{{rekening}}',
                '',
                '📞 CS: {{telepon_kantor}}',
                '— {{perusahaan}}',
            ]),
        ];
    }

    /**
     * Teks default lama — dipakai agar template tersimpan yang belum diubah
     * tetap naik ke versi berikon.
     *
     * @return array<string, string|list<string>>
     */
    public static function legacyDefaults(): array
    {
        $invoiceWithoutPortal = implode("\n", [
            '🧾 *Tagihan baru*',
            '',
            'Halo {{nama}}, tagihan layanan internet Anda sudah terbit.',
            '',
            '🧾 Invoice: {{nomor}}',
            '💰 Total: {{total}}',
            '📅 Jatuh tempo: {{jatuh_tempo}}',
            '📦 Paket: {{paket}}',
            '🔐 Akun: {{username}}',
            '',
            '💬 Ketik *tagihan* atau *bayar* di chat ini.',
            '',
            '{{rekening}}',
            '',
            '— {{perusahaan}}',
        ]);
        $invoiceWithoutBank = implode("\n", [
            '🧾 *Tagihan baru*',
            '',
            'Halo {{nama}}, tagihan layanan internet Anda sudah terbit.',
            '',
            '🧾 Invoice: {{nomor}}',
            '💰 Total: {{total}}',
            '📅 Jatuh tempo: {{jatuh_tempo}}',
            '📦 Paket: {{paket}}',
            '🔐 Akun: {{username}}',
            '',
            '💬 Ketik *tagihan* atau *bayar* di chat ini.',
            '',
            '— {{perusahaan}}',
        ]);
        $reminderWithoutPortal = implode("\n", [
            '⏰ *Pengingat tagihan*',
            '',
            'Halo {{nama}}, tagihan berikut belum lunas.',
            '',
            '🧾 Invoice: {{nomor}}',
            '💰 Total: {{total}}',
            '📅 Jatuh tempo: {{jatuh_tempo}}',
            '',
            '💬 Ketik *bayar* untuk tautan pembayaran.',
            '',
            '{{rekening}}',
            '',
            '— {{perusahaan}}',
        ]);
        $reminderWithoutBank = implode("\n", [
            '⏰ *Pengingat tagihan*',
            '',
            'Halo {{nama}}, tagihan berikut belum lunas.',
            '',
            '🧾 Invoice: {{nomor}}',
            '💰 Total: {{total}}',
            '📅 Jatuh tempo: {{jatuh_tempo}}',
            '',
            '💬 Ketik *bayar* untuk tautan pembayaran.',
            '',
            '— {{perusahaan}}',
        ]);
        $isolirWithoutPortal = implode("\n", [
            '⛔ *Layanan diisolir*',
            '',
            'Halo {{nama}}, layanan *{{username}}* diisolir karena tagihan belum lunas.',
            '',
            'Segera lunasi agar koneksi aktif kembali.',
            '',
            '💬 Ketik *bayar* di chat ini.',
            '',
            '{{rekening}}',
            '',
            '— {{perusahaan}}',
        ]);
        $isolirWithoutBank = implode("\n", [
            '⛔ *Layanan diisolir*',
            '',
            'Halo {{nama}}, layanan *{{username}}* diisolir karena tagihan belum lunas.',
            '',
            'Segera lunasi agar koneksi aktif kembali.',
            '',
            '💬 Ketik *bayar* di chat ini.',
            '',
            '— {{perusahaan}}',
        ]);
        $welcomeWithoutBank = implode("\n", [
            '🎉 *Selamat datang di {{perusahaan}}!*',
            '',
            'Pendaftaran layanan internet Anda sudah berhasil. Simpan pesan ini sebagai acuan.',
            '',
            '🌐 *Portal pelanggan*',
            '{{portal}}',
            'Masuk pakai username PPPoE atau nomor HP.',
            '',
            '👤 *Data pelanggan*',
            'Nama: {{nama}}',
            'HP: {{phone}}',
            'Alamat: {{alamat}}',
            '',
            '📦 *Layanan*',
            'Paket: {{paket}}',
            'Harga/bulan: {{harga_paket}}',
            'Mulai aktif: {{tanggal_mulai}}',
            '',
            '🔐 *Akun PPPoE* (isi di modem/router)',
            'Username: {{username}}',
            'Password: {{password}}',
            '',
            '🧾 *Tagihan*',
            'Tagihan pertama: {{tagihan_pertama}}',
            'Nomor invoice: {{nomor}}',
            'Jatuh tempo: {{jatuh_tempo}}',
            'Hari tagihan: setiap tanggal {{hari_tagihan}}',
            '',
            '💬 *Bot WhatsApp* (ketik di chat ini)',
            '• tagihan — cek tagihan belum lunas',
            '• bayar — tautan pembayaran',
            '• bantuan — daftar perintah',
            '',
            '📞 CS: {{telepon_kantor}}',
            '— {{perusahaan}}',
        ]);
        $welcomeWithOrLogin = implode("\n", [
            '🎉 *Selamat datang di {{perusahaan}}!*',
            '',
            'Pendaftaran layanan internet Anda sudah berhasil. Simpan pesan ini sebagai acuan.',
            '',
            '🌐 *Portal pelanggan*',
            '{{portal}}',
            'Masuk pakai username PPPoE atau nomor HP.',
            '',
            '👤 *Data pelanggan*',
            'Nama: {{nama}}',
            'HP: {{phone}}',
            'Alamat: {{alamat}}',
            '',
            '📦 *Layanan*',
            'Paket: {{paket}}',
            'Harga/bulan: {{harga_paket}}',
            'Mulai aktif: {{tanggal_mulai}}',
            '',
            '🔐 *Akun PPPoE* (isi di modem/router)',
            'Username: {{username}}',
            'Password: {{password}}',
            '',
            '🧾 *Tagihan*',
            'Tagihan pertama: {{tagihan_pertama}}',
            'Nomor invoice: {{nomor}}',
            'Jatuh tempo: {{jatuh_tempo}}',
            'Hari tagihan: setiap tanggal {{hari_tagihan}}',
            '',
            '💬 *Bot WhatsApp* (ketik di chat ini)',
            '• tagihan — cek tagihan belum lunas',
            '• bayar — tautan pembayaran',
            '• bantuan — daftar perintah',
            '',
            '{{rekening}}',
            '',
            '📞 CS: {{telepon_kantor}}',
            '— {{perusahaan}}',
        ]);

        return [
            self::INVOICE => [
                "Halo {{nama}},\n\nTagihan {{nomor}} sebesar {{total}} jatuh tempo {{jatuh_tempo}}.\nPaket: {{paket}}\nAkun: {{username}}\n\nKetik tagihan atau bayar di chat ini.\n\n— {{perusahaan}}",
                $invoiceWithoutBank,
                $invoiceWithoutPortal,
            ],
            self::REMINDER => [
                "Halo {{nama}},\n\nPengingat: tagihan {{nomor}} sebesar {{total}} jatuh tempo {{jatuh_tempo}} belum lunas.\nKetik bayar untuk tautan pembayaran.\n\n— {{perusahaan}}",
                $reminderWithoutBank,
                $reminderWithoutPortal,
            ],
            self::PAID => "Halo {{nama}},\n\nTerima kasih. Tagihan {{nomor}} sebesar {{total}} sudah lunas.\nJatuh tempo berikutnya: {{jatuh_tempo}}.\n\n— {{perusahaan}}",
            self::ISOLIR => [
                "Halo {{nama}},\n\nLayanan {{username}} diisolir karena tagihan belum lunas.\nSegera lunasi agar koneksi aktif kembali. Ketik bayar.\n\n— {{perusahaan}}",
                $isolirWithoutBank,
                $isolirWithoutPortal,
            ],
            self::RESTORE => "Halo {{nama}},\n\nLayanan {{username}} sudah aktif kembali. Terima kasih.\n\n— {{perusahaan}}",
            self::WELCOME => [
                implode("\n", [
                    '🎉 *Selamat datang di {{perusahaan}}!*',
                    '',
                    'Pendaftaran layanan internet Anda sudah berhasil. Simpan pesan ini sebagai acuan.',
                    '',
                    '👤 *Data pelanggan*',
                    'Nama: {{nama}}',
                    'HP: {{phone}}',
                    'Alamat: {{alamat}}',
                    '',
                    '📦 *Layanan*',
                    'Paket: {{paket}}',
                    'Harga/bulan: {{harga_paket}}',
                    'Mulai aktif: {{tanggal_mulai}}',
                    '',
                    '🔐 *Akun PPPoE* (isi di modem/router)',
                    'Username: {{username}}',
                    'Password: {{password}}',
                    '',
                    '🧾 *Tagihan*',
                    'Tagihan pertama: {{tagihan_pertama}}',
                    'Nomor invoice: {{nomor}}',
                    'Jatuh tempo: {{jatuh_tempo}}',
                    'Hari tagihan: setiap tanggal {{hari_tagihan}}',
                    '',
                    '🌐 *Portal pelanggan*',
                    '{{portal}}',
                    'Masuk pakai username PPPoE atau nomor HP.',
                    '',
                    '💬 *Bot WhatsApp* (ketik di chat ini)',
                    '• tagihan — cek tagihan belum lunas',
                    '• bayar — tautan pembayaran',
                    '• bantuan — daftar perintah',
                    '',
                    '📞 CS: {{telepon_kantor}}',
                    '— {{perusahaan}}',
                ]),
                $welcomeWithoutBank,
                $welcomeWithOrLogin,
            ],
        ];
    }

    public static function settingKey(string $template): string
    {
        return 'msg_tpl_'.$template;
    }

    public static function get(string $template): string
    {
        $defaults = self::defaults();
        if (! isset($defaults[$template])) {
            return '';
        }

        $stored = trim((string) AppSettings::get(self::settingKey($template), ''));
        $legacy = self::legacyDefaults()[$template] ?? null;
        $legacyList = is_array($legacy) ? $legacy : ($legacy !== null ? [$legacy] : []);
        if ($stored === '' || in_array($stored, $legacyList, true)) {
            return $defaults[$template];
        }

        return self::withCompanyPlaceholder($stored);
    }

    /**
     * Nama brand lama di template tersimpan diganti placeholder,
     * supaya mengikuti Nama Perusahaan di Pengaturan Situs.
     */
    public static function withCompanyPlaceholder(string $body): string
    {
        $body = preg_replace('/3R\s+Solusi\s+Media/i', '{{perusahaan}}', $body) ?? $body;
        $body = preg_replace('/(?<![@\/])3rsolusimedia/i', '{{perusahaan}}', $body) ?? $body;

        return $body;
    }

    /**
     * Variabel perusahaan/rekening yang selalu tersedia di semua template.
     *
     * @return array<string, string>
     */
    public static function sharedVars(): array
    {
        $accounts = AppSettings::bankAccounts();
        $bank = $accounts[0] ?? AppSettings::bankAccount();
        $blocks = [];
        foreach ($accounts as $account) {
            $block = self::formatBankBlock($account);
            if ($block !== '') {
                $blocks[] = $block;
            }
        }

        $rekening = $blocks === []
            ? ''
            : "Transfer ke:\n".implode("\n\n", $blocks);

        return [
            'perusahaan' => AppSettings::companyName(),
            'nama_bank' => $bank['bank_name'] !== '' ? $bank['bank_name'] : '—',
            'atas_nama' => $bank['bank_account_name'] !== '' ? $bank['bank_account_name'] : '—',
            'nomor_rekening' => $bank['bank_account_number'] !== '' ? $bank['bank_account_number'] : '—',
            'catatan_bank' => $bank['bank_note'],
            'rekening' => $rekening,
            'portal' => url('/portal'),
        ];
    }

    /**
     * @return list<string>
     */
    private static function portalLoginLines(string $title = 'Portal pelanggan'): array
    {
        return [
            '🌐 *'.$title.'*',
            '{{portal}}',
            'Masuk dengan username PPPoE dan nomor HP:',
            'Username: {{username}}',
            'Nomor HP: {{phone}}',
        ];
    }

    /**
     * @param  array{bank_name: string, bank_account_name: string, bank_account_number: string, bank_note: string}  $bank
     */
    private static function formatBankBlock(array $bank): string
    {
        return implode("\n", array_values(array_filter([
            $bank['bank_name'] !== '' ? $bank['bank_name'] : null,
            $bank['bank_account_name'] !== '' ? 'a.n. '.$bank['bank_account_name'] : null,
            $bank['bank_account_number'] !== '' ? $bank['bank_account_number'] : null,
            $bank['bank_note'] !== '' ? $bank['bank_note'] : null,
        ])));
    }

    /**
     * @param  array<string, scalar|null>  $vars
     */
    public static function render(string $template, array $vars): string
    {
        $body = self::get($template);
        $vars = [...self::sharedVars(), ...$vars];

        foreach ($vars as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) ($value ?? ''), $body);
        }

        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        return trim($body);
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $all = [];
        foreach (array_keys(self::defaults()) as $key) {
            $all[$key] = self::get($key);
        }

        return $all;
    }

    /**
     * Template yang boleh dikirim manual dari tagihan (bukan welcome).
     *
     * @return array<string, string>
     */
    public static function manualChoices(): array
    {
        return [
            self::INVOICE => 'Tagihan baru',
            self::REMINDER => 'Pengingat jatuh tempo',
            self::PAID => 'Pembayaran diterima',
            self::ISOLIR => 'Isolir',
            self::RESTORE => 'Layanan aktif kembali',
        ];
    }

    public static function isManual(string $template): bool
    {
        return array_key_exists($template, self::manualChoices());
    }
}
