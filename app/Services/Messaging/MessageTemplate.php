<?php

namespace App\Services\Messaging;

use App\Support\AppSettings;

class MessageTemplate
{
    public const INVOICE = 'invoice';

    public const REMINDER = 'reminder';

    public const ISOLIR = 'isolir';

    public const RESTORE = 'restore';

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            self::INVOICE => "Halo {{nama}},\n\nTagihan {{nomor}} sebesar {{total}} jatuh tempo {{jatuh_tempo}}.\nPaket: {{paket}}\nAkun: {{username}}\n\nKetik tagihan atau bayar di chat ini.\n\n— {{perusahaan}}",
            self::REMINDER => "Halo {{nama}},\n\nPengingat: tagihan {{nomor}} sebesar {{total}} jatuh tempo {{jatuh_tempo}} belum lunas.\nKetik bayar untuk tautan pembayaran.\n\n— {{perusahaan}}",
            self::ISOLIR => "Halo {{nama}},\n\nLayanan {{username}} diisolir karena tagihan belum lunas.\nSegera lunasi agar koneksi aktif kembali. Ketik bayar.\n\n— {{perusahaan}}",
            self::RESTORE => "Halo {{nama}},\n\nLayanan {{username}} sudah aktif kembali. Terima kasih.\n\n— {{perusahaan}}",
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

        return $stored !== '' ? $stored : $defaults[$template];
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
