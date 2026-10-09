<?php

namespace App\Services\Vpn;

use App\Models\PppoeCustomer;
use App\Models\VpnPortForward;
use App\Models\VpnRouter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class VpnAccessPlan
{
    public const SERIES_MIN = 120;

    public const SERIES_MAX = 640;

    public const POOL_NETWORK = '192.168.172.0';

    public const POOL_START = 15;

    public const POOL_END = 250;

    public const LOCAL_ADDRESS = '192.168.172.254';

    public const CHR_PROFILE = 'default-encryption';

    /**
     * Port tujuan di router pelanggan, dan dua digit terakhir port publik.
     *
     * @var array<int, array{suffix: int, label: string}>
     */
    public const STANDARD = [
        22 => ['suffix' => 22, 'label' => 'SSH'],
        80 => ['suffix' => 80, 'label' => 'HTTP'],
        8291 => ['suffix' => 91, 'label' => 'Winbox'],
        8728 => ['suffix' => 28, 'label' => 'API'],
    ];

    public function ensure(PppoeCustomer $customer): void
    {
        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            return;
        }

        DB::transaction(function () use ($customer) {
            $locked = PppoeCustomer::query()->whereKey($customer->id)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }

            $legacy = VpnPortForward::query()
                ->where('pppoe_customer_id', $locked->id)
                ->whereNull('vpn_router_id')
                ->get();

            if (! $locked->vpn_remote_address && $legacy->isEmpty()) {
                return;
            }

            $router = VpnRouter::query()
                ->where('pppoe_customer_id', $locked->id)
                ->where('included', true)
                ->first();

            if (! $router) {
                $address = $locked->vpn_remote_address ?: $this->nextAddress();
                $series = $locked->vpn_port_series ?: $this->nextSeries();
                $locked->vpn_remote_address = null;
                $locked->vpn_port_series = null;
                $locked->save();

                $router = VpnRouter::query()->create([
                    'pppoe_customer_id' => $locked->id,
                    'name' => $this->legacyRouterName($locked),
                    'vpn_remote_address' => $address,
                    'vpn_port_series' => $series,
                    'billing_day' => (int) ($locked->billing_day ?: now()->day),
                    'included' => true,
                    'service_until' => $locked->due_date?->toDateString(),
                ]);
            } else {
                $locked->vpn_remote_address = null;
                $locked->vpn_port_series = null;
                $locked->save();
            }

            if ($legacy->isNotEmpty()) {
                VpnPortForward::query()
                    ->where('pppoe_customer_id', $locked->id)
                    ->whereNull('vpn_router_id')
                    ->update(['vpn_router_id' => $router->id]);
            }
        });
    }

    public function addCustom(PppoeCustomer $customer, int $dstPort, ?string $note = null, ?VpnRouter $router = null): VpnPortForward
    {
        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            throw new InvalidArgumentException('Port khusus hanya untuk pelanggan VPN.');
        }

        if ($dstPort < 1 || $dstPort > 65535) {
            throw new InvalidArgumentException('Port tujuan harus antara 1 dan 65535.');
        }

        if (isset(self::STANDARD[$dstPort])) {
            throw new InvalidArgumentException('Port '.$dstPort.' sudah termasuk port bawaan pelanggan.');
        }

        $this->ensure($customer);
        $customer->refresh();

        $exists = VpnPortForward::query()
            ->where('pppoe_customer_id', $customer->id)
            ->when(
                $router,
                fn ($query) => $query->where('vpn_router_id', $router->id),
                fn ($query) => $query->whereNull('vpn_router_id'),
            )
            ->where('dst_port', $dstPort)
            ->exists();
        if ($exists) {
            throw new InvalidArgumentException('Port tujuan '.$dstPort.' sudah ada untuk router ini.');
        }

        $label = trim((string) $note);
        if ($label === '') {
            $label = 'Custom '.$dstPort;
        }

        $series = $router ? (int) $router->vpn_port_series : (int) $customer->vpn_port_series;
        if ($series < 1) {
            throw new InvalidArgumentException('Router ini belum punya seri port.');
        }

        return VpnPortForward::query()->create([
            'pppoe_customer_id' => $customer->id,
            'vpn_router_id' => $router?->id,
            'public_port' => $this->nextCustomPublicPort($series),
            'dst_port' => $dstPort,
            'kind' => VpnPortForward::KIND_CUSTOM,
            'label' => mb_substr($label, 0, 80),
        ]);
    }

    public function addRouter(PppoeCustomer $customer, string $name): VpnRouter
    {
        if ($customer->pppService() !== PppoeCustomer::SERVICE_L2TP) {
            throw new InvalidArgumentException('Router tambahan hanya untuk pelanggan VPN.');
        }

        $name = trim($name);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/', $name) !== 1) {
            throw new InvalidArgumentException('Nama router hanya huruf, angka, titik, garis bawah, atau strip, 2–32 karakter.');
        }

        return DB::transaction(function () use ($customer, $name) {
            $this->ensure($customer);
            $locked = PppoeCustomer::query()->whereKey($customer->id)->lockForUpdate()->first();
            if (! $locked) {
                throw new RuntimeException('Pelanggan tidak ditemukan.');
            }

            $count = VpnRouter::query()->where('pppoe_customer_id', $locked->id)->lockForUpdate()->count();
            if ($count >= 3) {
                throw new InvalidArgumentException('Satu akun paling banyak 3 router.');
            }

            $taken = VpnRouter::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists()
                || PppoeCustomer::query()
                    ->where('id', '!=', $locked->id)
                    ->whereRaw('LOWER(username) = ?', [strtolower($name)])
                    ->exists();
            if ($taken) {
                throw new InvalidArgumentException('Nama router '.$name.' sudah dipakai.');
            }

            $router = VpnRouter::query()->create([
                'pppoe_customer_id' => $locked->id,
                'name' => $name,
                'vpn_remote_address' => $this->nextAddress(),
                'vpn_port_series' => $this->nextSeries(),
                'billing_day' => (int) now()->day,
            ]);

            foreach (self::STANDARD as $dst => $meta) {
                VpnPortForward::query()->create([
                    'pppoe_customer_id' => $locked->id,
                    'vpn_router_id' => $router->id,
                    'public_port' => $this->publicPort((int) $router->vpn_port_series, $dst),
                    'dst_port' => $dst,
                    'kind' => VpnPortForward::KIND_STANDARD,
                    'label' => $meta['label'],
                ]);
            }

            return $router->fresh('portForwards');
        });
    }

    public function publicPort(int $series, int $dstPort): int
    {
        $suffix = self::STANDARD[$dstPort]['suffix'] ?? null;
        if ($suffix === null) {
            throw new InvalidArgumentException('Port bawaan tidak dikenal.');
        }

        return $series * 100 + $suffix;
    }

    public function nextSeries(): int
    {
        $lastCustomer = PppoeCustomer::query()->whereNotNull('vpn_port_series')->max('vpn_port_series');
        $lastRouter = VpnRouter::query()->whereNotNull('vpn_port_series')->max('vpn_port_series');
        $last = max($lastCustomer === null ? 0 : (int) $lastCustomer, $lastRouter === null ? 0 : (int) $lastRouter);
        $series = $last === 0
            ? random_int(self::SERIES_MIN, self::SERIES_MAX)
            : max($last + 1, self::SERIES_MIN);

        while (
            $series <= self::SERIES_MAX
            && (
                PppoeCustomer::query()->where('vpn_port_series', $series)->exists()
                || VpnRouter::query()->where('vpn_port_series', $series)->exists()
            )
        ) {
            $series++;
        }

        if ($series < self::SERIES_MIN || $series > self::SERIES_MAX) {
            throw new RuntimeException('Rentang port VPN sudah habis.');
        }

        return $series;
    }

    public function nextCustomPublicPort(int $series): int
    {
        $reserved = [];
        foreach (self::STANDARD as $dst => $meta) {
            $reserved[$this->publicPort($series, $dst)] = true;
        }

        $used = VpnPortForward::query()
            ->whereBetween('public_port', [$series * 100, $series * 100 + 99])
            ->pluck('public_port')
            ->all();
        foreach ($used as $port) {
            $reserved[(int) $port] = true;
        }

        for ($port = $series * 100 + 1; $port <= $series * 100 + 99; $port++) {
            if (! isset($reserved[$port])) {
                return $port;
            }
        }

        throw new RuntimeException('Blok port pelanggan ini sudah penuh.');
    }

    private function nextAddress(): string
    {
        $used = array_merge(
            PppoeCustomer::query()->whereNotNull('vpn_remote_address')->pluck('vpn_remote_address')->all(),
            VpnRouter::query()->whereNotNull('vpn_remote_address')->pluck('vpn_remote_address')->all(),
        );
        $taken = array_fill_keys($used, true);
        $base = ip2long(self::POOL_NETWORK);

        for ($host = self::POOL_START; $host <= self::POOL_END; $host++) {
            $address = long2ip($base + $host);
            if (! isset($taken[$address])) {
                return $address;
            }
        }

        throw new RuntimeException('Alamat IP VPN sudah habis.');
    }

    private function legacyRouterName(PppoeCustomer $customer): string
    {
        $name = trim((string) $customer->username);
        $free = preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/', $name) === 1
            && ! VpnRouter::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();

        if ($free) {
            return $name;
        }

        $fallback = 'router-'.$customer->id;
        if (! VpnRouter::query()->whereRaw('LOWER(name) = ?', [strtolower($fallback)])->exists()) {
            return $fallback;
        }

        return 'router-'.$customer->id.'-'.now()->format('His');
    }
}
