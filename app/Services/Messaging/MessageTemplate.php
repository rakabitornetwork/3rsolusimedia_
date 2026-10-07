<?php

namespace App\Services\Messaging;

use App\Models\PppoeCustomer;
use App\Support\AppSettings;

class MessageTemplate
{
    public const INVOICE = 'invoice';

    public const REMINDER = 'reminder';

    public const PAID = 'paid';

    public const ISOLIR = 'isolir';

    public const RESTORE = 'restore';

    public const WELCOME = 'welcome';

    public const VPN_INVOICE = 'vpn_invoice';

    public const VPN_REMINDER = 'vpn_reminder';

    public const VPN_PAID = 'vpn_paid';

    public const VPN_ISOLIR = 'vpn_isolir';

    public const VPN_RESTORE = 'vpn_restore';

    public const VPN_WELCOME = 'vpn_welcome';

    public const VPN_SIGNUP = 'vpn_signup';

    public const VPN_ACCOUNT = 'vpn_account';

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
                '📅 Jatuh tempo berikutnya: {{jatuh_tempo}}',
                '',
                '💬 Ketik *tagihan* jika ingin cek tagihan.',
                '',
                '— {{perusahaan}}',
            ]),
            self::ISOLIR => implode("\n", [
                '⛔ *Layanan diisolir*',
                '',
                'Halo {{nama}}, layanan internet Anda diisolir karena tagihan belum lunas.',
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
                'Halo {{nama}}, layanan internet Anda sudah aktif kembali. Terima kasih.',
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
                '📍 Alamat:',
                '{{alamat}}',
                '',
                '📦 *Layanan*',
                'Paket: {{paket}}',
                'Harga/bulan: {{harga_paket}}',
                'Mulai aktif: {{tanggal_mulai}}',
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
            self::VPN_INVOICE => implode("\n", [
                '🧾 *Tagihan baru*',
                '',
                'Halo {{nama}}, tagihan layanan VPN Anda sudah terbit.',
                '',
                '🧾 Invoice: {{nomor}}',
                '💰 Total: {{total}}',
                '📅 Jatuh tempo: {{jatuh_tempo}}',
                '📦 Paket: {{paket}}',
                '',
                ...self::vpnPortalLines('Bayar di portal pelanggan'),
                '',
                '💬 Atau ketik *tagihan* / *bayar* di chat ini.',
                '',
                '— {{perusahaan}}',
            ]),
            self::VPN_REMINDER => implode("\n", [
                '⏰ *Pengingat tagihan*',
                '',
                'Halo {{nama}}, tagihan VPN berikut belum lunas.',
                '',
                '🧾 Invoice: {{nomor}}',
                '💰 Total: {{total}}',
                '📅 Jatuh tempo: {{jatuh_tempo}}',
                '',
                ...self::vpnPortalLines('Bayar di portal pelanggan'),
                '',
                '💬 Atau ketik *bayar* di chat ini.',
                '',
                '— {{perusahaan}}',
            ]),
            self::VPN_PAID => implode("\n", [
                '✅ *Pembayaran diterima*',
                '',
                'Halo {{nama}}, terima kasih. Tagihan VPN *{{nomor}}* sebesar {{total}} sudah lunas.',
                '',
                '📦 Paket: {{paket}}',
                '📅 Jatuh tempo berikutnya: {{jatuh_tempo}}',
                '',
                'Skrip pemasangan di portal tidak berubah. Jika VPN masih putus, cek interface l2tp-vpn di Winbox.',
                '',
                '💬 Ketik *tagihan* jika ingin cek tagihan.',
                '',
                '— {{perusahaan}}',
            ]),
            self::VPN_ISOLIR => implode("\n", [
                '⛔ *VPN dinonaktifkan*',
                '',
                'Halo {{nama}}, layanan VPN Anda dinonaktifkan karena tagihan belum lunas. Login VPN ditolak sampai tagihan dibayar. Skrip di router tidak perlu dihapus.',
                '',
                ...self::vpnPortalLines('Bayar di portal pelanggan'),
                '',
                '💬 Atau ketik *bayar* di chat ini.',
                '',
                '— {{perusahaan}}',
            ]),
            self::VPN_RESTORE => implode("\n", [
                '✅ *VPN aktif kembali*',
                '',
                'Halo {{nama}}, layanan VPN Anda sudah diaktifkan kembali. Jika sambungan masih putus, buka Winbox → Interfaces, lalu pastikan l2tp-vpn tidak disabled.',
                '',
                '💬 Ketik *tagihan* jika ingin cek tagihan.',
                '',
                '— {{perusahaan}}',
            ]),
            self::VPN_WELCOME => implode("\n", [
                '🎉 *Selamat datang di {{perusahaan}}!*',
                '',
                'Pendaftaran layanan VPN Anda sudah berhasil. Simpan pesan ini sebagai acuan.',
                '',
                ...self::vpnPortalLines(),
                '',
                '👤 *Data pelanggan*',
                'Nama: {{nama}}',
                'HP: {{phone}}',
                '📍 Alamat:',
                '{{alamat}}',
                '',
                '📦 *Layanan*',
                'Jenis: VPN L2TP',
                'Paket: {{paket}}',
                'Harga/bulan: {{harga_paket}}',
                'Mulai aktif: {{tanggal_mulai}}',
                '',
                '🧾 *Tagihan*',
                'Tagihan pertama: {{tagihan_pertama}}',
                'Nomor invoice: {{nomor}}',
                'Jatuh tempo: {{jatuh_tempo}}',
                'Hari tagihan: setiap tanggal {{hari_tagihan}}',
                '',
                '🛠 *Pemasangan di router Anda*',
                '1. Buka portal, lalu salin skrip VPN.',
                '2. Di Winbox, login ke router MikroTik Anda.',
                '3. Klik New Terminal, tempel seluruh skrip, lalu tekan Enter.',
                '4. Jangan impor file .rsc. Skrip hanya dijalankan di terminal Winbox.',
                '',
                '💬 *Bot WhatsApp* (ketik di chat ini)',
                '• tagihan — cek tagihan belum lunas',
                '• bayar — tautan pembayaran',
                '• bantuan — daftar perintah',
                '',
                '📞 CS: {{telepon_kantor}}',
                '— {{perusahaan}}',
            ]),
            self::VPN_SIGNUP => implode("\n", [
                '🎉 *Pendaftaran VPN Tunnel berhasil*',
                '',
                'Halo {{nama}}, akun portal Anda sudah dibuat.',
                '',
                '👤 Nama: {{nama}}',
                '✉️ Email: {{email}}',
                '',
                '🌐 *Masuk portal pelanggan VPN*',
                '{{portal}}',
                'Login dengan email dan password yang Anda buat.',
                '',
                'Setelah masuk, buat akun VPN. Akun itu gratis 3 hari.',
                'Informasi tagihan dan akun dikirim ke WhatsApp ini.',
                '',
                '— {{perusahaan}}',
            ]),
            self::VPN_ACCOUNT => implode("\n", [
                '🔐 *Akun VPN sudah dibuat*',
                '',
                'Halo {{nama}}, akun VPN berikut aktif di CHR dan gratis sampai {{masa_gratis}}.',
                '',
                'Username: {{username_vpn}}',
                'Password: {{password}}',
                'Server: {{server}}',
                '',
                'Salin skrip pemasangan di portal, lalu jalankan di New Terminal Winbox.',
                '',
                'Setelah masa gratis habis, tagihan dikirim ke WhatsApp ini dan dibayar lewat payment gateway.',
                'Jika tidak dibayar, akun dihapus dari CHR.',
                '',
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
                implode("\n", [
                    '🧾 *Tagihan baru*',
                    '',
                    'Halo {{nama}}, tagihan layanan internet Anda sudah terbit.',
                    '',
                    '🧾 Invoice: {{nomor}}',
                    '💰 Total: {{total}}',
                    '📅 Jatuh tempo: {{jatuh_tempo}}',
                    '📦 Paket: {{paket}}',
                    '',
                    '🌐 *Bayar di portal pelanggan*',
                    '{{portal}}',
                    'Masuk dengan username PPPoE dan nomor HP:',
                    'Username: {{username}}',
                    'Nomor HP: {{phone}}',
                    '',
                    '💬 Atau ketik *tagihan* / *bayar* di chat ini.',
                    '',
                    '{{rekening}}',
                    '',
                    '— {{perusahaan}}',
                ]),
            ],
            self::REMINDER => [
                "Halo {{nama}},\n\nPengingat: tagihan {{nomor}} sebesar {{total}} jatuh tempo {{jatuh_tempo}} belum lunas.\nKetik bayar untuk tautan pembayaran.\n\n— {{perusahaan}}",
                $reminderWithoutBank,
                $reminderWithoutPortal,
                implode("\n", [
                    '⏰ *Pengingat tagihan*',
                    '',
                    'Halo {{nama}}, tagihan berikut belum lunas.',
                    '',
                    '🧾 Invoice: {{nomor}}',
                    '💰 Total: {{total}}',
                    '📅 Jatuh tempo: {{jatuh_tempo}}',
                    '',
                    '🌐 *Bayar di portal pelanggan*',
                    '{{portal}}',
                    'Masuk dengan username PPPoE dan nomor HP:',
                    'Username: {{username}}',
                    'Nomor HP: {{phone}}',
                    '',
                    '💬 Atau ketik *bayar* di chat ini.',
                    '',
                    '{{rekening}}',
                    '',
                    '— {{perusahaan}}',
                ]),
            ],
            self::PAID => [
                "Halo {{nama}},\n\nTerima kasih. Tagihan {{nomor}} sebesar {{total}} sudah lunas.\nJatuh tempo berikutnya: {{jatuh_tempo}}.\n\n— {{perusahaan}}",
                implode("\n", [
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
            ],
            self::ISOLIR => [
                "Halo {{nama}},\n\nLayanan {{username}} diisolir karena tagihan belum lunas.\nSegera lunasi agar koneksi aktif kembali. Ketik bayar.\n\n— {{perusahaan}}",
                $isolirWithoutBank,
                $isolirWithoutPortal,
                implode("\n", [
                    '⛔ *Layanan diisolir*',
                    '',
                    'Halo {{nama}}, layanan *{{username}}* diisolir karena tagihan belum lunas.',
                    '',
                    'Segera lunasi agar koneksi aktif kembali.',
                    '',
                    '🌐 *Bayar di portal pelanggan*',
                    '{{portal}}',
                    'Masuk dengan username PPPoE dan nomor HP:',
                    'Username: {{username}}',
                    'Nomor HP: {{phone}}',
                    '',
                    '💬 Atau ketik *bayar* di chat ini.',
                    '',
                    '{{rekening}}',
                    '',
                    '— {{perusahaan}}',
                ]),
            ],
            self::RESTORE => [
                "Halo {{nama}},\n\nLayanan {{username}} sudah aktif kembali. Terima kasih.\n\n— {{perusahaan}}",
                implode("\n", [
                    '✅ *Layanan aktif kembali*',
                    '',
                    'Halo {{nama}}, layanan *{{username}}* sudah aktif kembali. Terima kasih.',
                    '',
                    '💬 Ketik *tagihan* jika ingin cek tagihan.',
                    '',
                    '— {{perusahaan}}',
                ]),
            ],
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
                implode("\n", [
                    '🎉 *Selamat datang di {{perusahaan}}!*',
                    '',
                    'Pendaftaran layanan internet Anda sudah berhasil. Simpan pesan ini sebagai acuan.',
                    '',
                    '🌐 *Portal pelanggan*',
                    '{{portal}}',
                    'Masuk dengan username PPPoE dan nomor HP:',
                    'Username: {{username}}',
                    'Nomor HP: {{phone}}',
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
        $body = ($stored === '' || in_array($stored, $legacyList, true))
            ? $defaults[$template]
            : self::withCompanyPlaceholder($stored);

        return self::withoutPppoePortalLogin($body);
    }

    /**
     * Pelanggan VPN memakai salinan template sendiri. Kunci PPPoE tidak berubah.
     */
    public static function forCustomer(string $template, PppoeCustomer $customer): string
    {
        $base = str_starts_with($template, 'vpn_') ? substr($template, 4) : $template;

        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            return $base;
        }

        $vpn = 'vpn_'.$base;

        return array_key_exists($vpn, self::defaults()) ? $vpn : $base;
    }

    /**
     * Template tersimpan yang masih memakai login username PPPoE
     * dinaikkan ke cara masuk portal yang sama dengan selamat datang.
     */
    public static function withoutPppoePortalLogin(string $body): string
    {
        $login = implode("\n", array_slice(self::portalLoginLines(), 2));
        $patterns = [
            '/Masuk dengan username PPPoE dan nomor HP:\s*Username:\s*\{\{username\}\}\s*Nomor HP:\s*\{\{phone\}\}/u',
            '/Masuk pakai username PPPoE atau nomor HP\./u',
            '/Masuk dengan username PPPoE dan nomor HP:?/u',
            '/🔐 \*Akun PPPoE\* \(isi di modem\/router\)\s*Username:\s*\{\{username\}\}\s*Password:\s*\{\{password\}\}\s*/u',
            '/🔐 Akun:\s*\{\{username\}\}\s*/u',
            '/^Username:\s*\{\{username\}\}\s*$/mu',
            '/^Nomor HP:\s*\{\{phone\}\}\s*$/mu',
            '/^Password:\s*\{\{password\}\}\s*$/mu',
        ];

        foreach ($patterns as $pattern) {
            $replacement = str_contains($body, 'Kode OTP dikirim ke HP terdaftar') ? '' : $login;
            $body = preg_replace($pattern, $replacement, $body, 1) ?? $body;
            $body = preg_replace($pattern, '', $body) ?? $body;
        }

        $body = preg_replace("/\n{3,}/", "\n\n", $body) ?? $body;

        return trim($body);
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
            'Login dengan WhatsApp. Kode OTP dikirim ke HP terdaftar.',
            'Di portal: cek & bayar tagihan, lihat status ONU (RX & suhu), ubah WiFi, pantau perangkat terhubung.',
        ];
    }

    /**
     * @return list<string>
     */
    private static function vpnPortalLines(string $title = 'Portal pelanggan'): array
    {
        return [
            '🌐 *'.$title.'*',
            '{{portal}}',
            'Login dengan email dan password yang didaftarkan.',
            'Tagihan dibayar lewat payment gateway di portal.',
            'Di portal ada skrip pemasangan VPN. Salin skrip itu, lalu jalankan hanya di New Terminal Winbox.',
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
