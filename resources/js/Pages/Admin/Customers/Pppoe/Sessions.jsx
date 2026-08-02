import { Head, Link, router, useForm } from '@inertiajs/react';
import { Activity, UserPlus, RefreshCw, Search, Unplug, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import StatCard from '../../../../Components/Admin/StatCard';
import AdminLayout from '../../../../Layouts/AdminLayout';
import useDebouncedCallback from '../../../../hooks/useDebouncedCallback';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal';

function todayIso() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export default function Sessions({
    routers,
    selected_router_id,
    sessions,
    filters,
    stats,
    error,
    packages = [],
    isolir_profiles = [],
    defaults = {},
}) {
    const [selected, setSelected] = useState([]);
    const [showImport, setShowImport] = useState(false);
    const [loadingSecret, setLoadingSecret] = useState(false);
    const [onlyUnknown, setOnlyUnknown] = useState(false);

    const visibleSessions = useMemo(() => {
        if (!onlyUnknown) return sessions;
        return sessions.filter((s) => !s.customer_id);
    }, [sessions, onlyUnknown]);

    const unknownUsernames = useMemo(
        () => sessions.filter((s) => !s.customer_id).map((s) => s.name).filter(Boolean),
        [sessions],
    );

    const importForm = useForm({
        mikrotik_router_id: selected_router_id || '',
        subscription_package_id: '',
        usernames: [],
        password: '',
        start_date: defaults.start_date || todayIso(),
        billing_day: defaults.billing_day || 1,
        overdue_action: defaults.overdue_action || 'isolir',
        isolir_profile: isolir_profiles[0]?.name || '',
    });

    const changeRouter = (routerId) => {
        setSelected([]);
        setShowImport(false);
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

    const searchLive = useDebouncedCallback((value) => {
        applySearch(value);
    });

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

    const registerUrl = (username) => {
        const params = new URLSearchParams({
            router_id: String(selected_router_id || ''),
            username: username || '',
        });
        return `/admin/customers/pppoe/create?${params.toString()}`;
    };

    const toggleOne = (username) => {
        setSelected((prev) =>
            prev.includes(username)
                ? prev.filter((item) => item !== username)
                : [...prev, username],
        );
    };

    const toggleAllUnknown = () => {
        if (selected.length === unknownUsernames.length) {
            setSelected([]);
            return;
        }
        setSelected([...unknownUsernames]);
    };

    const openImport = async (usernames) => {
        const list = usernames.filter(Boolean);
        if (!selected_router_id || list.length === 0) return;

        let password = importForm.data.password || '';
        let packageId = importForm.data.subscription_package_id || '';

        setShowImport(true);
        setLoadingSecret(true);

        try {
            const response = await fetch(
                `/admin/customers/pppoe/secret?router_id=${encodeURIComponent(selected_router_id)}&username=${encodeURIComponent(list[0])}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );
            const payload = await response.json();
            if (payload.ok && payload.secret) {
                if (payload.secret.password) {
                    password = payload.secret.password;
                }
                if (!packageId && payload.secret.profile) {
                    const matched = packages.find(
                        (pkg) => pkg.mikrotik_profile === payload.secret.profile,
                    );
                    if (matched) packageId = matched.id;
                }
            }
        } catch {
            // Password bisa diisi manual.
        } finally {
            setLoadingSecret(false);
        }

        importForm.setData({
            mikrotik_router_id: selected_router_id,
            subscription_package_id: packageId,
            usernames: list,
            password,
            start_date: defaults.start_date || todayIso(),
            billing_day: defaults.billing_day || 1,
            overdue_action: defaults.overdue_action || 'isolir',
            isolir_profile: isolir_profiles[0]?.name || importForm.data.isolir_profile || '',
        });
    };

    const submitImport = (e) => {
        e.preventDefault();
        importForm.post('/admin/customers/pppoe/import-sessions', {
            preserveScroll: true,
            onSuccess: () => {
                setShowImport(false);
                setSelected([]);
            },
        });
    };

    return (
        <AdminLayout
            title="Sesi Aktif PPPoE"
            subtitle="Monitor sesi online dan daftarkan pelanggan yang belum ada di app"
        >
            <Head title="Sesi Aktif PPPoE" />
            {error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}

            <div className="mb-5 grid items-stretch gap-3 sm:grid-cols-3">
                <StatCard
                    label="Online sekarang"
                    value={stats.online}
                    tone="emerald"
                    icon={Activity}
                />
                <StatCard
                    label="Terdaftar di app"
                    value={stats.matched}
                    tone="cyan"
                    icon={Users}
                />
                <StatCard
                    label="Belum terdaftar"
                    value={stats.unknown}
                    tone="amber"
                    icon={UserPlus}
                />
            </div>

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
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
                            onChange={(e) => searchLive(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    searchLive.cancel();
                                    applySearch(e.target.value);
                                }
                            }}
                            className="w-64 border border-ink/15 py-2 pr-3 pl-9 text-sm outline-none focus:border-signal"
                        />
                    </div>

                    <label className="inline-flex items-center gap-2 border border-ink/10 bg-white px-3 py-2 text-xs font-semibold text-ink">
                        <input
                            type="checkbox"
                            checked={onlyUnknown}
                            onChange={(e) => setOnlyUnknown(e.target.checked)}
                            className="accent-signal-deep"
                        />
                        Hanya belum terdaftar
                    </label>
                </div>

                <div className="admin-toolbar-actions">
                    {unknownUsernames.length > 0 && (
                        <>
                            <button
                                type="button"
                                onClick={() => openImport(selected.length ? selected : unknownUsernames)}
                                className="btn-action btn-action-xs btn-warn"
                            >
                                <Users className="mr-1.5 h-3.5 w-3.5 text-amber-700" />
                                {selected.length
                                    ? `Daftarkan terpilih (${selected.length})`
                                    : `Daftarkan semua (${unknownUsernames.length})`}
                            </button>
                        </>
                    )}
                    <button
                        type="button"
                        onClick={refresh}
                        className="btn-action btn-action-xs btn-sync"
                    >
                        <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                        Refresh
                    </button>
                </div>
            </div>

            {showImport && (
                <form
                    onSubmit={submitImport}
                    className="mb-5 space-y-4 border border-amber-200 bg-white p-5 sm:p-6"
                >
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold text-ink">
                                Impor pelanggan dari sesi aktif
                            </h2>
                            <p className="mt-0.5 text-xs text-ink-soft">
                                {importForm.data.usernames.length} username akan didaftarkan. Password
                                secret dipakai bersama untuk semua akun.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => setShowImport(false)}
                            className="text-xs font-semibold text-ink-soft hover:text-ink"
                        >
                            Tutup
                        </button>
                    </div>

                    <div className="flex flex-wrap gap-1.5">
                        {importForm.data.usernames.map((username) => (
                            <span
                                key={username}
                                className="bg-amber-50 px-2 py-1 text-xs font-medium text-amber-900"
                            >
                                {username}
                            </span>
                        ))}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            Paket langganan
                            <select
                                value={importForm.data.subscription_package_id}
                                onChange={(e) =>
                                    importForm.setData('subscription_package_id', e.target.value)
                                }
                                className={fieldClass}
                                required
                            >
                                <option value="">Pilih paket</option>
                                {packages.map((pkg) => (
                                    <option key={pkg.id} value={pkg.id}>
                                        {pkg.name} — {pkg.price_label}
                                    </option>
                                ))}
                            </select>
                            {importForm.errors.subscription_package_id && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {importForm.errors.subscription_package_id}
                                </span>
                            )}
                        </label>

                        <label className="block text-sm font-medium text-ink">
                            Password secret (sama untuk semua)
                            <input
                                type="text"
                                value={importForm.data.password}
                                onChange={(e) => importForm.setData('password', e.target.value)}
                                className={fieldClass}
                                required
                                placeholder={
                                    loadingSecret ? 'Mengambil dari MikroTik...' : 'Password PPPoE'
                                }
                            />
                            {importForm.errors.password && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {importForm.errors.password}
                                </span>
                            )}
                        </label>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <label className="block text-sm font-medium text-ink">
                            Tanggal mulai
                            <input
                                type="date"
                                value={importForm.data.start_date}
                                onChange={(e) => importForm.setData('start_date', e.target.value)}
                                className={fieldClass}
                                required
                            />
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            Tanggal tagihan
                            <select
                                value={importForm.data.billing_day}
                                onChange={(e) =>
                                    importForm.setData('billing_day', Number(e.target.value))
                                }
                                className={fieldClass}
                                required
                            >
                                {Array.from({ length: 28 }, (_, i) => i + 1).map((day) => (
                                    <option key={day} value={day}>
                                        Tanggal {day}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            Aksi tunggakan
                            <select
                                value={importForm.data.overdue_action}
                                onChange={(e) =>
                                    importForm.setData('overdue_action', e.target.value)
                                }
                                className={fieldClass}
                                required
                            >
                                <option value="isolir">Isolir</option>
                                <option value="bypass">Bypass</option>
                            </select>
                        </label>
                    </div>

                    {importForm.data.overdue_action === 'isolir' && (
                        <label className="block text-sm font-medium text-ink">
                            Profil isolir
                            {isolir_profiles.length > 0 ? (
                                <select
                                    value={importForm.data.isolir_profile}
                                    onChange={(e) =>
                                        importForm.setData('isolir_profile', e.target.value)
                                    }
                                    className={fieldClass}
                                    required
                                >
                                    <option value="">Pilih profil isolir</option>
                                    {isolir_profiles.map((profile) => (
                                        <option key={profile.name} value={profile.name}>
                                            {profile.name}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <input
                                    type="text"
                                    value={importForm.data.isolir_profile}
                                    onChange={(e) =>
                                        importForm.setData('isolir_profile', e.target.value)
                                    }
                                    className={fieldClass}
                                    required
                                    placeholder="Nama profil isolir di RouterOS"
                                />
                            )}
                            {importForm.errors.isolir_profile && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {importForm.errors.isolir_profile}
                                </span>
                            )}
                        </label>
                    )}

                    <button
                        type="submit"
                        disabled={importForm.processing || loadingSecret}
                        className="btn-action btn-action-sm btn-warn-solid"
                    >
                        {importForm.processing
                            ? 'Mengimpor...'
                            : `Daftarkan ${importForm.data.usernames.length} pelanggan`}
                    </button>
                </form>
            )}

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-3 py-3 font-semibold">
                                {unknownUsernames.length > 0 && (
                                    <input
                                        type="checkbox"
                                        checked={
                                            selected.length > 0 &&
                                            selected.length === unknownUsernames.length
                                        }
                                        onChange={toggleAllUnknown}
                                        className="accent-signal-deep"
                                        title="Pilih semua belum terdaftar"
                                    />
                                )}
                            </th>
                            <th className="px-4 py-3 font-semibold">Username</th>
                            <th className="px-4 py-3 font-semibold">Pelanggan</th>
                            <th className="hidden px-4 py-3 font-semibold md:table-cell">IP</th>
                            <th className="hidden px-4 py-3 font-semibold lg:table-cell">Caller ID</th>
                            <th className="px-4 py-3 font-semibold">Uptime</th>
                            <th className="hidden px-4 py-3 font-semibold xl:table-cell">Service</th>
                            <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibleSessions.map((session) => (
                            <tr key={session.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-3 py-3">
                                    {!session.customer_id && (
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(session.name)}
                                            onChange={() => toggleOne(session.name)}
                                            className="accent-signal-deep"
                                        />
                                    )}
                                </td>
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
                                        {!session.customer_id && (
                                            <Link
                                                href={registerUrl(session.name)}
                                                className="btn-action btn-action-xs btn-warn"
                                            >
                                                <UserPlus className="h-3.5 w-3.5 text-amber-700" />
                                                Daftarkan
                                            </Link>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => disconnect(session)}
                                            className="btn-action btn-action-xs btn-danger"
                                        >
                                            <Unplug className="h-3.5 w-3.5" />
                                            Putus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {visibleSessions.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-10 text-center text-ink-soft">
                                    Tidak ada sesi PPPoE aktif
                                    {filters.q ? ' untuk pencarian ini' : ' di router ini'}
                                    {onlyUnknown ? ' yang belum terdaftar' : ''}.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
