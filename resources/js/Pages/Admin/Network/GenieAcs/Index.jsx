import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    ExternalLink,
    Eye,
    Radio,
    RefreshCw,
    Search,
    ServerCrash,
    Settings2,
    ShieldAlert,
    Wifi,
} from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal disabled:bg-mist';

function StatCard({ label, value, icon: Icon, tone }) {
    return (
        <div className={`flex min-h-[110px] flex-col p-4 text-white shadow-sm ${tone}`}>
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] tracking-wide text-white/75 uppercase">{label}</p>
                <span className="inline-flex h-9 w-9 items-center justify-center bg-white/15">
                    <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                </span>
            </div>
            <p className="font-display mt-3 text-2xl font-bold">{value}</p>
        </div>
    );
}

export default function Index({ config, connection, devices, devices_error, stats, filters }) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const [q, setQ] = useState(filters?.q || '');
    const [showSettings, setShowSettings] = useState(false);

    const { data, setData, post, processing, errors, transform } = useForm({
        genieacs_enabled: Boolean(config?.enabled),
        genieacs_nbi_url: config?.nbi_url || 'http://127.0.0.1:7557',
        genieacs_ui_url: config?.ui_url || 'http://127.0.0.1:3000',
        genieacs_api_key: '',
        genieacs_username: config?.username || '',
        genieacs_password: '',
    });

    const saveSettings = (e) => {
        e.preventDefault();
        if (!canWrite) return;

        transform((form) => {
            const payload = { ...form };
            if (!payload.genieacs_api_key) delete payload.genieacs_api_key;
            if (!payload.genieacs_password) delete payload.genieacs_password;
            return payload;
        });

        post('/admin/network/genieacs/settings');
    };

    const search = (e) => {
        e.preventDefault();
        router.get('/admin/network/genieacs', { q }, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout
            title="GenieACS"
            subtitle="Integrasi TR-069 / CWMP untuk manajemen ONT & CPE"
        >
            <Head title="GenieACS" />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Hubungkan aplikasi ke GenieACS NBI (default port <strong>7557</strong>). Setelah
                    aktif, daftar perangkat CPE dapat dipantau dan di-summon dari panel ini.
                </p>
                <div className="admin-toolbar-actions">
                    {config?.ui_url && (
                        <a
                            href={config.ui_url}
                            target="_blank"
                            rel="noreferrer"
                            className="border border-ink/15 bg-white px-3 text-xs font-semibold text-ink hover:bg-mist"
                        >
                            <ExternalLink className="mr-1.5 h-3.5 w-3.5" />
                            Buka UI GenieACS
                        </a>
                    )}
                    <button
                        type="button"
                        onClick={() => setShowSettings((v) => !v)}
                        className="border border-ink/15 bg-white px-3 text-xs font-semibold text-ink hover:bg-mist"
                    >
                        <Settings2 className="mr-1.5 h-3.5 w-3.5" />
                        {showSettings ? 'Sembunyikan pengaturan' : 'Pengaturan koneksi'}
                    </button>
                    {canWrite && (
                        <button
                            type="button"
                            onClick={() => router.post('/admin/network/genieacs/test')}
                            className="bg-signal-deep px-3 text-xs font-semibold text-white hover:bg-ink"
                        >
                            <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                            Tes koneksi
                        </button>
                    )}
                </div>
            </div>

            {showSettings && (
                <form
                    onSubmit={saveSettings}
                    className="mb-5 space-y-4 border border-ink/10 bg-white p-5 sm:p-6"
                >
                    <div className="flex items-start gap-3">
                        <span className="inline-flex h-9 w-9 items-center justify-center bg-gradient-to-br from-sky-500 to-blue-800 text-white">
                            <Radio className="h-4 w-4" />
                        </span>
                        <div>
                            <h2 className="text-sm font-semibold text-ink">Pengaturan koneksi GenieACS</h2>
                            <p className="mt-0.5 text-xs text-ink-soft">
                                Isi URL NBI dari server GenieACS Anda. API key opsional jika
                                `NBI_AUTHENTICATION_KEY` aktif.
                            </p>
                        </div>
                    </div>

                    <label className="flex items-center justify-between gap-4 border border-ink/10 px-4 py-3">
                        <span>
                            <span className="block text-sm font-medium text-ink">Aktifkan integrasi</span>
                            <span className="mt-0.5 block text-xs text-ink-soft">
                                Jika nonaktif, daftar perangkat tidak diambil dari GenieACS.
                            </span>
                        </span>
                        <input
                            type="checkbox"
                            checked={data.genieacs_enabled}
                            disabled={!canWrite}
                            onChange={(e) => setData('genieacs_enabled', e.target.checked)}
                            className="h-4 w-4 accent-signal-deep"
                        />
                    </label>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            URL NBI (API)
                            <input
                                type="url"
                                value={data.genieacs_nbi_url}
                                onChange={(e) => setData('genieacs_nbi_url', e.target.value)}
                                className={fieldClass}
                                placeholder="http://127.0.0.1:7557"
                                disabled={!canWrite}
                                required
                            />
                            {errors.genieacs_nbi_url && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.genieacs_nbi_url}
                                </span>
                            )}
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            URL UI GenieACS (opsional)
                            <input
                                type="url"
                                value={data.genieacs_ui_url}
                                onChange={(e) => setData('genieacs_ui_url', e.target.value)}
                                className={fieldClass}
                                placeholder="http://127.0.0.1:3000"
                                disabled={!canWrite}
                            />
                        </label>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <label className="block text-sm font-medium text-ink">
                            API Key (x-api-key)
                            <input
                                type="password"
                                value={data.genieacs_api_key}
                                onChange={(e) => setData('genieacs_api_key', e.target.value)}
                                className={fieldClass}
                                placeholder={config?.has_api_key ? 'Tersimpan (isi untuk ganti)' : 'Opsional'}
                                disabled={!canWrite}
                                autoComplete="new-password"
                            />
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            Basic auth user
                            <input
                                type="text"
                                value={data.genieacs_username}
                                onChange={(e) => setData('genieacs_username', e.target.value)}
                                className={fieldClass}
                                placeholder="Opsional (jika di balik proxy)"
                                disabled={!canWrite}
                            />
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            Basic auth password
                            <input
                                type="password"
                                value={data.genieacs_password}
                                onChange={(e) => setData('genieacs_password', e.target.value)}
                                className={fieldClass}
                                placeholder={config?.has_password ? 'Tersimpan (isi untuk ganti)' : 'Opsional'}
                                disabled={!canWrite}
                                autoComplete="new-password"
                            />
                        </label>
                    </div>

                    {canWrite && (
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-signal-deep px-4 py-2.5 text-sm font-semibold text-white hover:bg-ink disabled:opacity-60"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan pengaturan'}
                        </button>
                    )}
                </form>
            )}

            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Status Koneksi"
                    value={connection?.ok ? 'Terhubung' : 'Terputus'}
                    icon={connection?.ok ? Wifi : ServerCrash}
                    tone={
                        connection?.ok
                            ? 'bg-gradient-to-br from-emerald-500 to-teal-800'
                            : 'bg-gradient-to-br from-red-500 to-red-900'
                    }
                />
                <StatCard
                    label="Perangkat"
                    value={stats?.devices ?? 0}
                    icon={Radio}
                    tone="bg-gradient-to-br from-sky-500 to-blue-800"
                />
                <StatCard
                    label="Online (~15 mnt)"
                    value={stats?.online ?? 0}
                    icon={Activity}
                    tone="bg-gradient-to-br from-lime-400 to-green-800"
                />
                <StatCard
                    label="Faults"
                    value={stats?.faults ?? 0}
                    icon={ShieldAlert}
                    tone="bg-gradient-to-br from-amber-400 to-orange-700"
                />
            </div>

            <div className="mb-4 border border-ink/10 bg-white px-4 py-3 text-sm text-ink-soft">
                {connection?.message || 'Belum ada status koneksi.'}
                {connection?.latency_ms != null && ` · Latency ${connection.latency_ms} ms`}
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <form
                    onSubmit={search}
                    className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-end"
                >
                    <label className="block w-full text-sm text-ink sm:w-auto">
                        <span className="mb-1 block text-xs font-semibold text-ink-soft">Cari perangkat</span>
                        <span className="relative block">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft" />
                            <input
                                type="search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="ID, serial, manufacturer, tag"
                                className="w-full border border-ink/15 py-2.5 pr-3 pl-9 text-sm outline-none focus:border-signal sm:w-64"
                            />
                        </span>
                    </label>
                    <button
                        type="submit"
                        className="border border-ink/15 bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-mist"
                    >
                        Cari
                    </button>
                </form>
                <div className="admin-toolbar-actions">
                    <button
                        type="button"
                        onClick={() => router.reload({ only: ['devices', 'stats', 'connection'] })}
                        className="border border-ink/15 bg-white px-3 text-xs font-semibold text-ink hover:bg-mist"
                    >
                        <RefreshCw className="mr-1.5 h-3.5 w-3.5" />
                        Refresh daftar
                    </button>
                </div>
            </div>

            {devices_error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {devices_error}
                </div>
            )}

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Perangkat</th>
                            <th className="px-4 py-3 font-semibold">Model</th>
                            <th className="hidden px-4 py-3 font-semibold md:table-cell">Firmware</th>
                            <th className="px-4 py-3 font-semibold">Inform terakhir</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        {devices.map((item) => (
                            <tr key={item.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{item.serial || item.id}</p>
                                    <p className="text-xs text-ink-soft">{item.manufacturer || '—'}</p>
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{item.model || '—'}</td>
                                <td className="hidden px-4 py-3 text-ink-soft md:table-cell">
                                    {item.software_version || '—'}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{item.last_inform_label}</td>
                                <td className="px-4 py-3">
                                    <span
                                        className={`px-2 py-1 text-xs font-semibold ${
                                            item.online
                                                ? 'bg-signal/15 text-signal-deep'
                                                : 'bg-ink/10 text-ink-soft'
                                        }`}
                                    >
                                        {item.online ? 'Online' : 'Offline'}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="admin-actions">
                                        <Link
                                            href={`/admin/network/genieacs/devices/${encodeURIComponent(item.id)}`}
                                            className="inline-flex items-center gap-1 border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-signal-deep hover:bg-mist"
                                        >
                                            <Eye className="h-3.5 w-3.5" />
                                            Detail
                                        </Link>
                                        {canWrite && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        `/admin/network/genieacs/devices/${encodeURIComponent(item.id)}/summon`,
                                                    )
                                                }
                                                className="inline-flex items-center gap-1 border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-ink hover:bg-mist"
                                            >
                                                <RefreshCw className="h-3.5 w-3.5" />
                                                Summon
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {devices.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-10 text-center text-ink-soft">
                                    {config?.enabled
                                        ? 'Belum ada perangkat yang cocok, atau GenieACS belum menerima inform.'
                                        : 'Aktifkan integrasi GenieACS pada pengaturan koneksi untuk menampilkan perangkat.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
