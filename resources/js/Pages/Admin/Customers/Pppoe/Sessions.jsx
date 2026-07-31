import { Head, Link, router } from '@inertiajs/react';
import { RefreshCw, Search, Unplug } from 'lucide-react';
import AdminLayout from '../../../../Layouts/AdminLayout';

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
            '/admin/customers/pppoe/sessions',
            { router_id: routerId, q: filters.q || '' },
            { preserveState: true, replace: true },
        );
    };

    const applySearch = (value) => {
        router.get(
            '/admin/customers/pppoe/sessions',
            { router_id: selected_router_id, q: value },
            { preserveState: true, replace: true },
        );
    };

    const refresh = () => {
        router.get(
            '/admin/customers/pppoe/sessions',
            { router_id: selected_router_id, q: filters.q || '' },
            { preserveState: true, replace: true },
        );
    };

    const disconnect = (session) => {
        if (!selected_router_id) return;
        if (
            !window.confirm(
                `Putus sesi PPPoE "${session.name}"${session.customer_name ? ` (${session.customer_name})` : ''}?`,
            )
        ) {
            return;
        }

        router.delete(
            `/admin/customers/pppoe/sessions/${selected_router_id}/${encodeURIComponent(session.id)}`,
        );
    };

    return (
        <AdminLayout
            title="Sesi Aktif PPPoE"
            subtitle="Monitor dan putus sesi pelanggan yang sedang online di RouterOS"
        >
            <Head title="Sesi Aktif PPPoE" />
            {error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}

            <div className="mb-5 grid items-stretch gap-3 sm:grid-cols-3">
                <div className="flex min-h-[100px] flex-col bg-gradient-to-br from-emerald-500 to-teal-700 p-4 text-white shadow-sm">
                    <p className="text-[11px] tracking-wide text-white/75 uppercase">Online sekarang</p>
                    <p className="font-display mt-3 text-2xl font-bold">{stats.online}</p>
                </div>
                <div className="flex min-h-[100px] flex-col bg-gradient-to-br from-sky-500 to-blue-700 p-4 text-white shadow-sm">
                    <p className="text-[11px] tracking-wide text-white/75 uppercase">Terdaftar di app</p>
                    <p className="font-display mt-3 text-2xl font-bold">{stats.matched}</p>
                </div>
                <div className="flex min-h-[100px] flex-col bg-gradient-to-br from-amber-400 to-orange-600 p-4 text-white shadow-sm">
                    <p className="text-[11px] tracking-wide text-white/75 uppercase">Belum terdaftar</p>
                    <p className="font-display mt-3 text-2xl font-bold">{stats.unknown}</p>
                </div>
            </div>

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <select
                        value={selected_router_id || ''}
                        onChange={(e) => changeRouter(e.target.value)}
                        className="border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal"
                    >
                        {routers.length === 0 && <option value="">Tidak ada router</option>}
                        {routers.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name} ({item.host})
                            </option>
                        ))}
                    </select>

                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft" />
                        <input
                            type="search"
                            defaultValue={filters.q}
                            placeholder="Cari username / nama / IP..."
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') applySearch(e.target.value);
                            }}
                            className="w-64 border border-ink/15 py-2 pr-3 pl-9 text-sm outline-none focus:border-signal"
                        />
                    </div>
                </div>

                <button
                    type="button"
                    onClick={refresh}
                    className="inline-flex items-center gap-2 border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-mist"
                >
                    <RefreshCw className="h-4 w-4" />
                    Refresh
                </button>
            </div>

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Username</th>
                            <th className="px-4 py-3 font-semibold">Pelanggan</th>
                            <th className="hidden px-4 py-3 font-semibold md:table-cell">IP</th>
                            <th className="hidden px-4 py-3 font-semibold lg:table-cell">Caller ID</th>
                            <th className="px-4 py-3 font-semibold">Uptime</th>
                            <th className="hidden px-4 py-3 font-semibold xl:table-cell">Service</th>
                            <th className="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        {sessions.map((session) => (
                            <tr key={session.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{session.name || '—'}</p>
                                    {session.service_profile && (
                                        <p className="text-xs text-ink-soft">{session.service_profile}</p>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    {session.customer_id ? (
                                        <div>
                                            <Link
                                                href={`/admin/customers/pppoe/${session.customer_id}/edit`}
                                                className="font-medium text-signal-deep hover:underline"
                                            >
                                                {session.customer_name}
                                            </Link>
                                            <p className="text-xs text-ink-soft">{session.customer_status}</p>
                                        </div>
                                    ) : (
                                        <span className="text-xs font-semibold text-amber-700">
                                            Tidak terdaftar
                                        </span>
                                    )}
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft md:table-cell">
                                    {session.address || '—'}
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft lg:table-cell">
                                    {session.caller_id || '—'}
                                </td>
                                <td className="px-4 py-3 font-medium text-ink">{session.uptime || '—'}</td>
                                <td className="hidden px-4 py-3 text-ink-soft xl:table-cell">
                                    {session.service || '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="admin-actions">
                                        <button
                                            type="button"
                                            onClick={() => disconnect(session)}
                                            className="inline-flex items-center gap-1 border border-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                        >
                                            <Unplug className="h-3.5 w-3.5" />
                                            Putus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {sessions.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-10 text-center text-ink-soft">
                                    Tidak ada sesi PPPoE aktif
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
