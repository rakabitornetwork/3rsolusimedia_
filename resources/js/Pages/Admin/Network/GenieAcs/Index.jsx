import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    ExternalLink,
    Eye,
    EyeOff,
    KeyRound,
    Radio,
    RefreshCw,
    Search,
    ServerCrash,
    Settings2,
    ShieldAlert,
    Signal,
    Thermometer,
    Wifi,
} from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import {
    faultsTone,
    onlineTone,
    rxPowerTone,
    temperatureTone,
} from '../../../../Utils/genieacsMetrics';

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

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

function MetricPill({ icon: Icon, label, tone, title }) {
    return (
        <span
            title={title}
            className={`inline-flex items-center gap-1.5 px-2 py-1 text-xs font-semibold ${tone.badge}`}
        >
            <Icon className={`h-3.5 w-3.5 shrink-0 ${tone.icon}`} strokeWidth={2} />
            <span>{label}</span>
        </span>
    );
}

function SsidPasswordCell({ password }) {
    const [visible, setVisible] = useState(false);

    if (!password) {
        return (
            <span className="inline-flex items-center gap-1.5 text-xs text-ink-soft">
                <KeyRound className="h-3.5 w-3.5" />
                —
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1.5">
            <KeyRound className="h-3.5 w-3.5 shrink-0 text-sky-600" />
            <span className="font-mono text-xs text-ink">{visible ? password : '••••••••'}</span>
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                className="inline-flex text-ink-soft hover:text-ink"
                title={visible ? 'Sembunyikan password' : 'Tampilkan password'}
            >
                {visible ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
            </button>
        </span>
    );
}

export default function Index({ config, connection, devices, devices_error, stats, filters }) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const [q, setQ] = useState(filters?.q || '');
    const [showSettings, setShowSettings] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const rows = devices?.data || [];
    const perPage = filters?.per_page || 10;
    const currentPage = devices?.current_page || 1;
    const faultAccent = faultsTone(stats?.faults);

    const { data, setData, post, processing, errors, transform } = useForm({
        genieacs_enabled: config?.enabled ?? Boolean(config?.nbi_url),
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

    const browse = (params = {}, options = {}) => {
        router.get(
            '/admin/network/genieacs',
            {
                q: q || undefined,
                per_page: perPage,
                page: currentPage,
                ...params,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                ...options,
            },
        );
    };

    const search = (e) => {
        e.preventDefault();
        browse({ page: 1, q });
    };

    const refreshList = () => {
        if (refreshing) return;
        setRefreshing(true);
        browse(
            {},
            {
                onFinish: () => setRefreshing(false),
            },
        );
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
                            <ExternalLink className="mr-1.5 h-3.5 w-3.5 text-sky-600" />
                            Buka UI GenieACS
                        </a>
                    )}
                    <button
                        type="button"
                        onClick={() => setShowSettings((v) => !v)}
                        className="border border-ink/15 bg-white px-3 text-xs font-semibold text-ink hover:bg-mist"
                    >
                        <Settings2 className="mr-1.5 h-3.5 w-3.5 text-slate-600" />
                        {showSettings ? 'Sembunyikan pengaturan' : 'Pengaturan koneksi'}
                    </button>
                    {canWrite && (
                        <button
                            type="button"
                            onClick={() => router.post('/admin/network/genieacs/test')}
                            className="bg-signal-deep px-3 text-xs font-semibold text-white hover:bg-ink"
                        >
                            <Wifi className="mr-1.5 h-3.5 w-3.5" />
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
                        <span className="inline-flex h-9 w-9 items-center justify-center bg-gradient-to-br from-cyan-400 to-sky-600 text-white">
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
                                Tandai sebagai aktif di panel. Daftar perangkat diambil otomatis
                                selama URL NBI dapat dihubungi.
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
                            ? 'bg-gradient-to-br from-emerald-400 to-teal-600'
                            : 'bg-gradient-to-br from-rose-400 to-pink-600'
                    }
                />
                <StatCard
                    label="Perangkat"
                    value={stats?.devices ?? 0}
                    icon={Radio}
                    tone="bg-gradient-to-br from-cyan-400 to-sky-600"
                />
                <StatCard
                    label="Online (~15 mnt)"
                    value={stats?.online ?? 0}
                    icon={Activity}
                    tone="bg-gradient-to-br from-teal-400 to-cyan-600"
                />
                <StatCard
                    label="Faults"
                    value={stats?.faults ?? 0}
                    icon={ShieldAlert}
                    tone={`bg-gradient-to-br ${faultAccent.accent}`}
                />
            </div>

            <div
                className={`mb-4 flex items-start gap-3 border px-4 py-3 text-sm ${
                    connection?.ok
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        : 'border-rose-200 bg-rose-50 text-rose-800'
                }`}
            >
                {connection?.ok ? (
                    <Wifi className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                ) : (
                    <ServerCrash className="mt-0.5 h-4 w-4 shrink-0 text-rose-600" />
                )}
                <span>
                    {connection?.message || 'Belum ada status koneksi.'}
                    {connection?.latency_ms != null && ` · Latency ${connection.latency_ms} ms`}
                </span>
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
                                placeholder="ID, serial, SSID, manufacturer"
                                className="w-full border border-ink/15 py-2.5 pr-3 pl-9 text-sm outline-none focus:border-signal sm:w-64"
                            />
                        </span>
                    </label>
                    <label className="block w-full text-sm text-ink sm:w-auto">
                        <span className="mb-1 block text-xs font-semibold text-ink-soft">Baris / halaman</span>
                        <select
                            value={perPage}
                            onChange={(e) => browse({ page: 1, per_page: Number(e.target.value) })}
                            className="w-full border border-ink/15 bg-white py-2.5 pr-8 pl-3 text-sm outline-none focus:border-signal sm:w-28"
                        >
                            {PER_PAGE_OPTIONS.map((n) => (
                                <option key={n} value={n}>
                                    {n}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button
                        type="submit"
                        className="inline-flex items-center justify-center gap-1.5 border border-ink/15 bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-mist"
                    >
                        <Search className="h-3.5 w-3.5 text-sky-600" />
                        Cari
                    </button>
                </form>
                <div className="admin-toolbar-actions">
                    <button
                        type="button"
                        onClick={refreshList}
                        disabled={refreshing}
                        className="border border-cyan-200 bg-cyan-50 px-3 text-xs font-semibold text-cyan-800 hover:bg-cyan-100 disabled:opacity-60"
                    >
                        <RefreshCw
                            className={`mr-1.5 h-3.5 w-3.5 text-cyan-600 ${refreshing ? 'animate-spin' : ''}`}
                        />
                        {refreshing ? 'Memuat...' : 'Refresh daftar'}
                    </button>
                </div>
            </div>

            {devices_error && (
                <div className="mb-4 flex items-start gap-3 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    <ServerCrash className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{devices_error}</span>
                </div>
            )}

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Perangkat</th>
                            <th className="px-4 py-3 font-semibold">Model</th>
                            <th className="px-4 py-3 font-semibold">Suhu</th>
                            <th className="px-4 py-3 font-semibold">RX Power</th>
                            <th className="px-4 py-3 font-semibold">SSID</th>
                            <th className="px-4 py-3 font-semibold">Password SSID</th>
                            <th className="px-4 py-3 font-semibold">Inform</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((item) => {
                            const temp = temperatureTone(item.temperature);
                            const rx = rxPowerTone(item.rx_power);
                            const status = onlineTone(item.online);

                            return (
                                <tr key={item.id} className="border-b border-ink/5 last:border-0">
                                    <td className="px-4 py-3">
                                        <div className="flex items-start gap-2">
                                            <Radio className="mt-0.5 h-4 w-4 shrink-0 text-cyan-600" />
                                            <div>
                                                <p className="font-medium text-ink">{item.serial || item.id}</p>
                                                <p className="text-xs text-ink-soft">
                                                    {item.manufacturer || '—'}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-ink-soft">{item.model || '—'}</td>
                                    <td className="px-4 py-3">
                                        <MetricPill
                                            icon={Thermometer}
                                            label={item.temperature_label || '—'}
                                            tone={temp}
                                            title="Suhu transceiver / ONT"
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <MetricPill
                                            icon={Signal}
                                            label={item.rx_power_label || '—'}
                                            tone={rx}
                                            title="RX optical power"
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="inline-flex items-center gap-1.5 text-ink">
                                            <Wifi className="h-3.5 w-3.5 shrink-0 text-sky-600" />
                                            {item.ssid || '—'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <SsidPasswordCell password={item.ssid_password} />
                                    </td>
                                    <td className="px-4 py-3 text-ink-soft">{item.last_inform_label}</td>
                                    <td className="px-4 py-3">
                                        <MetricPill
                                            icon={item.online ? Activity : ServerCrash}
                                            label={item.online ? 'Online' : 'Offline'}
                                            tone={status}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="admin-actions">
                                            <Link
                                                href={`/admin/network/genieacs/devices/${encodeURIComponent(item.id)}`}
                                                className="inline-flex items-center gap-1 border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-800 hover:bg-sky-100"
                                            >
                                                <Eye className="h-3.5 w-3.5 text-sky-600" />
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
                                                    className="inline-flex items-center gap-1 border border-teal-200 bg-teal-50 px-2.5 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-100"
                                                >
                                                    <RefreshCw className="h-3.5 w-3.5 text-teal-600" />
                                                    Summon
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={9} className="px-4 py-10 text-center text-ink-soft">
                                    {connection?.ok
                                        ? 'Belum ada perangkat yang cocok, atau GenieACS belum menerima inform.'
                                        : 'Atur URL NBI GenieACS (port 7557) di Pengaturan koneksi, lalu Tes koneksi.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {devices?.total > 0 && (
                <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-xs text-ink-soft">
                        Menampilkan {devices.from ?? 0}–{devices.to ?? 0} dari {devices.total} perangkat
                    </p>
                    {devices.last_page > 1 && (
                        <div className="flex flex-wrap gap-2">
                            {devices.links.map((link, index) => (
                                <button
                                    key={`${link.label}-${index}`}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                    className={`px-3 py-1.5 text-xs font-semibold ${
                                        link.active
                                            ? 'bg-signal-deep text-white'
                                            : 'border border-ink/10 text-ink-soft hover:bg-mist'
                                    } disabled:opacity-40`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </AdminLayout>
    );
}
