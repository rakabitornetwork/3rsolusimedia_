<?php

namespace App\Services;

use App\Models\MikrotikRouter;
use App\Models\PppoeCustomer;
use App\Models\PppoeDailyUsage;
use App\Models\PppoeMonthlyUsage;
use App\Models\PppoeTrafficCursor;
use App\Support\AppSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class PppoeMonthlyUsageService
{
    public function __construct(private readonly MikrotikApiService $api) {}

    /**
     * Baca counter interface PPPoE di setiap router aktif dan tambahkan selisihnya
     * ke pemakaian bulan berjalan.
     *
     * @return array{routers: int, customers: int, failed_routers: int}
     */
    public function collect(): array
    {
        $summary = [
            'routers' => 0,
            'customers' => 0,
            'failed_routers' => 0,
        ];

        $routers = MikrotikRouter::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($routers as $router) {
            $map = $this->api->pppoeInterfaceBytesMap($router);
            if ($map === []) {
                $summary['failed_routers']++;

                continue;
            }

            $summary['routers']++;
            $summary['customers'] += $this->applyRouterMap($router, $map, now());
        }

        return $summary;
    }

    /**
     * @param  array<string, array{rx_byte: int, tx_byte: int}>  $bytesByUsername
     */
    public function applyRouterMap(MikrotikRouter $router, array $bytesByUsername, CarbonInterface $at): int
    {
        if ($bytesByUsername === []) {
            return 0;
        }

        $usernames = array_keys($bytesByUsername);
        $customers = PppoeCustomer::query()
            ->where('mikrotik_router_id', $router->id)
            ->whereIn(DB::raw('LOWER(username)'), $usernames)
            ->get();

        $updated = 0;
        foreach ($customers as $customer) {
            $key = strtolower(trim((string) $customer->username));
            $sample = $bytesByUsername[$key] ?? null;
            if (! is_array($sample)) {
                continue;
            }

            $this->applySample(
                $customer,
                (int) ($sample['rx_byte'] ?? 0),
                (int) ($sample['tx_byte'] ?? 0),
                $at,
            );
            $updated++;
        }

        return $updated;
    }

    /**
     * Counter MikroTik pada interface `<pppoe-user>`:
     * rx-byte = upload pelanggan, tx-byte = download pelanggan.
     * Pemakaian disimpan sebagai RX (download) dan TX (upload).
     * Pembacaan pertama hanya menjadi patokan, supaya sisa sesi yang sudah
     * berjalan tidak terhitung sebagai pemakaian bulan ini.
     */
    public function applySample(PppoeCustomer $customer, int $interfaceRx, int $interfaceTx, CarbonInterface $at): void
    {
        $download = max(0, $interfaceTx);
        $upload = max(0, $interfaceRx);
        $local = $at->copy()->timezone($this->timezone());
        $period = $local->format('Y-m');
        $usageDate = $local->toDateString();

        DB::transaction(function () use ($customer, $download, $upload, $at, $period, $usageDate) {
            $cursor = PppoeTrafficCursor::query()
                ->where('pppoe_customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            $deltaRx = 0;
            $deltaTx = 0;
            if ($cursor) {
                $deltaRx = $this->delta((int) $cursor->last_rx_byte, $download);
                $deltaTx = $this->delta((int) $cursor->last_tx_byte, $upload);
            }

            $usage = PppoeMonthlyUsage::query()->firstOrCreate(
                [
                    'pppoe_customer_id' => $customer->id,
                    'period' => $period,
                ],
                [
                    'rx_bytes' => 0,
                    'tx_bytes' => 0,
                ],
            );

            if ($deltaRx > 0 || $deltaTx > 0) {
                $usage->rx_bytes = (int) $usage->rx_bytes + $deltaRx;
                $usage->tx_bytes = (int) $usage->tx_bytes + $deltaTx;
                $usage->save();

                $daily = PppoeDailyUsage::query()->firstOrCreate(
                    [
                        'pppoe_customer_id' => $customer->id,
                        'usage_date' => $usageDate,
                    ],
                    [
                        'rx_bytes' => 0,
                        'tx_bytes' => 0,
                    ],
                );
                $daily->rx_bytes = (int) $daily->rx_bytes + $deltaRx;
                $daily->tx_bytes = (int) $daily->tx_bytes + $deltaTx;
                $daily->save();
            }

            if (! $cursor) {
                PppoeTrafficCursor::query()->create([
                    'pppoe_customer_id' => $customer->id,
                    'last_rx_byte' => $download,
                    'last_tx_byte' => $upload,
                    'sampled_at' => $at,
                ]);

                return;
            }

            $cursor->forceFill([
                'last_rx_byte' => $download,
                'last_tx_byte' => $upload,
                'sampled_at' => $at,
            ])->save();
        });
    }

    /**
     * @return array{
     *     period: string,
     *     period_label: string,
     *     rx_bytes: int,
     *     tx_bytes: int,
     *     total_bytes: int,
     *     rx_label: string,
     *     tx_label: string,
     *     total_label: string,
     *     has_sample: bool,
     *     sampled_at: ?string,
     *     reset_label: string
     * }
     */
    public function present(?PppoeMonthlyUsage $usage, ?PppoeTrafficCursor $cursor, ?CarbonInterface $at = null): array
    {
        $at = ($at ?? now())->copy()->timezone($this->timezone());
        $period = $at->format('Y-m');
        if ($usage && $usage->period !== $period) {
            $usage = null;
        }

        $rx = (int) ($usage->rx_bytes ?? 0);
        $tx = (int) ($usage->tx_bytes ?? 0);

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'rx_bytes' => $rx,
            'tx_bytes' => $tx,
            'total_bytes' => $rx + $tx,
            'rx_label' => $this->formatBytes($rx),
            'tx_label' => $this->formatBytes($tx),
            'total_label' => $this->formatBytes($rx + $tx),
            'has_sample' => $cursor !== null,
            'sampled_at' => $cursor?->sampled_at?->copy()->timezone($this->timezone())->format('Y-m-d H:i'),
            'reset_label' => 'Reset tiap tanggal 1',
        ];
    }

    /**
     * Sepuluh pelanggan dengan total RX+TX terbesar.
     * Harian dan mingguan dijumlah dari catatan harian. Bulanan memakai total bulan berjalan.
     * Minggu dihitung Senin–Minggu.
     *
     * @return array{
     *     daily: array{key: string, label: string, range_label: string, rows: list<array<string, mixed>>},
     *     weekly: array{key: string, label: string, range_label: string, rows: list<array<string, mixed>>},
     *     monthly: array{key: string, label: string, range_label: string, rows: list<array<string, mixed>>}
     * }
     */
    public function topTen(?int $agentId = null, ?CarbonInterface $at = null): array
    {
        $now = ($at ?? now())->copy()->timezone($this->timezone());
        $today = $now->toDateString();
        $weekStart = $now->copy()->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6);
        $period = $now->format('Y-m');

        return [
            'daily' => [
                'key' => 'daily',
                'label' => 'Harian',
                'range_label' => $this->shortDate($now),
                'rows' => $this->topFromDaily($agentId, $today, $today),
            ],
            'weekly' => [
                'key' => 'weekly',
                'label' => 'Mingguan',
                'range_label' => $this->rangeLabel($weekStart, $weekEnd),
                'rows' => $this->topFromDaily($agentId, $weekStart->toDateString(), $weekEnd->toDateString()),
            ],
            'monthly' => [
                'key' => 'monthly',
                'label' => 'Bulanan',
                'range_label' => $this->periodLabel($period),
                'rows' => $this->topFromMonthly($agentId, $period),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topFromDaily(?int $agentId, string $from, string $to): array
    {
        $query = DB::table('pppoe_daily_usages')
            ->join('pppoe_customers', 'pppoe_customers.id', '=', 'pppoe_daily_usages.pppoe_customer_id')
            ->where('pppoe_daily_usages.usage_date', '>=', $from)
            ->where('pppoe_daily_usages.usage_date', '<', \Carbon\Carbon::parse($to)->addDay()->toDateString());

        if ($agentId) {
            $query->where('pppoe_customers.agent_id', $agentId);
        }

        $rows = $query
            ->groupBy('pppoe_customers.id', 'pppoe_customers.name', 'pppoe_customers.username')
            ->havingRaw('(SUM(pppoe_daily_usages.rx_bytes) + SUM(pppoe_daily_usages.tx_bytes)) > 0')
            ->orderByRaw('(SUM(pppoe_daily_usages.rx_bytes) + SUM(pppoe_daily_usages.tx_bytes)) desc')
            ->orderBy('pppoe_customers.id')
            ->limit(10)
            ->get([
                'pppoe_customers.id',
                'pppoe_customers.name',
                'pppoe_customers.username',
                DB::raw('SUM(pppoe_daily_usages.rx_bytes) as rx_bytes'),
                DB::raw('SUM(pppoe_daily_usages.tx_bytes) as tx_bytes'),
            ]);

        return $this->mapTopRows($rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topFromMonthly(?int $agentId, string $period): array
    {
        $query = DB::table('pppoe_monthly_usages')
            ->join('pppoe_customers', 'pppoe_customers.id', '=', 'pppoe_monthly_usages.pppoe_customer_id')
            ->where('pppoe_monthly_usages.period', $period);

        if ($agentId) {
            $query->where('pppoe_customers.agent_id', $agentId);
        }

        $rows = $query
            ->whereRaw('(pppoe_monthly_usages.rx_bytes + pppoe_monthly_usages.tx_bytes) > 0')
            ->orderByRaw('(pppoe_monthly_usages.rx_bytes + pppoe_monthly_usages.tx_bytes) desc')
            ->orderBy('pppoe_customers.id')
            ->limit(10)
            ->get([
                'pppoe_customers.id',
                'pppoe_customers.name',
                'pppoe_customers.username',
                'pppoe_monthly_usages.rx_bytes',
                'pppoe_monthly_usages.tx_bytes',
            ]);

        return $this->mapTopRows($rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapTopRows($rows): array
    {
        return $rows->values()->map(function (object $row, int $index) {
            $rx = (int) $row->rx_bytes;
            $tx = (int) $row->tx_bytes;

            return [
                'rank' => $index + 1,
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'username' => (string) $row->username,
                'rx_bytes' => $rx,
                'tx_bytes' => $tx,
                'total_bytes' => $rx + $tx,
                'rx_label' => $this->formatBytes($rx),
                'tx_label' => $this->formatBytes($tx),
                'total_label' => $this->formatBytes($rx + $tx),
            ];
        })->all();
    }

    private function shortDate(CarbonInterface $date): string
    {
        return $date->day.' '.$this->monthName((int) $date->month).' '.$date->year;
    }

    private function rangeLabel(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->year === $end->year && $start->month === $end->month) {
            return $start->day.'–'.$end->day.' '.$this->monthName((int) $end->month).' '.$end->year;
        }

        return $this->shortDate($start).' – '.$this->shortDate($end);
    }

    private function monthName(int $month): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[$month] ?? (string) $month;
    }

    public function formatBytes(int $bytes): string
    {
        $value = (float) max(0, $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        $decimals = $index === 0 ? 0 : 2;
        $formatted = number_format($value, $decimals, ',', '.');
        if ($index > 0) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted.' '.$units[$index];
    }

    private function delta(int $previous, int $current): int
    {
        if ($current >= $previous) {
            return $current - $previous;
        }

        return $current;
    }

    private function periodLabel(string $period): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        [$year, $month] = array_pad(explode('-', $period, 2), 2, '1');
        $name = $months[(int) $month] ?? $period;

        return $name.' '.$year;
    }

    private function timezone(): string
    {
        try {
            $configured = (string) (AppSettings::get('app_timezone', '') ?: '');
            if ($configured !== '' && in_array($configured, timezone_identifiers_list(), true)) {
                return $configured;
            }
        } catch (Throwable) {
            // Pengaturan belum siap saat migrasi atau tes.
        }

        return (string) (config('app.timezone') ?: 'Asia/Jakarta');
    }
}
