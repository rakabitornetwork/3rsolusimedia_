<?php

namespace App\Services\Vpn;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\VpnPortForward;
use App\Models\VpnRouter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChrVpnImporter
{
    public function __construct(private readonly RunsChrCommands $chr) {}

    /**
     * @return array{ok: bool, message: string, created: int, updated: int, ports: int}
     */
    public function import(): array
    {
        if (! VpnChrSettings::configured()) {
            return ['ok' => false, 'message' => 'Kredensial SSH CHR belum diisi.', 'created' => 0, 'updated' => 0, 'ports' => 0];
        }

        $routerId = VpnChrSettings::mikrotikRouterId();
        $mikrotik = $routerId ? MikrotikRouter::query()->find($routerId) : null;
        if (! $mikrotik) {
            return ['ok' => false, 'message' => 'Router CHR untuk VPN Tunnel belum ditandai.', 'created' => 0, 'updated' => 0, 'ports' => 0];
        }

        $secrets = $this->secrets();
        $forwards = $this->forwards();
        $groups = $this->groups($secrets, $forwards);

        $created = 0;
        $updated = 0;
        $ports = 0;
        $missingSecret = 0;

        DB::transaction(function () use ($groups, $mikrotik, &$created, &$updated, &$ports, &$missingSecret) {
            foreach ($groups as $group) {
                $customer = PppoeCustomer::query()
                    ->where('mikrotik_router_id', $mikrotik->id)
                    ->whereRaw('LOWER(username) = ?', [strtolower($group['username'])])
                    ->first();

                $disabled = $group['disabled'];
                if (! $customer) {
                    $customer = PppoeCustomer::query()->create([
                        'mikrotik_router_id' => $mikrotik->id,
                        'name' => $group['name'],
                        'username' => $group['username'],
                        'password' => $group['password'],
                        'ppp_service' => PppoeCustomer::SERVICE_L2TP,
                        'service_profile' => $group['profile'] !== '' ? $group['profile'] : VpnAccessPlan::CHR_PROFILE,
                        'start_date' => now()->toDateString(),
                        'billing_day' => min(28, (int) now()->day),
                        'due_date' => now()->addYear()->toDateString(),
                        'overdue_action' => 'bypass',
                        'status' => $disabled ? 'disabled' : 'active',
                        'sync_status' => 'synced',
                        'sync_message' => 'Diimpor dari CHR. Secret tidak diubah.',
                        'last_synced_at' => now(),
                        'notes' => $group['has_secret']
                            ? 'Diimpor dari CHR. Tagihan belum dipasang dan aturan NAT di CHR tidak diubah.'
                            : 'Diimpor dari NAT CHR. Secret L2TP belum ada, jadi jangan push sebelum secret diisi.',
                        'is_active' => ! $disabled,
                    ]);
                    $created++;
                } else {
                    if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
                        throw new RuntimeException('Username '.$group['username'].' sudah dipakai pelanggan non-VPN.');
                    }

                    $customer->forceFill([
                        'service_profile' => $group['profile'] !== '' ? $group['profile'] : $customer->service_profile,
                        'status' => $disabled ? 'disabled' : 'active',
                        'is_active' => ! $disabled,
                        'sync_status' => 'synced',
                        'sync_message' => 'Port forward disegarkan dari CHR. Secret tidak diubah.',
                        'last_synced_at' => now(),
                    ]);
                    if ($group['has_secret'] && $group['password'] !== '') {
                        $customer->password = $group['password'];
                    }
                    $customer->save();
                    $updated++;
                }

                if (! $group['has_secret']) {
                    $missingSecret++;
                }

                $router = VpnRouter::query()
                    ->where('pppoe_customer_id', $customer->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($group['username'])])
                    ->first();

                if (! $router) {
                    $router = VpnRouter::query()->create([
                        'pppoe_customer_id' => $customer->id,
                        'name' => $group['username'],
                        'vpn_remote_address' => $group['address'],
                        'billing_day' => min(28, (int) now()->day),
                        'included' => true,
                        'service_until' => $customer->due_date?->toDateString(),
                    ]);
                } else {
                    $router->forceFill([
                        'vpn_remote_address' => $group['address'],
                        'included' => true,
                    ])->save();
                }

                $router->portForwards()->delete();
                foreach ($group['forwards'] as $forward) {
                    VpnPortForward::query()->create([
                        'pppoe_customer_id' => $customer->id,
                        'vpn_router_id' => $router->id,
                        'public_port' => $forward['public_port'],
                        'public_port_end' => $forward['public_port_end'],
                        'dst_port' => $forward['dst_port'],
                        'dst_port_end' => $forward['dst_port_end'],
                        'kind' => isset(VpnAccessPlan::STANDARD[$forward['dst_port']]) && $forward['dst_port_end'] === null
                            ? VpnPortForward::KIND_STANDARD
                            : VpnPortForward::KIND_CUSTOM,
                        'label' => $forward['label'],
                        'pushed_at' => now(),
                    ]);
                    $ports++;
                }
            }
        });

        $message = 'Data CHR masuk ke pelanggan VPN: '.$created.' baru, '.$updated.' diperbarui, '.$ports.' port forward.';
        if ($missingSecret > 0) {
            $message .= ' '.$missingSecret.' pelanggan hanya punya NAT, tanpa secret L2TP.';
        }
        $message .= ' Aturan di CHR tidak diubah.';

        return [
            'ok' => true,
            'message' => $message,
            'created' => $created,
            'updated' => $updated,
            'ports' => $ports,
        ];
    }

    /**
     * @return list<array{username: string, address: string, disabled: bool, profile: string, password: string}>
     */
    private function secrets(): array
    {
        $result = $this->chr->run(
            ':foreach i in=[/ppp secret find where service=l2tp] do={:put ([/ppp secret get $i name] . "|" . [/ppp secret get $i remote-address] . "|" . [/ppp secret get $i disabled] . "|" . [/ppp secret get $i profile])}'
        );
        $this->assertOk($result, 'Daftar secret L2TP tidak terbaca.');

        $secrets = [];
        foreach ($this->lines($result['output']) as $line) {
            $parts = explode('|', $line);
            if (count($parts) < 3) {
                continue;
            }
            $username = trim($parts[0]);
            $address = trim($parts[1]);
            if ($username === '' || filter_var($address, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $secrets[] = [
                'username' => $username,
                'address' => $address,
                'disabled' => $this->isDisabled($parts[2] ?? ''),
                'profile' => trim($parts[3] ?? ''),
                'password' => $this->password($username),
            ];
        }

        return $secrets;
    }

    /**
     * @return list<array{protocol: string, public_port: int, public_port_end: ?int, dst_port: int, dst_port_end: ?int, address: string, label: string, disabled: bool}>
     */
    private function forwards(): array
    {
        $result = $this->chr->run(
            ':foreach i in=[/ip firewall nat find where chain=dstnat action=dst-nat] do={:put ([/ip firewall nat get $i protocol] . "|" . [/ip firewall nat get $i dst-port] . "|" . [/ip firewall nat get $i to-addresses] . "|" . [/ip firewall nat get $i to-ports] . "|" . [/ip firewall nat get $i comment] . "|" . [/ip firewall nat get $i disabled])}'
        );
        $this->assertOk($result, 'Daftar NAT CHR tidak terbaca.');

        $forwards = [];
        foreach ($this->lines($result['output']) as $line) {
            $parts = explode('|', $line);
            if (count($parts) < 6 || strtolower(trim($parts[0])) !== 'tcp') {
                continue;
            }
            $disabled = array_pop($parts);
            $comment = trim(implode('|', array_slice($parts, 4)));
            $public = $this->portRange($parts[1]);
            $destination = $this->portRange($parts[3]);
            $address = trim(strtok($parts[2], ','));
            if ($public === null || $destination === null || filter_var($address, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            $label = trim(explode('|', $comment)[0] ?? '');
            if ($label === '') {
                $label = 'Port '.$destination[0];
            }
            if ($this->isDisabled($disabled)) {
                $label = mb_substr($label.' · nonaktif', 0, 80);
            }
            $forwards[] = [
                'protocol' => 'tcp',
                'public_port' => $public[0],
                'public_port_end' => $public[1],
                'dst_port' => $destination[0],
                'dst_port_end' => $destination[1],
                'address' => $address,
                'comment' => $comment,
                'label' => mb_substr($label, 0, 80),
                'disabled' => $this->isDisabled($disabled),
            ];
        }

        return $forwards;
    }

    /**
     * @param  list<array{username: string, address: string, disabled: bool, profile: string, password: string}>  $secrets
     * @param  list<array{public_port: int, public_port_end: ?int, dst_port: int, dst_port_end: ?int, address: string, label: string, disabled: bool}>  $forwards
     * @return list<array{username: string, name: string, address: string, password: string, disabled: bool, profile: string, has_secret: bool, forwards: list<array<string, mixed>}>
     */
    private function groups(array $secrets, array $forwards): array
    {
        $byAddress = [];
        foreach ($forwards as $forward) {
            $byAddress[$forward['address']][] = $forward;
        }

        $groups = [];
        $seen = [];
        foreach ($secrets as $secret) {
            $address = $secret['address'];
            $seen[$address] = true;
            $related = $byAddress[$address] ?? [];
            $groups[] = [
                'username' => $secret['username'],
                'name' => $this->displayName($related, $secret['username']),
                'address' => $address,
                'password' => $secret['password'],
                'disabled' => $secret['disabled'],
                'profile' => $secret['profile'],
                'has_secret' => true,
                'forwards' => $related,
            ];
        }

        foreach ($byAddress as $address => $related) {
            if (isset($seen[$address])) {
                continue;
            }
            $name = $this->displayName($related, 'vpn-'.$address);
            $username = $this->usernameFromName($name, $address);
            $groups[] = [
                'username' => $username,
                'name' => $name,
                'address' => $address,
                'password' => '',
                'disabled' => false,
                'profile' => VpnAccessPlan::CHR_PROFILE,
                'has_secret' => false,
                'forwards' => $related,
            ];
        }

        return $groups;
    }

    /**
     * @param  list<array{label: string}>  $forwards
     */
    private function displayName(array $forwards, string $fallback): string
    {
        $counts = [];
        foreach ($forwards as $forward) {
            $name = trim((string) (array_slice(explode('|', (string) ($forward['comment'] ?? '')), 1)[0] ?? ''));
            if ($name === '') {
                continue;
            }
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        if ($counts === []) {
            return $fallback;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function usernameFromName(string $name, string $address): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{1,31}$/', $slug) === 1) {
            return $slug;
        }

        $host = str_replace('.', '', $address);

        return 'vpn'.$host;
    }

    private function password(string $username): string
    {
        $quoted = L2tpClientScript::quote($username);
        $result = $this->chr->run(':put [/ppp secret get [find where name='.$quoted.'] password]');
        $this->assertOk($result, 'Password secret '.$username.' tidak terbaca.');
        $lines = $this->lines($result['output']);

        return $lines[0] ?? '';
    }

    /**
     * @param  array{ok: bool, output: string, message: string}  $result
     */
    private function assertOk(array $result, string $fallback): void
    {
        if ($result['ok']) {
            return;
        }

        throw new RuntimeException($result['message'] !== '' ? $result['message'] : $fallback);
    }

    /**
     * @return list<string>
     */
    private function lines(string $output): array
    {
        $lines = preg_split('/\r?\n/', $output) ?: [];

        return array_values(array_filter(array_map('trim', $lines), function (string $line) {
            return $line !== '' && ! str_starts_with($line, 'Warning:');
        }));
    }

    private function isDisabled(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', 'yes'], true);
    }

    /**
     * @return array{0: int, 1: ?int}|null
     */
    private function portRange(string $value): ?array
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,5})-(\d{1,5})$/', $value, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
            if ($start < 1 || $end > 65535 || $end < $start) {
                return null;
            }

            return [$start, $end === $start ? null : $end];
        }

        if (preg_match('/^\d{1,5}$/', $value) !== 1) {
            return null;
        }

        $port = (int) $value;
        if ($port < 1 || $port > 65535) {
            return null;
        }

        return [$port, null];
    }
}
