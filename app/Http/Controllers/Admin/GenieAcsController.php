<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\GenieAcsService;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenieAcsController extends Controller
{
    public function __construct(private readonly GenieAcsService $genie)
    {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('q', ''));
        $config = AppSettings::genieAcsConfig();
        $connection = $this->genie->isConfigured()
            ? $this->genie->testConnection()
            : ['ok' => false, 'message' => 'URL NBI belum dikonfigurasi.'];

        $devicesResult = ['ok' => true, 'devices' => [], 'total' => 0];
        $faults = ['ok' => true, 'count' => 0];

        // Ambil daftar bila URL NBI terisi & koneksi OK (tidak bergantung flag "enabled"
        // yang sering terlewat karena panel pengaturan tersembunyi).
        if ($this->genie->isConfigured() && ($connection['ok'] ?? false)) {
            $devicesResult = $this->genie->listDevices($search !== '' ? $search : null, 200);
            $faults = $this->genie->countFaults();
        } elseif ($this->genie->isConfigured() && ! ($connection['ok'] ?? false)) {
            $devicesResult = [
                'ok' => false,
                'message' => $connection['message'] ?? 'Tidak dapat terhubung ke GenieACS NBI.',
                'devices' => [],
                'total' => 0,
            ];
        }

        $listed = $devicesResult['devices'] ?? [];

        return Inertia::render('Admin/Network/GenieAcs/Index', [
            'config' => [
                ...$config,
                // Jangan kirim secret ke frontend.
                'api_key' => '',
            ],
            'connection' => $connection,
            'devices' => $listed,
            'devices_error' => ($devicesResult['ok'] ?? false) ? null : ($devicesResult['message'] ?? 'Gagal memuat perangkat.'),
            'stats' => [
                'devices' => $devicesResult['total'] ?? count($listed),
                'online' => collect($listed)->where('online', true)->count(),
                'faults' => $faults['count'] ?? 0,
            ],
            'filters' => [
                'q' => $search,
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'genieacs_enabled' => ['sometimes', 'boolean'],
            'genieacs_nbi_url' => ['required', 'url', 'max:255'],
            'genieacs_ui_url' => ['nullable', 'url', 'max:255'],
            'genieacs_api_key' => ['nullable', 'string', 'max:255'],
            'genieacs_username' => ['nullable', 'string', 'max:120'],
            'genieacs_password' => ['nullable', 'string', 'max:255'],
        ]);

        // Simpan URL = aktifkan integrasi (kecuali user sengaja menonaktifkan lewat checkbox).
        $values = [
            'genieacs_enabled' => $request->boolean('genieacs_enabled', true) ? '1' : '0',
            'genieacs_nbi_url' => rtrim($validated['genieacs_nbi_url'], '/'),
            'genieacs_ui_url' => isset($validated['genieacs_ui_url'])
                ? rtrim((string) $validated['genieacs_ui_url'], '/')
                : '',
            'genieacs_username' => $validated['genieacs_username'] ?? '',
        ];

        if (array_key_exists('genieacs_api_key', $validated) && $validated['genieacs_api_key'] !== null && $validated['genieacs_api_key'] !== '') {
            $values['genieacs_api_key'] = $validated['genieacs_api_key'];
        }

        if (array_key_exists('genieacs_password', $validated) && filled($validated['genieacs_password'])) {
            $values['genieacs_password'] = $validated['genieacs_password'];
        }

        SiteSetting::setMany($values);

        return redirect()
            ->route('admin.network.genieacs')
            ->with('success', 'Pengaturan GenieACS berhasil disimpan.');
    }

    public function test(): RedirectResponse
    {
        $result = $this->genie->testConnection();

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message'].(isset($result['latency_ms']) ? " ({$result['latency_ms']} ms)" : '')
        );
    }

    public function show(string $device): Response|RedirectResponse
    {
        $result = $this->genie->getDevice($device);

        if (! ($result['ok'] ?? false)) {
            return redirect()
                ->route('admin.network.genieacs')
                ->with('error', $result['message'] ?? 'Perangkat tidak ditemukan.');
        }

        return Inertia::render('Admin/Network/GenieAcs/Show', [
            'device' => $result['device'],
            'ui_url' => AppSettings::genieAcsConfig()['ui_url'] ?? null,
        ]);
    }

    public function summon(string $device): RedirectResponse
    {
        $result = $this->genie->summonDevice($device);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message']
        );
    }
}
