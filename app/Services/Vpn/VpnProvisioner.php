<?php

namespace App\Services\Vpn;

use App\Models\PppoeCustomer;
use App\Models\VpnPortForward;
use App\Models\VpnRouter;

class VpnProvisioner
{
    public function __construct(
        private readonly VpnAccessPlan $plan,
        private readonly VpnServerScript $script,
        private readonly RunsChrCommands $chr,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function push(PppoeCustomer $customer): array
    {
        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            return ['ok' => false, 'message' => 'Push CHR hanya untuk pelanggan VPN.'];
        }

        $this->plan->ensure($customer);
        $customer->refresh()->load('vpnPortForwards');

        if (! $customer->vpn_remote_address || $customer->vpnPortForwards->isEmpty()) {
            return ['ok' => false, 'message' => 'Skrip server belum lengkap.'];
        }

        return $this->runAll($this->script->commands($customer), $customer->vpnPortForwards->all());
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function pushForward(PppoeCustomer $customer, VpnPortForward $forward): array
    {
        $customer->loadMissing('vpnPortForwards');
        $result = $this->runAll($this->script->forwardCommands($customer, $forward), [$forward]);
        if (! $result['ok']) {
            return $result;
        }

        return ['ok' => true, 'message' => 'Port '.$forward->public_port.' terkirim ke CHR.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function pushRouter(VpnRouter $router): array
    {
        $router->loadMissing(['customer', 'portForwards']);
        $customer = $router->customer;
        if (! $customer || $customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            return ['ok' => false, 'message' => 'Push CHR hanya untuk pelanggan VPN.'];
        }

        if (! $router->vpn_remote_address || $router->portForwards->isEmpty()) {
            return ['ok' => false, 'message' => 'Skrip server router ini belum lengkap.'];
        }

        $result = $this->runAll($this->script->commandsForRouter($router), $router->portForwards->all());
        if (! $result['ok']) {
            return $result;
        }

        $active = $router->isUsable() && $customer->is_active && $customer->status !== 'isolated';

        return [
            'ok' => true,
            'message' => $active
                ? 'Router '.$router->name.' terkirim ke CHR dan secret-nya aktif.'
                : 'Router '.$router->name.' terkirim ke CHR. Secret tetap mati sampai tagihannya lunas.',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function syncRouterSecret(VpnRouter $router): array
    {
        $router->loadMissing('customer');
        $customer = $router->customer;
        if (! $customer || $customer->pppService() !== PppoeCustomer::SERVICE_L2TP || ! $router->vpn_remote_address) {
            return ['ok' => true, 'message' => 'Status CHR tidak diubah.'];
        }

        if (! VpnChrSettings::configured()) {
            return ['ok' => false, 'message' => 'Kredensial SSH CHR belum diisi.'];
        }

        $secret = $this->script->commandsForRouter($router)[0] ?? '';
        if ($secret === '') {
            return ['ok' => true, 'message' => 'Tidak ada secret untuk diselaraskan.'];
        }

        $result = $this->chr->run($secret);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Status secret '.$router->name.' diselaraskan.'
                : $this->safeMessage($result['message'], $customer),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function removeForward(PppoeCustomer $customer, VpnPortForward $forward): array
    {
        $comment = L2tpClientScript::quote($forward->comment());
        $commands = [
            ':do { /ip firewall nat remove [find where comment='.$comment.'] } on-error={}',
            ':do { /ip firewall filter remove [find where comment='.$comment.'] } on-error={}',
        ];

        $result = $this->runAll($commands, []);
        if ($result['ok']) {
            $forward->delete();
        }

        return $result['ok']
            ? ['ok' => true, 'message' => 'Port khusus dihapus dari CHR.']
            : $result;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function removeCustomer(PppoeCustomer $customer): array
    {
        $customer->load('vpnPortForwards');
        if ($customer->vpnPortForwards->isEmpty() && trim((string) $customer->username) === '') {
            return ['ok' => true, 'message' => 'Tidak ada aturan CHR.'];
        }

        return $this->runAll($this->script->removeCommands($customer), []);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function syncSecretState(PppoeCustomer $customer): array
    {
        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP || ! $customer->vpn_remote_address) {
            return ['ok' => true, 'message' => 'Status CHR tidak diubah.'];
        }

        if (! VpnChrSettings::configured()) {
            return ['ok' => false, 'message' => 'Kredensial SSH CHR belum diisi.'];
        }

        $commands = $this->script->commands($customer);
        $secret = $commands[0] ?? '';
        if ($secret === '') {
            return ['ok' => true, 'message' => 'Tidak ada secret untuk diselaraskan.'];
        }

        $result = $this->chr->run($secret);

        return [
            'ok' => $result['ok'],
            'message' => $result['ok']
                ? 'Status secret VPN di CHR diselaraskan.'
                : $this->safeMessage($result['message'], $customer),
        ];
    }

    /**
     * @param  list<string>  $commands
     * @param  list<VpnPortForward>  $forwards
     * @return array{ok: bool, message: string}
     */
    private function runAll(array $commands, array $forwards): array
    {
        if (! VpnChrSettings::configured()) {
            return ['ok' => false, 'message' => 'Kredensial SSH CHR belum diisi.'];
        }

        foreach ($commands as $command) {
            $result = $this->chr->run($command);
            if (! $result['ok']) {
                return [
                    'ok' => false,
                    'message' => 'CHR menolak perintah: '.$result['message'],
                ];
            }
        }

        $now = now();
        foreach ($forwards as $forward) {
            $forward->forceFill(['pushed_at' => $now])->save();
        }

        return ['ok' => true, 'message' => 'Skrip server sudah diisi ke CHR.'];
    }

    private function safeMessage(string $message, PppoeCustomer $customer): string
    {
        $password = (string) $customer->password;
        if ($password !== '') {
            $message = str_replace($password, '••••', $message);
        }

        return $message;
    }
}
