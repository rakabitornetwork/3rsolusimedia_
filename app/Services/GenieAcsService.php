<?php

namespace App\Services;

use App\Support\AppSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class GenieAcsService
{
    public function isConfigured(): bool
    {
        return trim((string) AppSettings::get('genieacs_nbi_url', '')) !== '';
    }

    public function isEnabled(): bool
    {
        return AppSettings::bool('genieacs_enabled', false) && $this->isConfigured();
    }

    /**
     * @return array{ok: bool, message: string, latency_ms?: int}
     */
    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'URL NBI GenieACS belum dikonfigurasi.',
            ];
        }

        $started = microtime(true);

        try {
            $response = $this->client()
                ->timeout(8)
                ->get('/devices/', [
                    'limit' => 1,
                    'projection' => '_id',
                ]);

            $latency = (int) round((microtime(true) - $started) * 1000);

            if ($response->successful()) {
                $total = $response->header('Total');

                return [
                    'ok' => true,
                    'message' => 'Koneksi ke GenieACS NBI berhasil.'
                        .($total !== null && $total !== '' ? " Total perangkat: {$total}." : ''),
                    'latency_ms' => $latency,
                ];
            }

            return [
                'ok' => false,
                'message' => 'GenieACS merespons HTTP '.$response->status().'.',
                'latency_ms' => $latency,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal terhubung: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, devices?: array<int, array<string, mixed>>, total?: int}
     */
    public function listDevices(?string $search = null, int $limit = 100, int $skip = 0): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'URL NBI GenieACS belum dikonfigurasi.',
            ];
        }

        $query = [];
        if ($search) {
                $query = [
                '$or' => [
                    ['_id' => ['$regex' => $search, '$options' => 'i']],
                    ['_tags' => $search],
                    ['_deviceId._SerialNumber' => ['$regex' => $search, '$options' => 'i']],
                    ['_deviceId._Manufacturer' => ['$regex' => $search, '$options' => 'i']],
                    ['_deviceId._ProductClass' => ['$regex' => $search, '$options' => 'i']],
                    ['InternetGatewayDevice.DeviceInfo.SerialNumber._value' => ['$regex' => $search, '$options' => 'i']],
                    ['Device.DeviceInfo.SerialNumber._value' => ['$regex' => $search, '$options' => 'i']],
                    ['InternetGatewayDevice.DeviceInfo.Manufacturer._value' => ['$regex' => $search, '$options' => 'i']],
                    ['Device.DeviceInfo.Manufacturer._value' => ['$regex' => $search, '$options' => 'i']],
                    ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID._value' => ['$regex' => $search, '$options' => 'i']],
                ],
            ];
        }

        try {
            $params = [
                'limit' => max(1, min(500, $limit)),
                'skip' => max(0, $skip),
                'projection' => implode(',', [
                    '_id',
                    '_lastInform',
                    '_tags',
                    '_deviceId',
                    'VirtualParameters.gettemp',
                    'VirtualParameters.RXPower',
                    'VirtualParameters.WlanPassword',
                    'InternetGatewayDevice.DeviceInfo.Manufacturer',
                    'InternetGatewayDevice.DeviceInfo.ModelName',
                    'InternetGatewayDevice.DeviceInfo.SerialNumber',
                    'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
                    'InternetGatewayDevice.DeviceInfo.HardwareVersion',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
                    'InternetGatewayDevice.WANDevice.1.X_CU_WANEPONInterfaceConfig.OpticalTransceiver.Temperature',
                    'InternetGatewayDevice.WANDevice.1.X_CU_WANEPONInterfaceConfig.OpticalTransceiver.RXPower',
                    'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.TransceiverTemperature',
                    'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.RXPower',
                    'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TransceiverTemperature',
                    'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.RXPower',
                    'Device.DeviceInfo.Manufacturer',
                    'Device.DeviceInfo.ModelName',
                    'Device.DeviceInfo.SerialNumber',
                    'Device.DeviceInfo.SoftwareVersion',
                    'Device.DeviceInfo.HardwareVersion',
                    'Device.WiFi.SSID.1.SSID',
                    'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
                ]),
            ];

            if ($query !== []) {
                $params['query'] = json_encode($query, JSON_UNESCAPED_SLASHES);
            }

            $response = $this->client()->timeout(30)->get('/devices/', $params);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'Gagal mengambil daftar perangkat (HTTP '.$response->status().').',
                ];
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return [
                    'ok' => false,
                    'message' => 'Respons GenieACS tidak valid (bukan JSON array).',
                ];
            }

            $devices = collect($payload)
                ->filter(fn ($device) => is_array($device))
                ->map(fn (array $device) => $this->summarizeDevice($device))
                ->values()
                ->all();

            $totalHeader = $response->header('Total');
            $total = is_numeric($totalHeader) ? (int) $totalHeader : count($devices);

            return [
                'ok' => true,
                'devices' => $devices,
                'total' => $total,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal mengambil daftar perangkat: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, device?: array<string, mixed>}
     */
    public function getDevice(string $deviceId): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'URL NBI GenieACS belum dikonfigurasi.',
            ];
        }

        try {
            $response = $this->client()->timeout(20)->get('/devices/', [
                'query' => json_encode(['_id' => $deviceId], JSON_UNESCAPED_SLASHES),
            ]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'Gagal mengambil detail perangkat (HTTP '.$response->status().').',
                ];
            }

            $items = $response->json() ?: [];
            if (! is_array($items) || $items === []) {
                return [
                    'ok' => false,
                    'message' => 'Perangkat tidak ditemukan di GenieACS.',
                ];
            }

            $first = $items[0] ?? null;
            if (! is_array($first)) {
                return [
                    'ok' => false,
                    'message' => 'Perangkat tidak ditemukan di GenieACS.',
                ];
            }

            return [
                'ok' => true,
                'device' => $this->detailDevice($first),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal mengambil detail perangkat: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function summonDevice(string $deviceId): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'URL NBI GenieACS belum dikonfigurasi.',
            ];
        }

        try {
            $response = $this->client()
                ->timeout(25)
                ->withQueryParameters(['connection_request' => ''])
                ->post('/devices/'.rawurlencode($deviceId).'/tasks', [
                    'name' => 'refreshObject',
                    'objectName' => '',
                ]);

            if (in_array($response->status(), [200, 202], true)) {
                return [
                    'ok' => true,
                    'message' => $response->status() === 200
                        ? 'Perangkat berhasil di-summon dan di-refresh.'
                        : 'Task refresh diantrikan. Perangkat akan diproses pada inform berikutnya.',
                ];
            }

            return [
                'ok' => false,
                'message' => 'Gagal summon perangkat (HTTP '.$response->status().'). '.$response->body(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal summon perangkat: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{ok: bool, message?: string, count?: int}
     */
    public function countFaults(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'GenieACS belum dikonfigurasi.'];
        }

        try {
            $response = $this->client()->timeout(10)->get('/faults/', [
                'projection' => '_id',
                'limit' => 1,
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'message' => 'Gagal mengambil faults.'];
            }

            $totalHeader = $response->header('Total');
            if (is_numeric($totalHeader)) {
                return [
                    'ok' => true,
                    'count' => (int) $totalHeader,
                ];
            }

            return [
                'ok' => true,
                'count' => count($response->json() ?: []),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Hitung perangkat online dari _lastInform (projection ringan).
     */
    public function countOnlineDevices(int $limit = 500): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        try {
            $response = $this->client()->timeout(20)->get('/devices/', [
                'limit' => max(1, min(1000, $limit)),
                'projection' => '_lastInform',
            ]);

            if (! $response->successful()) {
                return 0;
            }

            return collect($response->json() ?: [])
                ->filter(fn ($device) => is_array($device) && $this->isRecentlyInformed($device['_lastInform'] ?? null))
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    private function client(): PendingRequest
    {
        $base = rtrim((string) AppSettings::get('genieacs_nbi_url', ''), '/');
        $request = Http::baseUrl($base)
            ->acceptJson()
            ->withHeaders(['Accept' => 'application/json']);

        $apiKey = trim((string) AppSettings::get('genieacs_api_key', ''));
        if ($apiKey !== '') {
            $request = $request->withHeaders(['x-api-key' => $apiKey]);
        }

        $username = trim((string) AppSettings::get('genieacs_username', ''));
        $password = (string) AppSettings::get('genieacs_password', '');
        if ($username !== '') {
            $request = $request->withBasicAuth($username, $password);
        }

        return $request;
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    private function summarizeDevice(array $device): array
    {
        $lastInform = $device['_lastInform'] ?? null;
        $deviceId = is_array($device['_deviceId'] ?? null) ? $device['_deviceId'] : [];
        $temperature = $this->extractTemperature($device);
        $rxPower = $this->extractRxPower($device);
        $ssid = $this->extractSsid($device);
        $ssidPassword = $this->extractSsidPassword($device);

        return [
            'id' => (string) ($device['_id'] ?? ''),
            'manufacturer' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.Manufacturer',
                'Device.DeviceInfo.Manufacturer',
            ]) ?: $this->scalarOrNull($deviceId['_Manufacturer'] ?? null),
            'model' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.ModelName',
                'Device.DeviceInfo.ModelName',
            ]) ?: $this->scalarOrNull($deviceId['_ProductClass'] ?? null),
            'serial' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.SerialNumber',
                'Device.DeviceInfo.SerialNumber',
            ]) ?: $this->scalarOrNull($deviceId['_SerialNumber'] ?? null),
            'software_version' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
                'Device.DeviceInfo.SoftwareVersion',
            ]),
            'hardware_version' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.HardwareVersion',
                'Device.DeviceInfo.HardwareVersion',
            ]),
            'temperature' => $temperature,
            'temperature_label' => $temperature !== null ? $temperature.' °C' : '—',
            'rx_power' => $rxPower,
            'rx_power_label' => $rxPower !== null ? $rxPower.' dBm' : '—',
            'ssid' => $ssid,
            'ssid_password' => $ssidPassword,
            'tags' => $this->normalizeTags($device['_tags'] ?? []),
            'last_inform' => $lastInform,
            'last_inform_label' => $lastInform ? $this->formatDate($lastInform) : '—',
            'online' => $this->isRecentlyInformed($lastInform),
        ];
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    private function detailDevice(array $device): array
    {
        $summary = $this->summarizeDevice($device);
        $deviceId = is_array($device['_deviceId'] ?? null) ? $device['_deviceId'] : [];

        return [
            ...$summary,
            'product_class' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.ProductClass',
                'Device.DeviceInfo.ProductClass',
                'InternetGatewayDevice.DeviceInfo.ModelName',
                'Device.DeviceInfo.ModelName',
            ]) ?: $this->scalarOrNull($deviceId['_ProductClass'] ?? null),
            'oui' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.ManufacturerOUI',
                'Device.DeviceInfo.ManufacturerOUI',
            ]) ?: $this->scalarOrNull($deviceId['_OUI'] ?? null),
            'raw_keys' => array_values(array_filter(array_keys($device), fn ($key) => ! str_starts_with((string) $key, '_'))),
        ];
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  array<int, string>  $paths
     */
    private function firstParam(array $device, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = $this->paramValue($device, $path);
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function paramValue(array $device, string $path): mixed
    {
        $node = $device;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        if (is_array($node) && array_key_exists('_value', $node)) {
            return $node['_value'];
        }

        return is_scalar($node) ? $node : null;
    }

    private function scalarOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function extractTemperature(array $device): ?float
    {
        $virtual = $this->firstParam($device, ['VirtualParameters.gettemp']);
        if ($virtual !== null && is_numeric($virtual)) {
            return round((float) $virtual, 1);
        }

        foreach ([
            'InternetGatewayDevice.WANDevice.1.X_CU_WANEPONInterfaceConfig.OpticalTransceiver.Temperature',
            'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.TransceiverTemperature',
            'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TransceiverTemperature',
        ] as $path) {
            $raw = $this->paramValue($device, $path);
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                continue;
            }

            $value = (float) $raw;
            // Banyak ONT ZTE/CMCC menyimpan suhu sebagai nilai × 256.
            if ($value > 200) {
                $value = $value / 256;
            }

            return round($value, 1);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function extractRxPower(array $device): ?float
    {
        $virtual = $this->firstParam($device, ['VirtualParameters.RXPower']);
        if ($virtual !== null && is_numeric($virtual)) {
            return round((float) $virtual, 2);
        }

        foreach ([
            'InternetGatewayDevice.WANDevice.1.X_CU_WANEPONInterfaceConfig.OpticalTransceiver.RXPower',
            'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.RXPower',
            'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.RXPower',
        ] as $path) {
            $raw = $this->paramValue($device, $path);
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                continue;
            }

            $value = (float) $raw;
            // Nilai mentah optik vendor sering perlu dikonversi ke dBm.
            if ($value > 0 && $value < 10000) {
                $value = round(($value * 0.002) - 30, 2);
            }

            return round($value, 2);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function extractSsid(array $device): ?string
    {
        return $this->firstParam($device, [
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
            'Device.WiFi.SSID.1.SSID',
        ]);
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function extractSsidPassword(array $device): ?string
    {
        return $this->firstParam($device, [
            'VirtualParameters.WlanPassword',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.KeyPassphrase',
            'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTags(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        if (array_is_list($tags)) {
            return array_values(array_map(
                static fn ($tag) => (string) $tag,
                array_filter($tags, static fn ($tag) => is_scalar($tag) && (string) $tag !== '')
            ));
        }

        // Format lama GenieACS: { "tagName": true }
        return array_values(array_map('strval', array_keys(array_filter($tags))));
    }

    private function isRecentlyInformed(mixed $lastInform): bool
    {
        if (! $lastInform) {
            return false;
        }

        try {
            return abs(now()->diffInMinutes(\Carbon\Carbon::parse($lastInform), false)) <= 15;
        } catch (Throwable) {
            return false;
        }
    }

    private function formatDate(mixed $value): string
    {
        try {
            return \Carbon\Carbon::parse($value)->timezone(config('app.timezone'))->format('d M Y H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
