import { Head, router, useForm } from '@inertiajs/react';
import {
    Cookie,
    Laptop,
    Link2,
    RefreshCw,
    ScrollText,
    Search,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { keepPage } from '../../../../lib/keepPage';
import { matchesSearch } from '../../../../lib/search';

const TABS = [
    { id: 'hosts', label: 'Hosts', icon: Laptop },
    { id: 'cookies', label: 'Cookies', icon: Cookie },
    { id: 'bindings', label: 'IP Binding', icon: Link2 },
    { id: 'log', label: 'Log', icon: ScrollText },
];

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

function bindingTypeLabel(type) {
    if (type === 'bypassed') return 'Bypassed';
    if (type === 'blocked') return 'Blocked';
    return 'Regular';
}

export default function Tools({
    routers,
    selected_router_id,
    tab = 'hosts',
    host_filter = 'all',
    hosts = [],
    cookies = [],
    bindings = [],
    logs = [],
    servers = [],
    error,
}) {
    const [query, setQuery] = useState('');

    const { data, setData, post, processing, reset } = useForm({
        mac_address: '',
        address: '',
        to_address: '',
        server: 'all',
        type: 'bypassed',
        comment: '',
    });

    const change = (params) => {
        setQuery('');
        router.get(
            '/admin/network/hotspot/tools',
            {
                router_id: selected_router_id,
                tab,
                host_filter,
                ...params,
            },
            { preserveState: true, replace: true },
        );
    };

    const encodedId = (id) => encodeURIComponent(id);

    const removeHost = (host) => {
        if (!selected_router_id) return;
        if (!window.confirm(`Hapus host ${host.mac_address || host.address || host.id}?`)) {
            return;
        }
        router.delete(
            `/admin/network/hotspot/tools/${selected_router_id}/hosts/${encodedId(host.id)}`,
            keepPage,
        );
    };

    const bindHost = (host) => {
        if (!selected_router_id) return;
        if (
            !window.confirm(
                `Buat IP binding bypassed untuk ${host.mac_address || host.address}?`,
            )
        ) {
            return;
        }
        router.post(
            `/admin/network/hotspot/tools/${selected_router_id}/hosts/${encodedId(host.id)}/bind`,
            { type: 'bypassed' },
            keepPage,
        );
    };

    const removeCookie = (cookie) => {
        if (!selected_router_id) return;
        if (!window.confirm(`Hapus cookie user "${cookie.user}"?`)) return;
        router.delete(
            `/admin/network/hotspot/tools/${selected_router_id}/cookies/${encodedId(cookie.id)}`,
            keepPage,
        );
    };

    const toggleBinding = (binding) => {
        if (!selected_router_id) return;
        router.post(
            `/admin/network/hotspot/tools/${selected_router_id}/bindings/${encodedId(binding.id)}/toggle`,
            { disabled: !binding.disabled },
            keepPage,
        );
    };

    const removeBinding = (binding) => {
        if (!selected_router_id) return;
        if (
            !window.confirm(
                `Hapus IP binding ${binding.mac_address || binding.address || binding.id}?`,
            )
        ) {
            return;
        }
        router.delete(
            `/admin/network/hotspot/tools/${selected_router_id}/bindings/${encodedId(binding.id)}`,
            keepPage,
        );
    };

    const submitBinding = (e) => {
        e.preventDefault();
        if (!selected_router_id) return;
        post(`/admin/network/hotspot/tools/${selected_router_id}/bindings`, {
            ...keepPage,
            onSuccess: () => reset(),
        });
    };

    const hostRows = useMemo(
        () =>
            (hosts || []).filter((host) =>
                matchesSearch(
                    query,
                    host.mac_address,
                    host.address,
                    host.to_address,
                    host.server,
                    host.comment,
                    host.flags,
                ),
            ),
        [hosts, query],
    );

    const cookieRows = useMemo(
        () =>
            (cookies || []).filter((cookie) =>
                matchesSearch(query, cookie.user, cookie.mac_address, cookie.domain),
            ),
        [cookies, query],
    );

    const bindingRows = useMemo(
        () =>
            (bindings || []).filter((binding) =>
                matchesSearch(
                    query,
                    binding.mac_address,
                    binding.address,
                    binding.to_address,
                    binding.comment,
                    binding.server,
                    binding.type,
                ),
            ),
        [bindings, query],
    );

    const logRows = useMemo(
        () =>
            (logs || []).filter((row) =>
                matchesSearch(query, row.time, row.user, row.message),
            ),
        [logs, query],
    );

    const count =
        tab === 'cookies'
            ? cookieRows.length
            : tab === 'bindings'
              ? bindingRows.length
              : tab === 'log'
                ? logRows.length
                : hostRows.length;

    return (
        <AdminLayout
            title="Tools Hotspot"
            subtitle="Hosts, cookies, IP binding, dan log RouterOS — sama seperti Mikhmon"
        >
            <Head title="Tools Hotspot" />

            {error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                    <select
                        value={selected_router_id || ''}
                        onChange={(e) => change({ router_id: e.target.value, tab, host_filter })}
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
                            value={query}
                            placeholder="Cari..."
                            onChange={(e) => setQuery(e.target.value)}
                            className="w-full border border-ink/15 py-2 pr-3 pl-9 text-sm outline-none focus:border-signal sm:w-64"
                        />
                    </div>
                </div>

                <div className="admin-toolbar-actions">
                    <button
                        type="button"
                        onClick={() => change({ router_id: selected_router_id })}
                        className="btn-action btn-action-sm btn-sync"
                    >
                        <RefreshCw className="mr-1.5 h-4 w-4" />
                        Refresh
                    </button>
                </div>
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                {TABS.map((item) => {
                    const Icon = item.icon;
                    const active = tab === item.id;
                    return (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => change({ tab: item.id })}
                            className={`btn-action btn-action-sm ${
                                active ? 'btn-primary' : 'btn-secondary'
                            }`}
                        >
                            <Icon className="mr-1.5 h-4 w-4" />
                            {item.label}
                        </button>
                    );
                })}
                <span className="self-center text-xs text-ink-soft">{count} item</span>
            </div>

            {tab === 'hosts' && (
                <div className="mb-4 flex flex-wrap gap-2">
                    {[
                        { id: 'all', label: 'Semua' },
                        { id: 'authorized', label: 'Authorized (A)' },
                        { id: 'bypassed', label: 'Bypassed (P)' },
                    ].map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => change({ host_filter: item.id })}
                            className={`btn-action btn-action-xs ${
                                host_filter === item.id ? 'btn-primary' : 'btn-secondary'
                            }`}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            )}

            {tab === 'bindings' && (
                <form
                    onSubmit={submitBinding}
                    className="mb-4 grid gap-3 border border-ink/10 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6"
                >
                    <label className="block text-sm font-medium text-ink">
                        MAC
                        <input
                            value={data.mac_address}
                            onChange={(e) => setData('mac_address', e.target.value)}
                            className={fieldClass}
                            placeholder="AA:BB:CC:DD:EE:FF"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Address
                        <input
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            className={fieldClass}
                            placeholder="10.10.10.20"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        To Address
                        <input
                            value={data.to_address}
                            onChange={(e) => setData('to_address', e.target.value)}
                            className={fieldClass}
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Tipe
                        <select
                            value={data.type}
                            onChange={(e) => setData('type', e.target.value)}
                            className={fieldClass}
                        >
                            <option value="bypassed">Bypassed</option>
                            <option value="regular">Regular</option>
                            <option value="blocked">Blocked</option>
                        </select>
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Server
                        <select
                            value={data.server}
                            onChange={(e) => setData('server', e.target.value)}
                            className={fieldClass}
                        >
                            <option value="all">all</option>
                            {servers.map((server) => (
                                <option key={server.name} value={server.name}>
                                    {server.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="flex items-end">
                        <button
                            type="submit"
                            disabled={processing || !selected_router_id}
                            className="btn-action btn-action-sm btn-primary w-full"
                        >
                            Tambah binding
                        </button>
                    </div>
                </form>
            )}

            <div className="admin-data-scroll border border-ink/10 bg-white">
                {tab === 'hosts' && (
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-3 font-semibold">Flag</th>
                                <th className="px-4 py-3 font-semibold">MAC</th>
                                <th className="px-4 py-3 font-semibold">Address</th>
                                <th className="hidden px-4 py-3 font-semibold md:table-cell">
                                    To Address
                                </th>
                                <th className="hidden px-4 py-3 font-semibold lg:table-cell">
                                    Server
                                </th>
                                <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {hostRows.map((host) => (
                                <tr key={host.id} className="border-b border-ink/5 last:border-0">
                                    <td className="px-4 py-3 font-mono text-xs font-semibold text-signal-deep">
                                        {host.flags || '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {host.mac_address || '—'}
                                    </td>
                                    <td className="px-4 py-3">{host.address || '—'}</td>
                                    <td className="hidden px-4 py-3 md:table-cell">
                                        {host.to_address || '—'}
                                    </td>
                                    <td className="hidden px-4 py-3 lg:table-cell">
                                        {host.server || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="admin-actions">
                                            <button
                                                type="button"
                                                onClick={() => bindHost(host)}
                                                className="btn-action btn-action-xs btn-secondary"
                                            >
                                                Binding
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => removeHost(host)}
                                                className="btn-action btn-action-xs btn-danger"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {hostRows.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-ink-soft">
                                        Tidak ada host hotspot.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                )}

                {tab === 'cookies' && (
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-3 font-semibold">User</th>
                                <th className="px-4 py-3 font-semibold">MAC</th>
                                <th className="hidden px-4 py-3 font-semibold md:table-cell">
                                    Domain
                                </th>
                                <th className="hidden px-4 py-3 font-semibold lg:table-cell">
                                    Expires
                                </th>
                                <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {cookieRows.map((cookie) => (
                                <tr key={cookie.id} className="border-b border-ink/5 last:border-0">
                                    <td className="px-4 py-3 font-mono">{cookie.user || '—'}</td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {cookie.mac_address || '—'}
                                    </td>
                                    <td className="hidden px-4 py-3 md:table-cell">
                                        {cookie.domain || '—'}
                                    </td>
                                    <td className="hidden px-4 py-3 lg:table-cell">
                                        {cookie.expires_in || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="admin-actions">
                                            <button
                                                type="button"
                                                onClick={() => removeCookie(cookie)}
                                                className="btn-action btn-action-xs btn-danger"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {cookieRows.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-10 text-center text-ink-soft">
                                        Tidak ada cookie hotspot.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                )}

                {tab === 'bindings' && (
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-3 font-semibold">Nama</th>
                                <th className="px-4 py-3 font-semibold">MAC</th>
                                <th className="px-4 py-3 font-semibold">Address</th>
                                <th className="hidden px-4 py-3 font-semibold md:table-cell">
                                    Tipe
                                </th>
                                <th className="px-4 py-3 font-semibold">Status</th>
                                <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {bindingRows.map((binding) => (
                                <tr
                                    key={binding.id}
                                    className="border-b border-ink/5 last:border-0"
                                >
                                    <td className="px-4 py-3">{binding.comment || '—'}</td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {binding.mac_address || '—'}
                                    </td>
                                    <td className="px-4 py-3">{binding.address || '—'}</td>
                                    <td className="hidden px-4 py-3 md:table-cell">
                                        {bindingTypeLabel(binding.type)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {binding.disabled ? (
                                            <span className="bg-ink/10 px-2 py-1 text-xs font-semibold text-ink-soft">
                                                Nonaktif
                                            </span>
                                        ) : (
                                            <span className="bg-signal/15 px-2 py-1 text-xs font-semibold text-signal-deep">
                                                Aktif
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="admin-actions">
                                            <button
                                                type="button"
                                                onClick={() => toggleBinding(binding)}
                                                className="btn-action btn-action-xs btn-warn"
                                            >
                                                {binding.disabled ? 'Aktifkan' : 'Nonaktif'}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => removeBinding(binding)}
                                                className="btn-action btn-action-xs btn-danger"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {bindingRows.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-10 text-center text-ink-soft">
                                        Tidak ada IP binding.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                )}

                {tab === 'log' && (
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-3 font-semibold">Waktu</th>
                                <th className="px-4 py-3 font-semibold">User (IP)</th>
                                <th className="px-4 py-3 font-semibold">Pesan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logRows.map((row, index) => (
                                <tr
                                    key={row.id || `${row.time}-${index}`}
                                    className="border-b border-ink/5 last:border-0"
                                >
                                    <td className="px-4 py-3 whitespace-nowrap text-xs">
                                        {row.time || '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {row.user || '—'}
                                    </td>
                                    <td className="px-4 py-3">{row.message || '—'}</td>
                                </tr>
                            ))}
                            {logRows.length === 0 && (
                                <tr>
                                    <td colSpan={3} className="px-4 py-10 text-center text-ink-soft">
                                        Tidak ada log hotspot.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                )}
            </div>
        </AdminLayout>
    );
}
