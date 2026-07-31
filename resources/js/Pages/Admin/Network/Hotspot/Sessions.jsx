import { Head, router } from '@inertiajs/react';
import { RefreshCw, Search, Unplug } from 'lucide-react';
import AdminLayout from '../../../../Layouts/AdminLayout';

function formatBytes(value) {
    if (value == null) return '—';
    const n = Number(value);
    if (!n) return '0 B';
    if (n >= 1024 ** 3) return `${(n / 1024 ** 3).toFixed(1)} GB`;
    if (n >= 1024 ** 2) return `${(n / 1024 ** 2).toFixed(1)} MB`;
    if (n >= 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${n} B`;
}

function formatUptime(uptime) {
    if (uptime == null || uptime === '') return '—';
    return String(uptime)
        .replace(/(\d+[wdhms])/gi, '$1 ')
        .replace(/\s+/g, ' ')
        .trim();
}

export default function Sessions({
    routers,
    selected_router_id,
    sessions,
    filters,
    stats,
    error,
}) {
    const changeRouter = (routerId) => {
        router.get(
            '/admin/network/hotspot/sessions',
            { router_id: routerId, q: filters.q || '' },
            { preserveState: true, replace: true },
        );
    };

    const applySearch = (value) => {
        router.get(
            '/admin/network/hotspot/sessions',
            { router_id: selected_router_id, q: value },
            { preserveState: true, replace: true },
        );
    };

    const refresh = () => {
        router.get(
            '/admin/network/hotspot/sessions',
            { router_id: selected_router_id, q: filters.q || '' },
            { preserveState: true, replace: true },
        );
    };

    const disconnect = (session) => {
        if (!selected_router_id) return;
        if (!window.confirm(`Putus sesi hotspot "${session.user}"?`)) {
            return;
        }

        router.delete(
            `/admin/network/hotspot/sessions/${selected_router_id}/${encodeURIComponent(session.id)}`,
        );
    };

    return (
        <AdminLayout
            title="Sesi Aktif Hotspot"
            subtitle="Monitor dan putus sesi user hotspot yang sedang online di RouterOS"
        >
            <Head title="Sesi Aktif Hotspot" />

            {error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}

            <div className="mb-5 grid items-stretch gap-3 sm:grid-cols-3">
                <div className="flex min-h-[100px] flex-col bg-gradient-to-br from-emerald-400 to-teal-600 p-4 text-white shadow-sm">
                    <p className="text-[11px] tracking-wide text-white/75 uppercase">Online sekarang</p>
                    <p className="font-display mt-3 text-2xl font-bold">{stats.online}</p>
                </div>
                <div className="flex min-h-[100px] flex-col bg-gradient-to-br from-cyan-400 to-sky-600 p-4 text-white shadow-sm">
                    <p className="text-[11px] tracking-wide text-white/75 uppercase">Terdaftar di user</p>
                    <p className="font-display mt-3 text-2xl font-bold">{stats.matched}</p>
                </div>
                <div className="flex min-h-[100px] flex-col bg-gradient-to-br from-amber-300 to-orange-500 p-4 text-white shadow-sm">
                    <p className="text-[11px] tracking-wide text-white/75 uppercase">Belum terdaftar</p>
                    <p className="font-display mt-3 text-2xl font-bold">{stats.unknown}</p>
                </div>
            </div>

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                    <select
                        value={selected_router_id || ''}
                        onChange={(e) => changeRouter(e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                    >
                        {routers.length === 0 && <option value="">Tidak ada router</option>}
                        {routers.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name} ({item.host})
                            </option>
                        ))}
                    </select>

                    <div className="relative w-full sm:w-auto">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft" />
                        <input
                            type="search"
                            defaultValue={filters.q}
                            placeholder="Cari user / IP / MAC..."
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') applySearch(e.target.value);
                            }}
                            className="w-full border border-ink/15 py-2 pr-3 pl-9 text-sm outline-none focus:border-signal sm:w-64"
                        />
                    </div>
                </div>

                <div className="admin-toolbar-actions">
                    <button
                        type="button"
                        onClick={refresh}
                        className="border border-ink/15 px-4 text-sm font-semibold text-ink hover:bg-mist"
                    >
                        <RefreshCw className="mr-1.5 h-4 w-4" />
                        Refresh
                    </button>
                </div>
            </div>

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Username</th>
                            <th className="hidden px-4 py-3 font-semibold md:table-cell">IP</th>
                            <th className="hidden px-4 py-3 font-semibold lg:table-cell">MAC</th>
                            <th className="px-4 py-3 font-semibold">Uptime</th>
                            <th className="hidden px-4 py-3 font-semibold xl:table-cell">Usage</th>
                            <th className="hidden px-4 py-3 font-semibold lg:table-cell">Server</th>
                            <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sessions.map((session) => (
                            <tr key={session.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{session.user || '—'}</p>
                                    <p className="text-xs text-ink-soft">
                                        {session.profile || (session.user_registered ? '—' : 'Tidak terdaftar')}
                                        {session.login_by ? ` · ${session.login_by}` : ''}
                                    </p>
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft md:table-cell">
                                    {session.address || '—'}
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft lg:table-cell">
                                    {session.mac_address || '—'}
                                </td>
                                <td className="px-4 py-3 font-medium text-ink">
                                    {formatUptime(session.uptime)}
                                    {session.idle_time ? (
                                        <p className="text-xs font-normal text-ink-soft">
                                            Idle {formatUptime(session.idle_time)}
                                        </p>
                                    ) : null}
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft xl:table-cell">
                                    ↓ {formatBytes(session.bytes_in)} / ↑{' '}
                                    {formatBytes(session.bytes_out)}
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft lg:table-cell">
                                    {session.server || '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="admin-actions">
                                        <button
                                            type="button"
                                            onClick={() => disconnect(session)}
                                            className="border border-red-100 px-2.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                        >
                                            <Unplug className="mr-1 h-3.5 w-3.5" />
                                            Putus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {sessions.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-10 text-center text-ink-soft">
                                    Tidak ada sesi hotspot aktif
                                    {filters.q ? ' untuk pencarian ini' : ' di router ini'}.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
