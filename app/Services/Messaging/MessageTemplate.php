<?php

namespace App\Services\Messaging;

use App\Support\AppSettings;

class MessageTemplate
{
    public const INVOICE = 'invoice';

    public const REMINDER = 'reminder';

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
                '🔐 Akun: {{username}}',
                '',
                '💬 Ketik *tagihan* atau *bayar* di chat ini.',
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
                '💬 Ketik *bayar* untuk tautan pembayaran.',
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
                '💬 Ketik *bayar* di chat ini.',
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
            ]),
        ];
    }

    /**
     * Teks default lama — dipakai agar template tersimpan yang belum diubah
     * tetap naik ke versi berikon.
     *
     * @return array<string, string>
     */
    public static function legacyDefaults(): array
    {
        return [
            self::INVOICE => "Halo {{nama}},\n\nTagihan {{nomor}} sebesar {{total}} jatuh tempo {{jatuh_tempo}}.\nPaket: {{paket}}\nAkun: {{username}}\n\nKetik tagihan atau bayar di chat ini.\n\n— {{perusahaan}}",
            self::REMINDER => "Halo {{nama}},\n\nPengingat: tagihan {{nomor}} sebesar {{total}} jatuh tempo {{jatuh_tempo}} belum lunas.\nKetik bayar untuk tautan pembayaran.\n\n— {{perusahaan}}",
            self::ISOLIR => "Halo {{nama}},\n\nLayanan {{username}} diisolir karena tagihan belum lunas.\nSegera lunasi agar koneksi aktif kembali. Ketik bayar.\n\n— {{perusahaan}}",
            self::RESTORE => "Halo {{nama}},\n\nLayanan {{username}} sudah aktif kembali. Terima kasih.\n\n— {{perusahaan}}",
            self::WELCOME => implode("\n", [
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

        return $stored;
    }

    /**
     * @param  array<string, scalar|null>  $vars
     */
    public static function render(string $template, array $vars): string
    {
        $body = self::get($template);

        foreach ($vars as $key => $value) {
            $body = str_replace('{{'.$key.'}}', (string) ($value ?? ''), $body);
        }

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
}
