<?php

namespace App\Services;

use App\Models\MikrotikRouter;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Exceptions\BadCredentialsException;
use RouterOS\Exceptions\ClientException;
use RouterOS\Exceptions\ConfigException;
use RouterOS\Exceptions\ConnectException;
use RouterOS\Exceptions\QueryException;
use RouterOS\Query;
use Throwable;

class MikrotikApiService
{
    public function makeClient(MikrotikRouter $router, ?int $timeout = null): Client
    {
        $timeout = $timeout ?? (int) env('MIKROTIK_API_TIMEOUT', 15);

        // Always cast to expected types — partial Eloquent selects can leave
        // username/password null and trigger ConfigException in the SDK.
        $config = (new Config())
            ->set('host', (string) $router->host)
            ->set('port', (int) ($router->port ?: 8728))
            ->set('user', (string) ($router->username ?? ''))
            ->set('pass', (string) ($router->password ?? ''))
            ->set('timeout', $timeout)
            ->set('socket_timeout', $timeout)
            ->set('attempts', 1)
            ->set('delay', 1)
            ->set('ssl', (bool) $router->use_ssl);

        return new Client($config);
    }

    /**
     * @return array{ok: bool, message: string, info?: array<string, mixed>}
     */
    public function testConnection(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $identity = $this->first($client, '/system/identity/print');
            $resource = $this->first($client, '/system/resource/print');

            return [
                'ok' => true,
                'message' => 'Koneksi berhasil ke '.$router->host.':'.$router->port,
                'info' => [
                    'identity' => $identity['name'] ?? null,
                    'version' => $resource['version'] ?? null,
                    'board' => $resource['board-name'] ?? null,
                    'uptime' => $resource['uptime'] ?? null,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    public function fetchDashboard(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);

            $identity = $this->first($client, '/system/identity/print');
            $resource = $this->first($client, '/system/resource/print');
            $clock = $this->first($client, '/system/clock/print');
            $interfaces = $client->query((new Query('/interface/print'))->where('type', 'ether'))->read();

            $pppActive = [];
            $hotspotActive = [];

            try {
                $pppActive = $client->query(new Query('/ppp/active/print'))->read();
            } catch (Throwable) {
                $pppActive = [];
            }

            try {
                $hotspotActive = $client->query(new Query('/ip/hotspot/active/print'))->read();
            } catch (Throwable) {
                $hotspotActive = [];
            }

            $totalRx = 0;
            $totalTx = 0;
            $ifaceRows = [];
            $physicalInterfaces = [];

            foreach ($interfaces as $iface) {
                if (($iface['disabled'] ?? 'false') === 'true') {
                    continue;
                }

                $type = strtolower((string) ($iface['type'] ?? ''));
                $name = (string) ($iface['name'] ?? '-');
                $nameLower = strtolower($name);

                if ($type === 'ether') {
                    $physicalInterfaces[] = [
                        'name' => $name,
                        'running' => ($iface['running'] ?? 'false') === 'true',
                        'comment' => $iface['comment'] ?? null,
                        'is_wan' => false,
                    ];
                }

                // Sembunyikan interface PPPoE dari kartu daftar interface
                if (str_contains($type, 'pppoe') || str_starts_with($nameLower, '<pppoe-')) {
                    continue;
                }

                $rx = (int) ($iface['rx-byte'] ?? 0);
                $tx = (int) ($iface['tx-byte'] ?? 0);
                $totalRx += $rx;
                $totalTx += $tx;

                $ifaceRows[] = [
                    'name' => $name,
                    'type' => $iface['type'] ?? '-',
                    'running' => ($iface['running'] ?? 'false') === 'true',
                    'rx_byte' => $rx,
                    'tx_byte' => $tx,
                ];
            }

            usort($ifaceRows, fn ($a, $b) => strcmp($a['name'], $b['name']));
            $wanNames = $this->defaultRouteInterfaceNames($client);
            $physicalInterfaces = $this->enrichPhysicalInterfaces($physicalInterfaces, $wanNames);

            return [
                'ok' => true,
                'data' => [
                    'identity' => $identity['name'] ?? $router->name,
                    'version' => $resource['version'] ?? null,
                    'board' => $resource['board-name'] ?? null,
                    'architecture' => $resource['architecture-name'] ?? null,
                    'uptime' => $resource['uptime'] ?? null,
                    'cpu_load' => isset($resource['cpu-load']) ? (int) $resource['cpu-load'] : null,
                    'free_memory' => isset($resource['free-memory']) ? (int) $resource['free-memory'] : null,
                    'total_memory' => isset($resource['total-memory']) ? (int) $resource['total-memory'] : null,
                    'free_hdd' => isset($resource['free-hdd-space']) ? (int) $resource['free-hdd-space'] : null,
                    'total_hdd' => isset($resource['total-hdd-space']) ? (int) $resource['total-hdd-space'] : null,
                    'platform' => $resource['platform'] ?? null,
                    'time' => $clock['time'] ?? null,
                    'date' => $clock['date'] ?? null,
                    'interface_count' => count($ifaceRows),
                    'ppp_active' => count($pppActive),
                    'hotspot_active' => count($hotspotActive),
                    'traffic' => [
                        'rx_byte' => $totalRx,
                        'tx_byte' => $totalTx,
                    ],
                    'interfaces' => array_slice($ifaceRows, 0, 20),
                    'physical_interfaces' => $physicalInterfaces,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, interfaces?: array<int, array<string, mixed>>}
     */
    public function listPhysicalInterfaces(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $interfaces = $client->query((new Query('/interface/print'))->where('type', 'ether'))->read();

            $physicalInterfaces = collect($interfaces)
                ->filter(function (array $iface) {
                    return ($iface['disabled'] ?? 'false') !== 'true'
                        && strtolower((string) ($iface['type'] ?? '')) === 'ether'
                        && ($iface['name'] ?? '') !== '';
                })
                ->map(fn (array $iface) => [
                    'name' => (string) $iface['name'],
                    'running' => ($iface['running'] ?? 'false') === 'true',
                    'comment' => $iface['comment'] ?? null,
                    'is_wan' => false,
                ])
                ->values()
                ->all();

            $wanNames = $this->defaultRouteInterfaceNames($client);
            $physicalInterfaces = $this->enrichPhysicalInterfaces($physicalInterfaces, $wanNames);

            return [
                'ok' => true,
                'interfaces' => $physicalInterfaces,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'interfaces' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    public function monitorTraffic(MikrotikRouter $router, string $interface): array
    {
        try {
            $client = $this->makeClient($router, timeout: 10);

            $query = (new Query('/interface/monitor-traffic'))
                ->equal('interface', $interface)
                ->equal('once', '');

            $rows = $client->query($query)->read();
            $row = $rows[0] ?? null;

            if (! $row) {
                return [
                    'ok' => false,
                    'message' => 'Tidak ada data traffic untuk interface '.$interface,
                ];
            }

            return [
                'ok' => true,
                'data' => [
                    'interface' => $interface,
                    'rx_bps' => (int) ($row['rx-bits-per-second'] ?? 0),
                    'tx_bps' => (int) ($row['tx-bits-per-second'] ?? 0),
                    'rx_pps' => (int) ($row['rx-packets-per-second'] ?? 0),
                    'tx_pps' => (int) ($row['tx-packets-per-second'] ?? 0),
                    'fp_rx_bps' => isset($row['fp-rx-bits-per-second']) ? (int) $row['fp-rx-bits-per-second'] : null,
                    'fp_tx_bps' => isset($row['fp-tx-bits-per-second']) ? (int) $row['fp-tx-bits-per-second'] : null,
                    'checked_at' => now()->toIso8601String(),
                ],
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
            ];
        }
    }

    /**
     * Live traffic untuk sesi PPPoE aktif (interface dinamis <pppoe-username>).
     *
     * @return array{ok: bool, online: bool, message?: string, data?: array<string, mixed>}
     */
    public function monitorPppoeUserTraffic(MikrotikRouter $router, string $username): array
    {
        $username = trim($username);
        if ($username === '') {
            return [
                'ok' => false,
                'online' => false,
                'message' => 'Username PPPoE kosong.',
            ];
        }

        $sessions = $this->listPppActiveSessions($router);
        if (! ($sessions['ok'] ?? false)) {
            return [
                'ok' => false,
                'online' => false,
                'message' => $sessions['message'] ?? 'Gagal membaca sesi PPPoE aktif.',
            ];
        }

        $online = collect($sessions['sessions'] ?? [])
            ->contains(fn (array $session) => strcasecmp((string) ($session['name'] ?? ''), $username) === 0);

        if (! $online) {
            return [
                'ok' => false,
                'online' => false,
                'message' => 'Sesi PPPoE tidak online.',
            ];
        }

        $interface = '<pppoe-'.$username.'>';
        $traffic = $this->monitorTraffic($router, $interface);

        if (! ($traffic['ok'] ?? false)) {
            return [
                'ok' => false,
                'online' => true,
                'message' => $traffic['message'] ?? 'Gagal membaca traffic interface '.$interface,
            ];
        }

        return [
            'ok' => true,
            'online' => true,
            'data' => $traffic['data'],
        ];
    }

    /**
     * @return array{ok: bool, message?: string, profiles?: array<int, array<string, mixed>>, isolir_profiles?: array<int, array<string, mixed>>}
     */
    public function listPppProfiles(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ppp/profile/print'))->read();

            $profiles = collect($rows)
                ->map(fn (array $row) => $this->mapPppProfile($row))
                ->filter(fn (array $row) => $row['name'] !== '')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            $isolirProfiles = array_values(array_filter(
                $profiles,
                function (array $profile) {
                    $name = strtolower($profile['name']);

                    return str_contains($name, 'isolir') || str_contains($name, 'expired');
                }
            ));

            return [
                'ok' => true,
                'profiles' => $profiles,
                'isolir_profiles' => $isolirProfiles,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'profiles' => [],
                'isolir_profiles' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, pools?: array<int, array{name: string, ranges: ?string}>}
     */
    public function listIpPools(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ip/pool/print'))->read();

            $pools = collect($rows)
                ->map(fn (array $row) => [
                    'name' => $row['name'] ?? '',
                    'ranges' => $row['ranges'] ?? null,
                ])
                ->filter(fn (array $row) => $row['name'] !== '')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return [
                'ok' => true,
                'pools' => $pools,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'pools' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, queue_types?: array<int, array{name: string, kind: ?string}>}
     */
    public function listQueueTypes(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/queue/type/print'))->read();

            $queueTypes = collect($rows)
                ->map(fn (array $row) => [
                    'name' => $row['name'] ?? '',
                    'kind' => $row['kind'] ?? null,
                ])
                ->filter(fn (array $row) => $row['name'] !== '')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return [
                'ok' => true,
                'queue_types' => $queueTypes,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'queue_types' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, users?: array<int, array<string, mixed>>, active_count?: int}
     */
    public function listHotspotUsers(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ip/hotspot/user/print'))->read();

            $activeNames = [];
            try {
                $activeRows = $client->query(new Query('/ip/hotspot/active/print'))->read();
                $activeNames = collect($activeRows)
                    ->map(fn (array $row) => $row['user'] ?? $row['name'] ?? '')
                    ->filter()
                    ->flip()
                    ->all();
            } catch (Throwable) {
                // hotspot may not be configured
            }

            $users = collect($rows)
                ->map(function (array $row) use ($activeNames) {
                    $name = $row['name'] ?? '';

                    return [
                        'id' => $row['.id'] ?? '',
                        'name' => $name,
                        'password' => $row['password'] ?? null,
                        'profile' => $row['profile'] ?? null,
                        'server' => $row['server'] ?? 'all',
                        'limit_uptime' => $row['limit-uptime'] ?? null,
                        'limit_bytes_total' => isset($row['limit-bytes-total']) ? (int) $row['limit-bytes-total'] : null,
                        'uptime' => $row['uptime'] ?? null,
                        'bytes_in' => isset($row['bytes-in']) ? (int) $row['bytes-in'] : null,
                        'bytes_out' => isset($row['bytes-out']) ? (int) $row['bytes-out'] : null,
                        'comment' => $row['comment'] ?? null,
                        'disabled' => ($row['disabled'] ?? 'false') === 'true',
                        'is_online' => isset($activeNames[$name]),
                    ];
                })
                ->filter(fn (array $row) => $row['name'] !== '')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return [
                'ok' => true,
                'users' => $users,
                'active_count' => count($activeNames),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'users' => [],
                'active_count' => 0,
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, profiles?: array<int, array<string, mixed>>}
     */
    public function listHotspotUserProfiles(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ip/hotspot/user/profile/print'))->read();

            $profiles = collect($rows)
                ->map(fn (array $row) => $this->mapHotspotUserProfile($row))
                ->filter(fn (array $row) => $row['name'] !== '')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return ['ok' => true, 'profiles' => $profiles];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'profiles' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, profile?: array<string, mixed>}
     */
    public function getHotspotUserProfile(MikrotikRouter $router, string $profileId): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ip/hotspot/user/profile/print'))->read();
            $match = collect($rows)->first(
                fn (array $row) => ($row['.id'] ?? '') === $profileId || ($row['name'] ?? '') === $profileId
            );

            if (! $match) {
                return ['ok' => false, 'message' => 'Profile hotspot tidak ditemukan di RouterOS.'];
            }

            return [
                'ok' => true,
                'profile' => $this->mapHotspotUserProfile($match),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @param  array{name: string, rate_limit?: ?string, session_timeout?: ?string, idle_timeout?: ?string, shared_users?: ?int|string, address_list?: ?string}  $data
     * @return array{ok: bool, message: string}
     */
    public function createHotspotUserProfile(MikrotikRouter $router, array $data): array
    {
        try {
            $client = $this->makeClient($router);
            $query = (new Query('/ip/hotspot/user/profile/add'))->equal('name', $data['name']);
            $this->applyHotspotUserProfileFields($query, $data);
            $client->query($query)->read();

            return ['ok' => true, 'message' => 'Profile hotspot berhasil ditambahkan di RouterOS.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @param  array{name: string, rate_limit?: ?string, session_timeout?: ?string, idle_timeout?: ?string, shared_users?: ?int|string, address_list?: ?string}  $data
     * @return array{ok: bool, message: string}
     */
    public function updateHotspotUserProfile(MikrotikRouter $router, string $profileId, array $data): array
    {
        try {
            $client = $this->makeClient($router);
            $query = (new Query('/ip/hotspot/user/profile/set'))
                ->equal('.id', $profileId)
                ->equal('name', $data['name']);
            $this->applyHotspotUserProfileFields($query, $data);
            $client->query($query)->read();

            return ['ok' => true, 'message' => 'Profile hotspot berhasil diperbarui di RouterOS.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function removeHotspotUserProfile(MikrotikRouter $router, string $profileId): array
    {
        try {
            $client = $this->makeClient($router);
            $client->query(
                (new Query('/ip/hotspot/user/profile/remove'))->equal('.id', $profileId)
            )->read();

            return ['ok' => true, 'message' => 'Profile hotspot berhasil dihapus dari RouterOS.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message?: string, servers?: array<int, array{name: string}>}
     */
    public function listHotspotServers(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ip/hotspot/print'))->read();

            $servers = collect($rows)
                ->map(fn (array $row) => [
                    'name' => $row['name'] ?? '',
                ])
                ->filter(fn (array $row) => $row['name'] !== '')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return ['ok' => true, 'servers' => $servers];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'servers' => [],
            ];
        }
    }

    /**
     * @param  array{name: string, password: string, profile?: ?string, server?: ?string, limit_uptime?: ?string, limit_bytes_total?: ?int, comment?: ?string}  $data
     * @return array{ok: bool, message: string}
     */
    public function createHotspotUser(MikrotikRouter $router, array $data): array
    {
        try {
            $client = $this->makeClient($router);
            $query = (new Query('/ip/hotspot/user/add'))
                ->equal('name', $data['name'])
                ->equal('password', $data['password']);

            if (! empty($data['profile'])) {
                $query->equal('profile', (string) $data['profile']);
            }
            if (! empty($data['server']) && $data['server'] !== 'all') {
                $query->equal('server', (string) $data['server']);
            }
            if (! empty($data['limit_uptime'])) {
                $query->equal('limit-uptime', (string) $data['limit_uptime']);
            }
            if (! empty($data['limit_bytes_total'])) {
                $query->equal('limit-bytes-total', (string) $data['limit_bytes_total']);
            }
            if (! empty($data['comment'])) {
                $query->equal('comment', (string) $data['comment']);
            }

            $client->query($query)->read();

            return ['ok' => true, 'message' => 'Voucher hotspot berhasil dibuat.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message: string, vouchers?: array<int, array{name: string, password: string}>}
     */
    public function createHotspotVoucherBatch(MikrotikRouter $router, array $options): array
    {
        $quantity = max(1, min(100, (int) ($options['quantity'] ?? 1)));
        $prefix = (string) ($options['prefix'] ?? 'VC');
        $length = max(4, min(12, (int) ($options['code_length'] ?? 6)));
        $format = (string) ($options['code_format'] ?? 'numbers');
        $passwordMode = ($options['password_mode'] ?? 'same') === 'random' ? 'random' : 'same';
        $created = [];
        $errors = [];

        for ($i = 0; $i < $quantity; $i++) {
            $code = $this->generateVoucherCode($length, $format);
            $name = $prefix !== '' ? $prefix.$code : $code;
            $password = $passwordMode === 'random'
                ? $this->generateVoucherCode($length, $format)
                : $name;

            $result = $this->createHotspotUser($router, [
                'name' => $name,
                'password' => $password,
                'profile' => $options['profile'] ?? null,
                'server' => $options['server'] ?? null,
                'limit_uptime' => $options['limit_uptime'] ?? null,
                'limit_bytes_total' => $options['limit_bytes_total'] ?? null,
                'comment' => $options['comment'] ?? null,
            ]);

            if ($result['ok']) {
                $created[] = ['name' => $name, 'password' => $password];
            } else {
                $errors[] = $name.': '.$result['message'];
            }
        }

        if ($created === []) {
            return [
                'ok' => false,
                'message' => $errors[0] ?? 'Gagal membuat voucher hotspot.',
                'vouchers' => [],
            ];
        }

        $message = count($created).' voucher berhasil dibuat di RouterOS.';
        if ($errors !== []) {
            $message .= ' '.count($errors).' gagal.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'vouchers' => $created,
        ];
    }

    public function generateVoucherCode(int $length, string $format = 'numbers'): string
    {
        return $this->randomVoucherCode($length, $format);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function removeHotspotUser(MikrotikRouter $router, string $userId): array
    {
        try {
            $client = $this->makeClient($router);
            $client->query(
                (new Query('/ip/hotspot/user/remove'))->equal('.id', $userId)
            )->read();

            return ['ok' => true, 'message' => 'Voucher hotspot berhasil dihapus.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function setHotspotUserDisabled(MikrotikRouter $router, string $userId, bool $disabled): array
    {
        try {
            $client = $this->makeClient($router);
            $client->query(
                (new Query('/ip/hotspot/user/set'))
                    ->equal('.id', $userId)
                    ->equal('disabled', $disabled ? 'yes' : 'no')
            )->read();

            return [
                'ok' => true,
                'message' => $disabled ? 'Voucher dinonaktifkan.' : 'Voucher diaktifkan kembali.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    private function randomVoucherCode(int $length, string $format = 'numbers'): string
    {
        $digits = '0123456789';
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';

        if (in_array($format, ['alt_numbers_upper', 'alt_numbers_lower'], true)) {
            $letters = $format === 'alt_numbers_upper' ? $upper : $lower;
            $code = '';

            for ($i = 0; $i < $length; $i++) {
                $pool = $i % 2 === 0 ? $digits : $letters;
                $code .= $pool[random_int(0, strlen($pool) - 1)];
            }

            return $code;
        }

        $chars = match ($format) {
            'numbers', 'numeric' => $digits,
            'upper', 'letters' => $upper,
            'lower' => $lower,
            'numbers_lower' => $digits.$lower,
            'numbers_upper', 'alphanumeric', 'hex' => $digits.$upper,
            default => $digits,
        };

        $max = strlen($chars) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * @return array{ok: bool, message?: string, profile?: array<string, mixed>}
     */
    public function getPppProfile(MikrotikRouter $router, string $profileId): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ppp/profile/print'))->read();
            $match = collect($rows)->first(
                fn (array $row) => ($row['.id'] ?? '') === $profileId || ($row['name'] ?? '') === $profileId
            );

            if (! $match) {
                return ['ok' => false, 'message' => 'Profile PPP tidak ditemukan di RouterOS.'];
            }

            return [
                'ok' => true,
                'profile' => $this->mapPppProfile($match),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @param  array{name: string, rate_limit?: ?string, local_address?: ?string, remote_address?: ?string, only_one?: ?string, dns_server?: ?string, comment?: ?string}  $data
     * @return array{ok: bool, message: string}
     */
    public function createPppProfile(MikrotikRouter $router, array $data): array
    {
        try {
            $client = $this->makeClient($router);
            $query = (new Query('/ppp/profile/add'))->equal('name', $data['name']);
            $this->applyPppProfileFields($query, $data);
            $client->query($query)->read();

            // Some RouterOS builds ignore queue-type on add; enforce via set.
            $this->ensurePppProfileQueueType($client, $data['name'], $data);

            return ['ok' => true, 'message' => 'Profile PPP berhasil ditambahkan di RouterOS.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @param  array{name: string, rate_limit?: ?string, local_address?: ?string, remote_address?: ?string, only_one?: ?string, dns_server?: ?string, comment?: ?string}  $data
     * @return array{ok: bool, message: string}
     */
    public function updatePppProfile(MikrotikRouter $router, string $profileId, array $data): array
    {
        try {
            $client = $this->makeClient($router);
            $query = (new Query('/ppp/profile/set'))
                ->equal('.id', $profileId)
                ->equal('name', $data['name']);
            $this->applyPppProfileFields($query, $data);
            $client->query($query)->read();

            $queueType = $this->combineQueueType(
                $data['queue_type_rx'] ?? null,
                $data['queue_type_tx'] ?? null,
            );

            if ($queueType !== null) {
                $client->query(
                    (new Query('/ppp/profile/set'))
                        ->equal('.id', $profileId)
                        ->equal('queue-type', $queueType)
                )->read();
            }

            return ['ok' => true, 'message' => 'Profile PPP berhasil diperbarui di RouterOS.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function removePppProfile(MikrotikRouter $router, string $profileId): array
    {
        try {
            $client = $this->makeClient($router);
            $client->query(
                (new Query('/ppp/profile/remove'))->equal('.id', $profileId)
            )->read();

            return ['ok' => true, 'message' => 'Profile PPP berhasil dihapus dari RouterOS.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function upsertPppSecret(
        MikrotikRouter $router,
        string $username,
        string $password,
        ?string $profile = null,
        ?string $comment = null,
        bool $disabled = false,
        bool $disconnectActive = false,
        bool $updatePassword = true,
    ): array {
        try {
            $client = $this->makeClient($router);
            $existing = $client->query(
                (new Query('/ppp/secret/print'))->where('name', $username)
            )->read();

            if (! empty($existing[0]['.id'])) {
                $query = (new Query('/ppp/secret/set'))
                    ->equal('.id', $existing[0]['.id'])
                    ->equal('disabled', $disabled ? 'yes' : 'no');

                // Jangan timpa password RouterOS saat sync status (isolir/lunas)
                // atau bila password kosong — biarkan secret yang sudah ada.
                if ($updatePassword && $password !== '') {
                    $query->equal('password', $password);
                }

                if ($profile) {
                    $query->equal('profile', $profile);
                }
                if ($comment !== null) {
                    $query->equal('comment', $comment);
                }

                $client->query($query)->read();

                if ($disabled || $disconnectActive) {
                    $this->disconnectPppActive($client, $username);
                }

                return [
                    'ok' => true,
                    'message' => $disabled
                        ? 'Secret PPPoE dinonaktifkan di RouterOS.'
                        : 'Secret PPPoE berhasil diperbarui di RouterOS.',
                ];
            }

            if ($password === '') {
                return [
                    'ok' => false,
                    'message' => 'Password PPPoE wajib diisi untuk membuat secret baru di RouterOS.',
                ];
            }

            $query = (new Query('/ppp/secret/add'))
                ->equal('name', $username)
                ->equal('password', $password)
                ->equal('service', 'pppoe')
                ->equal('disabled', $disabled ? 'yes' : 'no');

            if ($profile) {
                $query->equal('profile', $profile);
            }
            if ($comment !== null) {
                $query->equal('comment', $comment);
            }

            $client->query($query)->read();

            return [
                'ok' => true,
                'message' => $disabled
                    ? 'Secret PPPoE ditambahkan dalam keadaan nonaktif di RouterOS.'
                    : 'Secret PPPoE berhasil ditambahkan di RouterOS.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function setPppSecretProfile(MikrotikRouter $router, string $username, string $profile): array
    {
        try {
            $client = $this->makeClient($router);
            $existing = $client->query(
                (new Query('/ppp/secret/print'))->where('name', $username)
            )->read();

            if (empty($existing[0]['.id'])) {
                return ['ok' => false, 'message' => 'Secret PPPoE tidak ditemukan di RouterOS.'];
            }

            $client->query(
                (new Query('/ppp/secret/set'))
                    ->equal('.id', $existing[0]['.id'])
                    ->equal('profile', $profile)
            )->read();

            // Putus sesi aktif agar profile baru langsung diterapkan
            $this->disconnectPppActive($client, $username);

            return ['ok' => true, 'message' => 'Profile secret diganti ke '.$profile];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function removePppSecret(MikrotikRouter $router, string $username): array
    {
        try {
            $client = $this->makeClient($router);
            $existing = $client->query(
                (new Query('/ppp/secret/print'))->where('name', $username)
            )->read();

            if (empty($existing[0]['.id'])) {
                return ['ok' => true, 'message' => 'Secret tidak ada di RouterOS.'];
            }

            $client->query(
                (new Query('/ppp/secret/remove'))->equal('.id', $existing[0]['.id'])
            )->read();

            return ['ok' => true, 'message' => 'Secret PPPoE dihapus dari RouterOS.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * Ambil satu PPP secret (untuk prefill impor pelanggan).
     *
     * @return array{ok: bool, message?: string, secret?: array<string, mixed>}
     */
    public function getPppSecret(MikrotikRouter $router, string $username): array
    {
        try {
            $client = $this->makeClient($router);
            $existing = $client->query(
                (new Query('/ppp/secret/print'))->where('name', $username)
            )->read();

            if (empty($existing[0])) {
                return [
                    'ok' => false,
                    'message' => 'Secret PPPoE tidak ditemukan di RouterOS.',
                ];
            }

            $row = $existing[0];

            return [
                'ok' => true,
                'secret' => [
                    'id' => $row['.id'] ?? '',
                    'name' => $row['name'] ?? $username,
                    'password' => $row['password'] ?? '',
                    'profile' => $row['profile'] ?? null,
                    'service' => $row['service'] ?? null,
                    'comment' => $row['comment'] ?? null,
                    'disabled' => ($row['disabled'] ?? 'false') === 'true',
                ],
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, sessions?: array<int, array<string, mixed>>}
     */
    public function listPppActiveSessions(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ppp/active/print'))->read();

            $sessions = collect($rows)
                ->map(fn (array $row) => $this->mapPppActiveSession($row))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return [
                'ok' => true,
                'sessions' => $sessions,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'sessions' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function disconnectPppActiveSession(MikrotikRouter $router, string $sessionId): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ppp/active/print'))->read();
            $existing = collect($rows)->first(
                fn (array $row) => (string) ($row['.id'] ?? '') === (string) $sessionId
            );

            if (! $existing) {
                return ['ok' => false, 'message' => 'Sesi aktif tidak ditemukan di RouterOS.'];
            }

            $name = (string) ($existing['name'] ?? $sessionId);

            $client->query(
                (new Query('/ppp/active/remove'))->equal('.id', $existing['.id'])
            )->read();

            return [
                'ok' => true,
                'message' => 'Sesi PPPoE "'.$name.'" berhasil diputus.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array{ok: bool, message?: string, sessions?: array<int, array<string, mixed>>}
     */
    public function listHotspotActiveSessions(MikrotikRouter $router): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ip/hotspot/active/print'))->read();

            $userProfiles = [];
            try {
                $users = $client->query(new Query('/ip/hotspot/user/print'))->read();
                $userProfiles = collect($users)
                    ->mapWithKeys(function (array $row) {
                        $name = strtolower((string) ($row['name'] ?? ''));

                        return $name !== ''
                            ? [$name => [
                                'profile' => $row['profile'] ?? null,
                                'comment' => $row['comment'] ?? null,
                            ]]
                            : [];
                    })
                    ->all();
            } catch (Throwable) {
                // hotspot user list optional for enrichment
            }

            $sessions = collect($rows)
                ->map(function (array $row) use ($userProfiles) {
                    $mapped = $this->mapHotspotActiveSession($row);
                    $key = strtolower((string) ($mapped['user'] ?? ''));
                    $user = $userProfiles[$key] ?? null;

                    return [
                        ...$mapped,
                        'profile' => $user['profile'] ?? null,
                        'user_comment' => $user['comment'] ?? null,
                        'user_registered' => $user !== null,
                    ];
                })
                ->sortBy('user', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            return [
                'ok' => true,
                'sessions' => $sessions,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $this->friendlyError($e),
                'sessions' => [],
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function disconnectHotspotActiveSession(MikrotikRouter $router, string $sessionId): array
    {
        try {
            $client = $this->makeClient($router);
            $rows = $client->query(new Query('/ip/hotspot/active/print'))->read();
            $existing = collect($rows)->first(
                fn (array $row) => (string) ($row['.id'] ?? '') === (string) $sessionId
            );

            if (! $existing) {
                return ['ok' => false, 'message' => 'Sesi hotspot aktif tidak ditemukan di RouterOS.'];
            }

            $name = (string) ($existing['user'] ?? $existing['name'] ?? $sessionId);

            $client->query(
                (new Query('/ip/hotspot/active/remove'))->equal('.id', $existing['.id'])
            )->read();

            return [
                'ok' => true,
                'message' => 'Sesi hotspot "'.$name.'" berhasil diputus.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e)];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPppActiveSession(array $row): array
    {
        return [
            'id' => $row['.id'] ?? '',
            'name' => $row['name'] ?? '',
            'service' => $row['service'] ?? null,
            'caller_id' => $row['caller-id'] ?? null,
            'address' => $row['address'] ?? null,
            'uptime' => $row['uptime'] ?? null,
            'encoding' => $row['encoding'] ?? null,
            'session_id' => $row['session-id'] ?? null,
            'radius' => ($row['radius'] ?? 'false') === 'true',
            'comment' => $row['comment'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapHotspotActiveSession(array $row): array
    {
        return [
            'id' => $row['.id'] ?? '',
            'user' => $row['user'] ?? $row['name'] ?? '',
            'address' => $row['address'] ?? null,
            'mac_address' => $row['mac-address'] ?? null,
            'login_by' => $row['login-by'] ?? null,
            'uptime' => $row['uptime'] ?? null,
            'idle_time' => $row['idle-time'] ?? null,
            'session_time_left' => $row['session-time-left'] ?? null,
            'bytes_in' => isset($row['bytes-in']) ? (int) $row['bytes-in'] : null,
            'bytes_out' => isset($row['bytes-out']) ? (int) $row['bytes-out'] : null,
            'server' => $row['server'] ?? null,
            'radius' => ($row['radius'] ?? 'false') === 'true',
            'comment' => $row['comment'] ?? null,
        ];
    }

    private function disconnectPppActive(Client $client, string $username): void
    {
        try {
            $active = $client->query(
                (new Query('/ppp/active/print'))->where('name', $username)
            )->read();

            if (! empty($active[0]['.id'])) {
                $client->query(
                    (new Query('/ppp/active/remove'))->equal('.id', $active[0]['.id'])
                )->read();
            }
        } catch (Throwable) {
            // ignore active session errors
        }
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function mapHotspotUserProfile(array $row): array
    {
        return [
            'id' => $row['.id'] ?? '',
            'name' => $row['name'] ?? '',
            'rate_limit' => $row['rate-limit'] ?? null,
            'session_timeout' => $row['session-timeout'] ?? null,
            'idle_timeout' => $row['idle-timeout'] ?? null,
            'shared_users' => $row['shared-users'] ?? null,
            'address_list' => $row['address-list'] ?? null,
        ];
    }

    /**
     * @param  array{rate_limit?: ?string, session_timeout?: ?string, idle_timeout?: ?string, shared_users?: ?int|string, address_list?: ?string}  $data
     */
    private function applyHotspotUserProfileFields(Query $query, array $data): void
    {
        $fields = [
            'rate-limit' => $data['rate_limit'] ?? null,
            'session-timeout' => $data['session_timeout'] ?? null,
            'idle-timeout' => $data['idle_timeout'] ?? null,
            'shared-users' => isset($data['shared_users']) && $data['shared_users'] !== ''
                ? (string) $data['shared_users']
                : null,
            'address-list' => $data['address_list'] ?? null,
        ];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $query->equal($key, (string) $value);
        }
    }

    private function mapPppProfile(array $row): array
    {
        $queueType = $row['queue-type'] ?? null;
        [$queueTypeRx, $queueTypeTx] = $this->splitQueueType($queueType);

        return [
            'id' => $row['.id'] ?? '',
            'name' => $row['name'] ?? '',
            'local_address' => $row['local-address'] ?? null,
            'remote_address' => $row['remote-address'] ?? null,
            'rate_limit' => $row['rate-limit'] ?? null,
            'queue_type' => $queueType,
            'queue_type_rx' => $queueTypeRx,
            'queue_type_tx' => $queueTypeTx,
            'only_one' => $row['only-one'] ?? null,
            'dns_server' => $row['dns-server'] ?? null,
            'comment' => $row['comment'] ?? null,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitQueueType(?string $queueType): array
    {
        if ($queueType === null || $queueType === '') {
            return [null, null];
        }

        if (! str_contains($queueType, '/')) {
            return [$queueType, $queueType];
        }

        [$rx, $tx] = array_pad(explode('/', $queueType, 2), 2, null);

        return [$rx !== '' ? $rx : null, $tx !== '' ? $tx : null];
    }

    private function combineQueueType(?string $rx, ?string $tx): ?string
    {
        $rx = is_string($rx) && trim($rx) !== '' ? trim($rx) : null;
        $tx = is_string($tx) && trim($tx) !== '' ? trim($tx) : null;

        if ($rx === null && $tx === null) {
            return null;
        }

        // Always use rx/tx so a TX-only choice is not collapsed incorrectly
        // on RouterOS 7.19+ (first = rx/upload, second = tx/download).
        return ($rx ?? $tx).'/'.($tx ?? $rx);
    }

    /**
     * @param  array{queue_type_rx?: ?string, queue_type_tx?: ?string}  $data
     */
    private function ensurePppProfileQueueType(Client $client, string $profileName, array $data): void
    {
        $queueType = $this->combineQueueType(
            $data['queue_type_rx'] ?? null,
            $data['queue_type_tx'] ?? null,
        );

        if ($queueType === null) {
            return;
        }

        $created = $client->query(
            (new Query('/ppp/profile/print'))->where('name', $profileName)
        )->read();

        if (empty($created[0]['.id'])) {
            return;
        }

        $client->query(
            (new Query('/ppp/profile/set'))
                ->equal('.id', $created[0]['.id'])
                ->equal('queue-type', $queueType)
        )->read();
    }

    /**
     * @param  array{rate_limit?: ?string, local_address?: ?string, remote_address?: ?string, queue_type_rx?: ?string, queue_type_tx?: ?string, only_one?: ?string, dns_server?: ?string, comment?: ?string}  $data
     */
    private function applyPppProfileFields(Query $query, array $data): void
    {
        $fields = [
            'rate-limit' => $data['rate_limit'] ?? null,
            'local-address' => $data['local_address'] ?? null,
            'remote-address' => $data['remote_address'] ?? null,
            'queue-type' => $this->combineQueueType(
                $data['queue_type_rx'] ?? null,
                $data['queue_type_tx'] ?? null,
            ),
            'only-one' => $data['only_one'] ?? null,
            'dns-server' => $data['dns_server'] ?? null,
            'comment' => $data['comment'] ?? null,
        ];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $query->equal($key, (string) $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Nama interface yang dipakai default route (0.0.0.0/0).
     *
     * @return array<int, string>
     */
    private function defaultRouteInterfaceNames($client): array
    {
        try {
            $routes = $client->query((new Query('/ip/route/print'))->equal('.propertyset', 'dst-address,immediate-gw,gateway,vrf-interface,gateway-interface'))->read();
        } catch (Throwable) {
            return [];
        }

        $names = [];
        foreach ($routes as $route) {
            $dst = (string) ($route['dst-address'] ?? '');
            if ($dst !== '0.0.0.0/0') {
                continue;
            }

            foreach (['immediate-gw', 'gateway'] as $field) {
                $gw = (string) ($route[$field] ?? '');
                if ($gw === '') {
                    continue;
                }
                if (str_contains($gw, '%')) {
                    $names[] = explode('%', $gw, 2)[1];
                } elseif (! preg_match('/^\d/', $gw)) {
                    $names[] = $gw;
                }
            }

            foreach (['vrf-interface', 'gateway-interface'] as $field) {
                $iface = trim((string) ($route[$field] ?? ''));
                if ($iface !== '') {
                    $names[] = $iface;
                }
            }
        }

        return array_values(array_unique(array_filter($names)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $interfaces
     * @param  array<int, string>  $wanNames
     * @return array<int, array<string, mixed>>
     */
    private function enrichPhysicalInterfaces(array $interfaces, array $wanNames): array
    {
        $wanLookup = array_fill_keys(array_map('strtolower', $wanNames), true);

        $enriched = array_map(function (array $iface) use ($wanLookup) {
            $name = (string) ($iface['name'] ?? '');
            $comment = strtolower((string) ($iface['comment'] ?? ''));
            $haystack = strtolower($name.' '.$comment);
            $hintWan = str_contains($haystack, 'wan')
                || str_contains($haystack, 'isp')
                || str_contains($haystack, 'internet')
                || str_contains($haystack, 'uplink');

            $iface['is_wan'] = isset($wanLookup[strtolower($name)]) || $hintWan;

            return $iface;
        }, $interfaces);

        usort($enriched, function (array $a, array $b) {
            $score = static function (array $iface): int {
                $score = 0;
                if (! empty($iface['is_wan'])) {
                    $score += 4;
                }
                if (! empty($iface['running'])) {
                    $score += 2;
                }

                return $score;
            };

            $diff = $score($b) <=> $score($a);
            if ($diff !== 0) {
                return $diff;
            }

            return strnatcasecmp((string) $a['name'], (string) $b['name']);
        });

        return array_values($enriched);
    }

    private function first(Client $client, string $path): array
    {
        $rows = $client->query(new Query($path))->read();

        return $rows[0] ?? [];
    }

    private function friendlyError(Throwable $e): string
    {
        return match (true) {
            $e instanceof BadCredentialsException => 'Username atau password RouterOS salah.',
            $e instanceof ConnectException => 'Tidak bisa terhubung ke router. Cek IP, port API 8728, dan firewall.',
            $e instanceof ConfigException => 'Konfigurasi koneksi tidak valid.',
            $e instanceof ClientException, $e instanceof QueryException => 'Gagal membaca data dari RouterOS: '.$e->getMessage(),
            default => 'Gagal koneksi RouterOS: '.$e->getMessage(),
        };
    }
}
