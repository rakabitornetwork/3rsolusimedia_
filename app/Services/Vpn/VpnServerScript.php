<?php

namespace App\Services\Vpn;

use App\Models\PppoeCustomer;
use App\Models\VpnPortForward;
use App\Models\VpnRouter;

class VpnServerScript
{
    public function __construct(private readonly VpnAccessPlan $plan) {}

    /**
     * @return list<string>
     */
    public function commands(PppoeCustomer $customer): array
    {
        $customer->loadMissing('vpnPortForwards');
        $commands = [$this->secretCommand($customer, disabled: $this->secretDisabled($customer))];

        foreach ($customer->vpnPortForwards as $forward) {
            array_push($commands, ...$this->forwardLines((string) $customer->vpn_remote_address, $forward));
        }

        return $commands;
    }

    public function text(PppoeCustomer $customer): string
    {
        $lines = [
            '# Skrip server VPN untuk CHR. Bisa ditempel di terminal, atau dipakai tombol Push ke CHR.',
            ...$this->commands($customer),
        ];

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    public function removeCommands(PppoeCustomer $customer): array
    {
        $customer->loadMissing('vpnPortForwards');
        $commands = [];
        foreach ($customer->vpnPortForwards as $forward) {
            $comment = L2tpClientScript::quote($forward->comment());
            $commands[] = ':do { /ip firewall nat remove [find where comment='.$comment.'] } on-error={}';
            $commands[] = ':do { /ip firewall filter remove [find where comment='.$comment.'] } on-error={}';
        }

        $name = L2tpClientScript::quote((string) $customer->username);
        $commands[] = ':do { /ppp secret remove [find where name='.$name.'] } on-error={}';

        return $commands;
    }

    /**
     * @return list<string>
     */
    public function commandsForRouter(VpnRouter $router): array
    {
        $router->loadMissing(['customer', 'portForwards']);
        $customer = $router->customer;
        if (! $customer) {
            return [];
        }

        $commands = [$this->routerSecretCommand($router, $customer)];
        foreach ($router->portForwards as $forward) {
            array_push($commands, ...$this->forwardLines((string) $router->vpn_remote_address, $forward));
        }

        return $commands;
    }

    public function textForRouter(VpnRouter $router): string
    {
        $lines = [
            '# Skrip server untuk router '.$router->name.'. Secret baru aktif setelah tagihan router ini lunas.',
            ...$this->commandsForRouter($router),
        ];

        return implode("\n", $lines);
    }

    /**
     * @return list<string>
     */
    public function forwardCommands(PppoeCustomer $customer, VpnPortForward $forward): array
    {
        return $this->forwardLines((string) $customer->vpn_remote_address, $forward);
    }

    /**
     * @return list<string>
     */
    private function forwardLines(string $address, VpnPortForward $forward): array
    {
        $comment = L2tpClientScript::quote($forward->comment());
        $publicHost = VpnChrSettings::host();
        $dstAddress = $publicHost !== '' && filter_var($publicHost, FILTER_VALIDATE_IP)
            ? ' dst-address='.$publicHost
            : '';

        $nat = '/ip firewall nat add chain=dstnat action=dst-nat protocol=tcp'
            .$dstAddress
            .' dst-port='.$forward->public_port
            .' to-addresses='.$address
            .' to-ports='.$forward->dst_port
            .' comment='.$comment;

        $accept = '/ip firewall filter add chain=forward action=accept protocol=tcp dst-address='.$address
            .' dst-port='.$forward->dst_port
            .' comment='.$comment;

        return [
            ':do { /ip firewall nat remove [find where comment='.$comment.'] } on-error={}',
            ':do { /ip firewall filter remove [find where comment='.$comment.'] } on-error={}',
            $nat,
            ':if ([:len [/ip firewall filter find]] > 0) do={ '.$accept.' place-before=0 } else={ '.$accept.' }',
        ];
    }

    private function secretCommand(PppoeCustomer $customer, bool $disabled): string
    {
        $name = L2tpClientScript::quote((string) $customer->username);
        $password = L2tpClientScript::quote(str_replace(["\r", "\n"], '', (string) $customer->password));
        $comment = L2tpClientScript::quote('VPN '.$customer->username);
        $address = (string) $customer->vpn_remote_address;
        $local = VpnAccessPlan::LOCAL_ADDRESS;
        $profile = L2tpClientScript::quote(VpnAccessPlan::CHR_PROFILE);
        $flag = $disabled ? 'yes' : 'no';

        $add = '/ppp secret add name='.$name.' password='.$password.' service=l2tp profile='.$profile.' local-address='.$local.' remote-address='.$address.' comment='.$comment.' disabled='.$flag;
        $set = '/ppp secret set [find where name='.$name.'] password='.$password.' service=l2tp profile='.$profile.' local-address='.$local.' remote-address='.$address.' disabled='.$flag;

        return ':if ([:len [/ppp secret find where name='.$name.']] = 0) do={ '.$add.' } else={ '.$set.' }';
    }

    private function secretDisabled(PppoeCustomer $customer): bool
    {
        return ! $customer->is_active || $customer->status === 'isolated';
    }

    private function routerSecretCommand(VpnRouter $router, PppoeCustomer $customer): string
    {
        $name = L2tpClientScript::quote($router->name);
        $password = L2tpClientScript::quote(str_replace(["\r", "\n"], '', (string) $customer->password));
        $comment = L2tpClientScript::quote('VPN '.$customer->username.' / '.$router->name);
        $address = (string) $router->vpn_remote_address;
        $local = VpnAccessPlan::LOCAL_ADDRESS;
        $profile = L2tpClientScript::quote(VpnAccessPlan::CHR_PROFILE);
        $disabled = $this->secretDisabled($customer) || ! $router->isUsable();
        $flag = $disabled ? 'yes' : 'no';

        $add = '/ppp secret add name='.$name.' password='.$password.' service=l2tp profile='.$profile.' local-address='.$local.' remote-address='.$address.' comment='.$comment.' disabled='.$flag;
        $set = '/ppp secret set [find where name='.$name.'] password='.$password.' service=l2tp profile='.$profile.' local-address='.$local.' remote-address='.$address.' disabled='.$flag;

        return ':if ([:len [/ppp secret find where name='.$name.']] = 0) do={ '.$add.' } else={ '.$set.' }';
    }
}
