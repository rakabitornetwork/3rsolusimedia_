<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mengingat filter/pilihan terakhir tiap halaman admin (dan router terakhir)
 * supaya aksi ubah/hapus/simpan tidak mengembalikan daftar ke default.
 */
class AdminListState
{
    public const LAST_ROUTER = 'admin.last_router_id';

    public const PPPOE = 'pppoe';

    public const PPPOE_SESSIONS = 'pppoe-sessions';

    public const HOTSPOT = 'hotspot';

    public const HOTSPOT_SESSIONS = 'hotspot-sessions';

    public const HOTSPOT_PROFILES = 'hotspot-profiles';

    public const MIKROTIK_PROFILES = 'mikrotik-profiles';

    public const SERVICE_PROFILES = 'service-profiles';

    public const BILLING = 'billing';

    public const BILLING_REPORTS = 'billing-reports';

    public const AGENT_COMMISSIONS = 'agent-commissions';

    public const HOTSPOT_REPORTS = 'hotspot-reports';

    public const USERS = 'users';

    public const GENIEACS = 'genieacs';

    public const MAP = 'map';

    public static function sessionKey(string $listKey): string
    {
        return "admin.list.{$listKey}";
    }

    /**
     * Pulihkan query tersimpan ke request bila URL tidak membawa filter,
     * dan simpan pilihan saat user memang menyertakan query.
     *
     * @param  list<string>  $keys
     */
    public static function apply(Request $request, string $listKey, array $keys, bool $preferLastRouter = false): void
    {
        $hasExplicit = collect($keys)->contains(
            fn (string $key) => $request->query->has($key)
        );

        if ($hasExplicit) {
            $params = [];
            foreach ($keys as $key) {
                if ($request->query->has($key)) {
                    $params[$key] = $request->query($key);
                }
            }
            $request->session()->put(self::sessionKey($listKey), $params);
            self::rememberRouter($request, $params['router_id'] ?? null);
        } else {
            $saved = $request->session()->get(self::sessionKey($listKey), []);
            if (is_array($saved)) {
                foreach ($keys as $key) {
                    if (array_key_exists($key, $saved) && ! $request->query->has($key)) {
                        $request->query->set($key, $saved[$key]);
                    }
                }
            }
        }

        $currentRouter = $request->query('router_id');
        if ($preferLastRouter && ($currentRouter === null || $currentRouter === '')) {
            $last = self::lastRouterId($request);
            if ($last) {
                $request->query->set('router_id', $last);
            }
        }
    }

    public static function lastRouterId(?Request $request = null): ?int
    {
        $id = ($request ?? request())->session()->get(self::LAST_ROUTER);

        return $id ? (int) $id : null;
    }

    public static function rememberRouter(Request $request, mixed $routerId): void
    {
        if ($routerId === null || $routerId === '') {
            return;
        }

        $id = (int) $routerId;
        if ($id > 0) {
            $request->session()->put(self::LAST_ROUTER, $id);
        }
    }

    /**
     * Redirect ke daftar dengan filter terakhir yang diingat.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function to(string $routeName, string $listKey, array $overrides = []): RedirectResponse
    {
        $saved = session(self::sessionKey($listKey), []);
        $params = array_merge(is_array($saved) ? $saved : [], $overrides);

        $filtered = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            $filtered[$key] = $value;
        }

        if (isset($filtered['router_id'])) {
            self::rememberRouter(request(), $filtered['router_id']);
        }

        return redirect()->route($routeName, $filtered);
    }
}
