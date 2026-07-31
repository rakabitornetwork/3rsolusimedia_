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
                return [
                    'ok' => true,
                    'message' => 'Koneksi ke GenieACS NBI berhasil.',
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
    public function listDevices(?string $search = null, int $limit = 50, int $skip = 0): array
    {
        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'message' => 'Integrasi GenieACS belum diaktifkan atau URL belum diisi.',
            ];
        }

        $query = [];
        if ($search) {
            $query = [
                '$or' => [
                    ['_id' => ['$regex' => $search, '$options' => 'i']],
                    ['_tags' => $search],
                    ['InternetGatewayDevice.DeviceInfo.SerialNumber._value' => ['$regex' => $search, '$options' => 'i']],
                    ['Device.DeviceInfo.SerialNumber._value' => ['$regex' => $search, '$options' => 'i']],
                    ['InternetGatewayDevice.DeviceInfo.Manufacturer._value' => ['$regex' => $search, '$options' => 'i']],
                    ['Device.DeviceInfo.Manufacturer._value' => ['$regex' => $search, '$options' => 'i']],
                ],
            ];
        }

        try {
            $params = [
                'limit' => max(1, min(200, $limit)),
                'skip' => max(0, $skip),
                'projection' => implode(',', [
                    '_id',
                    '_lastInform',
                    '_tags',
                    'InternetGatewayDevice.DeviceInfo.Manufacturer',
                    'InternetGatewayDevice.DeviceInfo.ModelName',
                    'InternetGatewayDevice.DeviceInfo.SerialNumber',
                    'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
                    'InternetGatewayDevice.DeviceInfo.HardwareVersion',
                    'Device.DeviceInfo.Manufacturer',
                    'Device.DeviceInfo.ModelName',
                    'Device.DeviceInfo.SerialNumber',
                    'Device.DeviceInfo.SoftwareVersion',
                    'Device.DeviceInfo.HardwareVersion',
                ]),
            ];

            if ($query !== []) {
                $params['query'] = json_encode($query, JSON_UNESCAPED_SLASHES);
            }

            $response = $this->client()->timeout(20)->get('/devices/', $params);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'Gagal mengambil daftar perangkat (HTTP '.$response->status().').',
                ];
            }

            $devices = collect($response->json() ?: [])
                ->map(fn (array $device) => $this->summarizeDevice($device))
                ->values()
                ->all();

            return [
                'ok' => true,
                'devices' => $devices,
                'total' => count($devices),
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
        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'message' => 'Integrasi GenieACS belum diaktifkan atau URL belum diisi.',
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
            if ($items === []) {
                return [
                    'ok' => false,
                    'message' => 'Perangkat tidak ditemukan di GenieACS.',
                ];
            }

            return [
                'ok' => true,
                'device' => $this->detailDevice($items[0]),
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
        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'message' => 'Integrasi GenieACS belum diaktifkan atau URL belum diisi.',
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
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'GenieACS belum dikonfigurasi.'];
        }

        try {
            $response = $this->client()->timeout(10)->get('/faults/', [
                'projection' => '_id',
            ]);

            if (! $response->successful()) {
                return ['ok' => false, 'message' => 'Gagal mengambil faults.'];
            }

            return [
                'ok' => true,
                'count' => count($response->json() ?: []),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
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

        return [
            'id' => (string) ($device['_id'] ?? ''),
            'manufacturer' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.Manufacturer',
                'Device.DeviceInfo.Manufacturer',
            ]),
            'model' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.ModelName',
                'Device.DeviceInfo.ModelName',
            ]),
            'serial' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.SerialNumber',
                'Device.DeviceInfo.SerialNumber',
            ]),
            'software_version' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.SoftwareVersion',
                'Device.DeviceInfo.SoftwareVersion',
            ]),
            'hardware_version' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.HardwareVersion',
                'Device.DeviceInfo.HardwareVersion',
            ]),
            'tags' => array_values($device['_tags'] ?? []),
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

        return [
            ...$summary,
            'product_class' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.ProductClass',
                'Device.DeviceInfo.ProductClass',
                'InternetGatewayDevice.DeviceInfo.ModelName',
                'Device.DeviceInfo.ModelName',
            ]),
            'oui' => $this->firstParam($device, [
                'InternetGatewayDevice.DeviceInfo.ManufacturerOUI',
                'Device.DeviceInfo.ManufacturerOUI',
            ]),
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

    private function isRecentlyInformed(mixed $lastInform): bool
    {
        if (! $lastInform) {
            return false;
        }

        try {
            return now()->diffInMinutes(\Carbon\Carbon::parse($lastInform)) <= 15;
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
