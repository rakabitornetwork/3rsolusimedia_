<?php

namespace App\Services\Vpn;

use App\Models\PppoeCustomer;
use App\Models\VpnRouter;

class L2tpClientScript
{
    public const INTERFACE_NAME = 'l2tp-vpn';

    /**
     * Skrip klien L2TP untuk ditempel di New Terminal Winbox.
     * Null bila server, username, atau password belum lengkap.
     */
    public function build(PppoeCustomer $customer): ?string
    {
        $customer->loadMissing(['router', 'vpnPortForwards']);
        $server = VpnChrSettings::host() !== ''
            ? VpnChrSettings::host()
            : trim((string) ($customer->router?->host ?? ''));
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

        $lines = [
            '# Skrip klien VPN L2TP. Jalankan hanya di New Terminal Winbox.',
            '# Jangan diimpor lewat Files dan jangan ditempel di luar terminal.',
            ':do { /interface l2tp-client remove [find where name='.$interface.'] } on-error={}',
            '/interface l2tp-client add name='.$interface.' connect-to='.$serverArg.' user='.$userArg.' password='.$passArg.' profile=default-encryption add-default-route=no use-ipsec=no disabled=no comment='.$comment,
            ':do { /ip firewall nat remove [find where comment="nat-l2tp-vpn"] } on-error={}',
            '/ip firewall nat add chain=srcnat action=masquerade out-interface='.$interface.' comment="nat-l2tp-vpn"',
        ];

        if ($customer->vpnPortForwards->isNotEmpty()) {
            $lines[] = '# Port publik yang diteruskan ke router Anda:';
            foreach ($customer->vpnPortForwards as $forward) {
                $lines[] = '# '.$server.':'.$forward->public_port.' → port '.$forward->dst_port.' ('.$forward->label.')';
            }
            $lines[] = '# Port 22 di router ini boleh diteruskan ke perangkat mana pun.';
            $lines[] = '# Contoh, ubah IP dan port tujuan lalu jalankan di terminal yang sama:';
            $lines[] = '# /ip firewall nat add chain=dstnat in-interface='.$interface.' protocol=tcp dst-port=22 action=dst-nat to-addresses=192.168.88.2 to-ports=80 comment="remote-perangkat"';
        }

        return implode("\n", $lines);
    }

    public function buildForRouter(VpnRouter $router): ?string
    {
        $router->loadMissing(['customer.router', 'portForwards']);
        $customer = $router->customer;
        if (! $customer) {
            return null;
        }

        $server = VpnChrSettings::host() !== ''
            ? VpnChrSettings::host()
            : trim((string) ($customer->router?->host ?? ''));
        $user = trim($router->name);
        $password = str_replace(["\r", "\n"], '', (string) $customer->password);

        if ($server === '' || $user === '' || $password === '') {
            return null;
        }

        $interface = self::quote(self::INTERFACE_NAME);
        $serverArg = $this->token($server);
        $userArg = self::quote($user);
        $passArg = self::quote($password);
        $comment = self::quote('VPN '.$user);

        $lines = [
            '# Skrip klien untuk router '.$user.'. Jalankan hanya di New Terminal Winbox router ini.',
            '# Secret ini baru bisa tersambung setelah tagihannya lunas.',
            ':do { /interface l2tp-client remove [find where name='.$interface.'] } on-error={}',
            '/interface l2tp-client add name='.$interface.' connect-to='.$serverArg.' user='.$userArg.' password='.$passArg.' profile=default-encryption add-default-route=no use-ipsec=no disabled=no comment='.$comment,
            ':do { /ip firewall nat remove [find where comment="nat-l2tp-vpn"] } on-error={}',
            '/ip firewall nat add chain=srcnat action=masquerade out-interface='.$interface.' comment="nat-l2tp-vpn"',
        ];

        if ($router->portForwards->isNotEmpty()) {
            $lines[] = '# Port publik yang diteruskan ke router ini:';
            foreach ($router->portForwards as $forward) {
                $lines[] = '# '.$server.':'.$forward->public_port.' → port '.$forward->dst_port.' ('.$forward->label.')';
            }
            $lines[] = '# Port 22 di router ini boleh diteruskan ke perangkat mana pun.';
        }

        return implode("\n", $lines);
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
