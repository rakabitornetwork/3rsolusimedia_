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
                    ['VirtualParameters.pppoeUsername._value' => ['$regex' => $search, '$options' => 'i']],
                    ['VirtualParameters.PPPoEUsername._value' => ['$regex' => $search, '$options' => 'i']],
                    ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Username._value' => ['$regex' => $search, '$options' => 'i']],
                    ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.Username._value' => ['$regex' => $search, '$options' => 'i']],
                    ['Device.PPP.Interface.1.Username._value' => ['$regex' => $search, '$options' => 'i']],
                ],
            ];
        }

        try {
            $params = [
                'limit' => max(1, min(500, $limit)),
                'skip' => max(0, $skip),
                'projection' => implode(',', $this->deviceListProjection()),
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
     * Antrikan task reboot ONT/CPE melalui GenieACS NBI.
     *
     * @return array{ok: bool, message: string}
     */
    public function rebootDevice(string $deviceId): array
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
                    'name' => 'reboot',
                ]);

            if (in_array($response->status(), [200, 202], true)) {
                return [
                    'ok' => true,
                    'message' => $response->status() === 200
                        ? 'Perintah reboot berhasil dikirim ke perangkat.'
                        : 'Task reboot diantrikan. Perangkat akan reboot pada connection request / inform berikutnya.',
                ];
            }

            return [
                'ok' => false,
                'message' => 'Gagal reboot perangkat (HTTP '.$response->status().'). '.$response->body(),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal reboot perangkat: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Ubah SSID dan/atau password WiFi (WLANConfiguration.1) via setParameterValues.
     *
     * @return array{ok: bool, message: string}
     */
    public function updateWifi(string $deviceId, ?string $ssid = null, ?string $password = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'URL NBI GenieACS belum dikonfigurasi.',
            ];
        }

        $ssid = $ssid !== null ? trim($ssid) : null;
        $password = $password !== null ? trim($password) : null;

        if (($ssid === null || $ssid === '') && ($password === null || $password === '')) {
            return [
                'ok' => false,
                'message' => 'Isi SSID dan/atau password baru.',
            ];
        }

        if ($ssid !== null && $ssid !== '' && (strlen($ssid) < 1 || strlen($ssid) > 32)) {
            return [
                'ok' => false,
                'message' => 'SSID harus 1–32 karakter.',
            ];
        }

        if ($password !== null && $password !== '' && (strlen($password) < 8 || strlen($password) > 63)) {
            return [
                'ok' => false,
                'message' => 'Password WiFi harus 8–63 karakter.',
            ];
        }

        $parameterValues = [];

        if ($ssid !== null && $ssid !== '') {
            $parameterValues[] = [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID',
                $ssid,
                'xsd:string',
            ];
        }

        if ($password !== null && $password !== '') {
            // Path yang dipakai mayoritas ONT ZTE/CMHI di jaringan ini.
            $parameterValues[] = [
                'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase',
                $password,
                'xsd:string',
            ];
        }

        try {
            $response = $this->client()
                ->timeout(35)
                ->withQueryParameters(['connection_request' => ''])
                ->post('/devices/'.rawurlencode($deviceId).'/tasks', [
                    'name' => 'setParameterValues',
                    'parameterValues' => $parameterValues,
                ]);

            if (! in_array($response->status(), [200, 202], true)) {
                return [
                    'ok' => false,
                    'message' => 'Gagal mengubah WiFi (HTTP '.$response->status().'). '.$response->body(),
                ];
            }

            // Refresh objek WLAN agar nilai baru terbaca di inform berikutnya.
            try {
                $this->client()
                    ->timeout(20)
                    ->withQueryParameters(['connection_request' => ''])
                    ->post('/devices/'.rawurlencode($deviceId).'/tasks', [
                        'name' => 'refreshObject',
                        'objectName' => 'InternetGatewayDevice.LANDevice.1.WLANConfiguration',
                    ]);
            } catch (Throwable) {
                // Non-fatal.
            }

            $parts = [];
            if ($ssid !== null && $ssid !== '') {
                $parts[] = 'SSID';
            }
            if ($password !== null && $password !== '') {
                $parts[] = 'password';
            }

            return [
                'ok' => true,
                'message' => $response->status() === 200
                    ? 'Berhasil mengubah '.implode(' & ', $parts).' WiFi pada perangkat.'
                    : 'Perubahan '.implode(' & ', $parts).' WiFi diantrikan. Akan diterapkan saat perangkat merespons connection request / inform.',
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal mengubah WiFi: '.$e->getMessage(),
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
     * Index metrik optik GenieACS berdasarkan username PPPoE (lowercase).
     *
     * @return array{ok: bool, message?: string, index?: array<string, array<string, mixed>>, total?: int, matched?: int}
     */
    public function opticalIndexByPppoeUsername(int $limit = 500): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'URL NBI GenieACS belum dikonfigurasi.',
                'index' => [],
            ];
        }

        $result = $this->listDevices(null, $limit, 0);
        if (! ($result['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $result['message'] ?? 'Gagal mengambil perangkat GenieACS.',
                'index' => [],
            ];
        }

        $index = [];
        foreach ($result['devices'] ?? [] as $device) {
            if (! is_array($device)) {
                continue;
            }

            $username = strtolower(trim((string) ($device['pppoe_username'] ?? '')));
            if ($username === '') {
                continue;
            }

            // Username pertama yang cocok menang (hindari overwrite tanpa alasan kuat).
            if (isset($index[$username])) {
                continue;
            }

            $index[$username] = [
                'matched' => true,
                'device_id' => $device['id'] ?? null,
                'manufacturer' => $device['manufacturer'] ?? null,
                'model' => $device['model'] ?? null,
                'serial' => $device['serial'] ?? null,
                'temperature' => $device['temperature'] ?? null,
                'temperature_label' => $device['temperature_label'] ?? '—',
                'rx_power' => $device['rx_power'] ?? null,
                'rx_power_label' => $device['rx_power_label'] ?? '—',
                'online_ont' => (bool) ($device['online'] ?? false),
                'last_inform' => $device['last_inform'] ?? null,
                'last_inform_label' => $device['last_inform_label'] ?? '—',
            ];
        }

        return [
            'ok' => true,
            'index' => $index,
            'total' => (int) ($result['total'] ?? count($result['devices'] ?? [])),
            'matched' => count($index),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function deviceListProjection(): array
    {
        $paths = [
            '_id',
            '_lastInform',
            '_tags',
            '_deviceId',
            'VirtualParameters.gettemp',
            'VirtualParameters.RXPower',
            'VirtualParameters.TXPower',
            'VirtualParameters.Redaman',
            'VirtualParameters.Attenuation',
            'VirtualParameters.pppoeUsername',
            'VirtualParameters.PPPoEUsername',
            'VirtualParameters.pppoe_username',
            'VirtualParameters.WlanPassword',
            'VirtualParameters.activedevices',
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
            'InternetGatewayDevice.WANDevice.1.X_CU_WANEPONInterfaceConfig.OpticalTransceiver.TXPower',
            'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.TransceiverTemperature',
            'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.RXPower',
            'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.TXPower',
            'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TransceiverTemperature',
            'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.RXPower',
            'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TXPower',
            'Device.DeviceInfo.Manufacturer',
            'Device.DeviceInfo.ModelName',
            'Device.DeviceInfo.SerialNumber',
            'Device.DeviceInfo.SoftwareVersion',
            'Device.DeviceInfo.HardwareVersion',
            'Device.WiFi.SSID.1.SSID',
            'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
        ];

        for ($wan = 1; $wan <= 2; $wan++) {
            for ($conn = 1; $conn <= 4; $conn++) {
                for ($ppp = 1; $ppp <= 2; $ppp++) {
                    $paths[] = "InternetGatewayDevice.WANDevice.{$wan}.WANConnectionDevice.{$conn}.WANPPPConnection.{$ppp}.Username";
                }
            }
        }

        for ($i = 1; $i <= 4; $i++) {
            $paths[] = "Device.PPP.Interface.{$i}.Username";
        }

        return $paths;
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
        $txPower = $this->extractTxPower($device);
        $redaman = $this->extractRedaman($device, $rxPower, $txPower);
        $pppoeUsername = $this->extractPppoeUsername($device);
        $ssid = $this->extractSsid($device);
        $ssidPassword = $this->extractSsidPassword($device);
        $connectedCount = $this->extractConnectedCount($device);

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
            'pppoe_username' => $pppoeUsername,
            'temperature' => $temperature,
            'temperature_label' => $temperature !== null ? $temperature.' °C' : '—',
            'rx_power' => $rxPower,
            'rx_power_label' => $rxPower !== null ? $rxPower.' dBm' : '—',
            'tx_power' => $txPower,
            'tx_power_label' => $txPower !== null ? $txPower.' dBm' : '—',
            'redaman' => $redaman,
            'redaman_label' => $redaman !== null ? $redaman.' dB' : '—',
            'ssid' => $ssid,
            'ssid_password' => $ssidPassword,
            'connected_count' => $connectedCount,
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
        $clients = $this->extractConnectedClients($device);

        return [
            ...$summary,
            'connected_count' => max((int) ($summary['connected_count'] ?? 0), count($clients)),
            'connected_clients' => $clients,
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

            return $this->normalizeOpticalDbm((float) $raw);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function extractTxPower(array $device): ?float
    {
        $virtual = $this->firstParam($device, ['VirtualParameters.TXPower']);
        if ($virtual !== null && is_numeric($virtual)) {
            return round((float) $virtual, 2);
        }

        foreach ([
            'InternetGatewayDevice.WANDevice.1.X_CU_WANEPONInterfaceConfig.OpticalTransceiver.TXPower',
            'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.TXPower',
            'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TXPower',
        ] as $path) {
            $raw = $this->paramValue($device, $path);
            if ($raw === null || $raw === '' || ! is_numeric($raw)) {
                continue;
            }

            return $this->normalizeOpticalDbm((float) $raw);
        }

        return null;
    }

    /**
     * Redaman (dB): VirtualParameters bila ada, else TX − RX.
     *
     * @param  array<string, mixed>  $device
     */
    private function extractRedaman(array $device, ?float $rxPower, ?float $txPower): ?float
    {
        $virtual = $this->firstParam($device, [
            'VirtualParameters.Redaman',
            'VirtualParameters.Attenuation',
        ]);
        if ($virtual !== null && is_numeric($virtual)) {
            return round(abs((float) $virtual), 2);
        }

        if ($rxPower === null || $txPower === null) {
            return null;
        }

        return round(abs($txPower - $rxPower), 2);
    }

    private function normalizeOpticalDbm(float $value): float
    {
        // Raw vendor (uint) biasanya >> 40; dBm ONT nyata sekitar -40 s/d +10.
        if ($value > 40) {
            $value = ($value * 0.002) - 30;
        }

        return round($value, 2);
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function extractPppoeUsername(array $device): ?string
    {
        $virtual = $this->firstParam($device, [
            'VirtualParameters.pppoeUsername',
            'VirtualParameters.PPPoEUsername',
            'VirtualParameters.pppoe_username',
        ]);
        if ($virtual !== null && trim($virtual) !== '') {
            return trim($virtual);
        }

        $fixedPaths = [];
        for ($wan = 1; $wan <= 2; $wan++) {
            for ($conn = 1; $conn <= 4; $conn++) {
                for ($ppp = 1; $ppp <= 2; $ppp++) {
                    $fixedPaths[] = "InternetGatewayDevice.WANDevice.{$wan}.WANConnectionDevice.{$conn}.WANPPPConnection.{$ppp}.Username";
                }
            }
        }
        for ($i = 1; $i <= 4; $i++) {
            $fixedPaths[] = "Device.PPP.Interface.{$i}.Username";
        }

        $fixed = $this->firstParam($device, $fixedPaths);
        if ($fixed !== null && trim($fixed) !== '') {
            return trim($fixed);
        }

        $found = $this->findPppoeUsernamesRecursive($device);
        foreach ($found as $username) {
            if ($username !== '') {
                return $username;
            }
        }

        return null;
    }

    /**
     * Cari Username di bawah node WANPPPConnection / PPP.Interface.
     *
     * @param  array<string, mixed>  $node
     * @return array<int, string>
     */
    private function findPppoeUsernamesRecursive(array $node, string $path = ''): array
    {
        $found = [];

        foreach ($node as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $segment = (string) $key;
            if (str_starts_with($segment, '_')) {
                continue;
            }

            $nextPath = $path === '' ? $segment : $path.'.'.$segment;

            if ($segment === 'Username' || $segment === 'username') {
                $isPppContext = str_contains($path, 'WANPPPConnection')
                    || str_contains($path, 'PPP.Interface')
                    || str_contains($path, '.PPP.');

                if ($isPppContext) {
                    $raw = is_array($value) && array_key_exists('_value', $value)
                        ? $value['_value']
                        : (is_scalar($value) ? $value : null);
                    $username = trim((string) ($raw ?? ''));
                    if ($username !== '') {
                        $found[] = $username;
                    }
                }
            }

            if (is_array($value)) {
                $found = array_merge($found, $this->findPppoeUsernamesRecursive($value, $nextPath));
            }
        }

        return $found;
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
     * @param  array<string, mixed>  $device
     */
    private function extractConnectedCount(array $device): int
    {
        $virtual = $this->firstParam($device, ['VirtualParameters.activedevices']);
        if ($virtual !== null && is_numeric($virtual)) {
            return max(0, (int) $virtual);
        }

        return count($this->extractConnectedClients($device));
    }

    /**
     * Daftar klien WiFi/LAN terhubung (AssociatedDevice + Hosts untuk nama).
     *
     * @param  array<string, mixed>  $device
     * @return array<int, array<string, mixed>>
     */
    private function extractConnectedClients(array $device): array
    {
        $hostsByMac = [];
        $hostNode = data_get($device, 'InternetGatewayDevice.LANDevice.1.Hosts.Host');
        if (is_array($hostNode)) {
            foreach ($hostNode as $index => $host) {
                if (! is_numeric((string) $index) || ! is_array($host)) {
                    continue;
                }

                $mac = strtolower((string) ($this->nodeValue($host, 'MACAddress') ?? ''));
                if ($mac === '') {
                    continue;
                }

                $hostsByMac[$mac] = [
                    'hostname' => $this->scalarOrNull($this->nodeValue($host, 'HostName')),
                    'ip' => $this->scalarOrNull($this->nodeValue($host, 'IPAddress')),
                    'active' => $this->truthy($this->nodeValue($host, 'Active')),
                ];
            }
        }

        $clients = [];
        $seenMacs = [];
        $wlanNode = data_get($device, 'InternetGatewayDevice.LANDevice.1.WLANConfiguration');
        if (is_array($wlanNode)) {
            foreach ($wlanNode as $wlanIndex => $wlan) {
                if (! is_numeric((string) $wlanIndex) || ! is_array($wlan)) {
                    continue;
                }

                $ssid = $this->scalarOrNull($this->nodeValue($wlan, 'SSID'));
                $assoc = $wlan['AssociatedDevice'] ?? null;
                if (! is_array($assoc)) {
                    continue;
                }

                foreach ($assoc as $assocIndex => $client) {
                    if (! is_numeric((string) $assocIndex) || ! is_array($client)) {
                        continue;
                    }

                    $mac = strtolower((string) ($this->nodeValue($client, 'AssociatedDeviceMACAddress') ?? ''));
                    if ($mac === '' || isset($seenMacs[$mac])) {
                        continue;
                    }

                    $host = $hostsByMac[$mac] ?? null;
                    $hostname = $this->scalarOrNull($this->nodeValue($client, 'X_CU_Hostname'))
                        ?: $this->scalarOrNull($this->nodeValue($client, 'HostName'))
                        ?: $this->scalarOrNull($this->nodeValue($client, 'AssociatedDeviceName'))
                        ?: ($host['hostname'] ?? null);

                    $ip = $this->scalarOrNull($this->nodeValue($client, 'AssociatedDeviceIPAddress'))
                        ?: ($host['ip'] ?? null);

                    $seenMacs[$mac] = true;
                    $clients[] = [
                        'name' => $hostname ?: strtoupper($mac),
                        'hostname' => $hostname,
                        'mac' => strtoupper($mac),
                        'ip' => $ip,
                        'ssid' => $ssid,
                        'interface' => 'WiFi '.$wlanIndex,
                    ];
                }
            }
        }

        // Fallback: host aktif di LAN jika AssociatedDevice kosong.
        if ($clients === []) {
            foreach ($hostsByMac as $mac => $host) {
                if (($host['active'] ?? false) === false && ($host['ip'] ?? null) === null) {
                    continue;
                }

                $clients[] = [
                    'name' => $host['hostname'] ?: strtoupper($mac),
                    'hostname' => $host['hostname'],
                    'mac' => strtoupper($mac),
                    'ip' => $host['ip'],
                    'ssid' => null,
                    'interface' => 'LAN/WiFi',
                ];
            }
        }

        usort($clients, static fn (array $a, array $b) => strcmp((string) $a['name'], (string) $b['name']));

        return array_values($clients);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function nodeValue(array $node, string $key): mixed
    {
        if (! array_key_exists($key, $node)) {
            return null;
        }

        $value = $node[$key];
        if (is_array($value) && array_key_exists('_value', $value)) {
            return $value['_value'];
        }

        return is_scalar($value) ? $value : null;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
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
