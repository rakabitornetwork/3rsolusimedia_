<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MikrotikRouter;
use App\Services\MikrotikApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RouterOsController extends Controller
{
    public function __construct(private readonly MikrotikApiService $api)
    {
    }

    public function index(): Response
    {
        $routers = MikrotikRouter::query()
            ->orderBy('name')
            ->get()
            ->map(fn (MikrotikRouter $router) => $router->toSafeArray())
            ->values();

        return Inertia::render('Admin/Network/RouterOs/Index', [
            'routers' => $routers,
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->user()?->isSuperadmin()) {
            return redirect()
                ->route('admin.network.routeros')
                ->with('error', 'Hanya Superadmin yang dapat menambahkan RouterOS.');
        }

        return Inertia::render('Admin/Network/RouterOs/Form', [
            'router' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()?->isSuperadmin()) {
            return redirect()
                ->route('admin.network.routeros')
                ->with('error', 'Hanya Superadmin yang dapat menambahkan RouterOS.');
        }

        $validated = $this->validateRouter($request);

        MikrotikRouter::create([
            ...$validated,
            'port' => $validated['port'] ?? 8728,
            'use_ssl' => $request->boolean('use_ssl'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.network.routeros')
            ->with('success', 'Router berhasil ditambahkan.');
    }

    public function edit(Request $request, MikrotikRouter $router): Response|RedirectResponse
    {
        if (! $request->user()?->isSuperadmin()) {
            return redirect()
                ->route('admin.network.routeros')
                ->with('error', 'Hanya Superadmin yang dapat mengedit RouterOS.');
        }

        return Inertia::render('Admin/Network/RouterOs/Form', [
            'router' => $router->toSafeArray(),
        ]);
    }

    public function update(Request $request, MikrotikRouter $router): RedirectResponse
    {
        if (! $request->user()?->isSuperadmin()) {
            return redirect()
                ->route('admin.network.routeros')
                ->with('error', 'Hanya Superadmin yang dapat mengedit RouterOS.');
        }

        $validated = $this->validateRouter($request, updating: true);

        $payload = [
            'name' => $validated['name'],
            'host' => $validated['host'],
            'port' => $validated['port'] ?? 8728,
            'username' => $validated['username'],
            'use_ssl' => $request->boolean('use_ssl'),
            'is_active' => $request->boolean('is_active', true),
            'notes' => $validated['notes'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $router->update($payload);

        return redirect()
            ->route('admin.network.routeros')
            ->with('success', 'Router berhasil diperbarui.');
    }

    public function destroy(Request $request, MikrotikRouter $router): RedirectResponse
    {
        if (! $request->user()?->isSuperadmin()) {
            return redirect()
                ->route('admin.network.routeros')
                ->with('error', 'Hanya Superadmin yang dapat menghapus RouterOS.');
        }

        $router->delete();

        return redirect()
            ->route('admin.network.routeros')
            ->with('success', 'Router berhasil dihapus.');
    }

    public function test(MikrotikRouter $router): RedirectResponse
    {
        $result = $this->api->testConnection($router);

        $router->update([
            'last_checked_at' => now(),
            'last_status' => $result['ok'] ? 'online' : 'offline',
            'last_message' => $result['message'],
        ]);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message'].(
                $result['ok'] && ! empty($result['info']['identity'])
                    ? ' Identity: '.$result['info']['identity']
                    : ''
            )
        );
    }

    public function show(MikrotikRouter $router): Response|RedirectResponse
    {
        $result = $this->api->fetchDashboard($router);

        $router->update([
            'last_checked_at' => now(),
            'last_status' => $result['ok'] ? 'online' : 'offline',
            'last_message' => $result['ok'] ? 'Sinkronisasi data berhasil' : ($result['message'] ?? 'Gagal'),
        ]);

        if (! $result['ok']) {
            return redirect()
                ->route('admin.network.routeros')
                ->with('error', $result['message'] ?? 'Gagal mengambil data RouterOS.');
        }

        return Inertia::render('Admin/Network/RouterOs/Show', [
            'router' => $router->fresh()->toSafeArray(),
            'info' => $result['data'],
        ]);
    }

    public function interfaces(MikrotikRouter $router)
    {
        $result = $this->api->listPhysicalInterfaces($router);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function traffic(Request $request, MikrotikRouter $router)
    {
        $validated = $request->validate([
            'interface' => ['required', 'string', 'max:120'],
        ]);

        $result = $this->api->monitorTraffic($router, $validated['interface']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    private function validateRouter(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:120'],
            'password' => [$updating ? 'nullable' : 'required', 'string', 'max:255'],
            'use_ssl' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
