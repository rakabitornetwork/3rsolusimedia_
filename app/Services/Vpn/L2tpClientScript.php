<?php

namespace App\Services\Vpn;

use App\Models\PppoeCustomer;

class L2tpClientScript
{
    public const INTERFACE_NAME = 'l2tp-vpn';

    /**
     * Skrip klien L2TP untuk ditempel di New Terminal Winbox.
     * Null bila server, username, atau password belum lengkap.
     */
    public function build(PppoeCustomer $customer): ?string
    {
        $customer->loadMissing('router');
        $server = trim((string) ($customer->router?->host ?? ''));
        $user = trim((string) $customer->username);
        $password = str_replace(["\r", "\n"], '', (string) $customer->password);

        if ($server === '' || $user === '' || $password === '') {
            return null;
        }

        $interface = self::quote(self::INTERFACE_NAME);
        $serverArg = $this->token($server);
        $userArg = self::quote($user);
        $passArg = self::quote($password);
        $comment = self::quote('VPN '.$user);

        return implode("\n", [
            '# Skrip klien VPN L2TP. Jalankan hanya di New Terminal Winbox.',
            '# Jangan diimpor lewat Files dan jangan ditempel di luar terminal.',
            ':do { /interface l2tp-client remove [find where name='.$interface.'] } on-error={}',
            '/interface l2tp-client add name='.$interface.' connect-to='.$serverArg.' user='.$userArg.' password='.$passArg.' profile=default-encryption add-default-route=no use-ipsec=no disabled=no comment='.$comment,
            ':do { /ip firewall nat remove [find where comment="nat-l2tp-vpn"] } on-error={}',
            '/ip firewall nat add chain=srcnat action=masquerade out-interface='.$interface.' comment="nat-l2tp-vpn"',
        ]);
    }

    public function unavailableReason(PppoeCustomer $customer): string
    {
        $customer->loadMissing('router');

        if (trim((string) ($customer->router?->host ?? '')) === '') {
            return 'Alamat server VPN belum diisi. Hubungi admin.';
        }

        if (trim((string) $customer->username) === '') {
            return 'Username VPN belum diisi. Hubungi admin.';
        }

        if (trim((string) $customer->password) === '') {
            return 'Password VPN belum diisi. Hubungi admin.';
        }

        return 'Skrip VPN belum bisa dibuat. Hubungi admin.';
    }

    /**
     * Kutip string untuk terminal RouterOS agar $, \, dan " tidak merusak perintah.
     */
    public static function quote(string $value): string
    {
        $escaped = str_replace(
            ['\\', '"', '$'],
            ['\\\\', '\\"', '\\$'],
            $value,
        );

        return '"'.$escaped.'"';
    }

    private function token(string $value): string
    {
        return preg_match('/^[A-Za-z0-9.:_-]+$/', $value) === 1
            ? $value
            : self::quote($value);
    }
}
