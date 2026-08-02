import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Cpu,
    ExternalLink,
    Hash,
    KeyRound,
    Laptop,
    Radio,
    RefreshCw,
    Save,
    ServerCrash,
    Signal,
    Smartphone,
    Thermometer,
    Users,
    Wifi,
} from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import {
    onlineTone,
    rxPowerTone,
    temperatureTone,
} from '../../../../Utils/genieacsMetrics';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal disabled:bg-mist';

function Row({ label, value, icon: Icon, iconClass = 'text-ink-soft' }) {
    return (
        <div className="grid gap-1 border-b border-ink/5 py-3 last:border-0 sm:grid-cols-[200px_1fr] sm:gap-4">
            <dt className="flex items-center gap-2 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                {Icon && <Icon className={`h-3.5 w-3.5 shrink-0 ${iconClass}`} />}
                {label}
            </dt>
            <dd className="text-sm text-ink break-all">{value || '—'}</dd>
        </div>
    );
}

function MetricCard({ label, value, icon: Icon, tone, hint }) {
    return (
        <div className={`border p-4 ${tone.card}`}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[11px] font-semibold tracking-wide text-ink-soft uppercase">
                        {label}
                    </p>
                    <p className={`font-display mt-2 text-xl font-bold ${tone.text}`}>{value || '—'}</p>
                    {hint ? <div className="mt-1 text-[11px] text-ink-soft">{hint}</div> : null}
                </div>
                <span
                    className={`inline-flex h-10 w-10 items-center justify-center bg-gradient-to-br text-white ${tone.accent}`}
                >
                    <Icon className="h-5 w-5" strokeWidth={1.75} />
                </span>
            </div>
        </div>
    );
}

function clientIcon(name = '') {
    const lower = name.toLowerCase();
    if (
        lower.includes('iphone') ||
        lower.includes('android') ||
        lower.includes('redmi') ||
        lower.includes('xiaomi') ||
        lower.includes('oppo') ||
        lower.includes('vivo') ||
        lower.includes('samsung')
    ) {
        return Smartphone;
    }

    return Laptop;
}

export default function Show({ device, ui_url }) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const [summoning, setSummoning] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const status = onlineTone(device.online);
    const temp = temperatureTone(device.temperature);
    const rx = rxPowerTone(device.rx_power);
    const clients = device.connected_clients || [];
    const connectedCount = device.connected_count ?? clients.length;

    const wifiForm = useForm({
        ssid: device.ssid || '',
        password: '',
    });

    const summon = () => {
        if (summoning) return;
        setSummoning(true);
        router.post(
            `/admin/network/genieacs/devices/${encodeURIComponent(device.id)}/summon`,
            {},
            { onFinish: () => setSummoning(false) },
        );
    };

    const saveWifi = (e) => {
        e.preventDefault();
        if (!canWrite) return;

        wifiForm.post(`/admin/network/genieacs/devices/${encodeURIComponent(device.id)}/wifi`, {
            preserveScroll: true,
            onSuccess: () => wifiForm.setData('password', ''),
        });
    };

    return (
        <AdminLayout
            title={device.serial || device.id}
            subtitle="Detail perangkat GenieACS"
        >
            <Head title={`GenieACS · ${device.serial || device.id}`} />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <Link
                    href="/admin/network/genieacs"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-sky-700 hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4 text-sky-600" />
                    Kembali ke daftar
                </Link>
                <div className="admin-toolbar-actions">
                    {ui_url && (
                        <a
                            href={`${ui_url.replace(/\/$/, '')}/#!/devices/${encodeURIComponent(device.id)}`}
                            target="_blank"
                            rel="noreferrer"
                            className="btn-action btn-action-xs btn-edit"
                        >
                            <ExternalLink className="mr-1.5 h-3.5 w-3.5 text-sky-600" />
                            Buka di GenieACS UI
                        </a>
                    )}
                    {canWrite && (
                        <button
                            type="button"
                            onClick={summon}
                            disabled={summoning}
                            className="btn-action btn-action-xs btn-sync"
                        >
                            <RefreshCw
                                className={`mr-1.5 h-3.5 w-3.5 ${summoning ? 'animate-spin' : ''}`}
                            />
                            {summoning ? 'Summon...' : 'Summon / Refresh'}
                        </button>
                    )}
                </div>
            </div>

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <span
                    className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold ${status.badge}`}
                >
                    {device.online ? (
                        <Activity className={`h-3.5 w-3.5 ${status.icon}`} />
                    ) : (
                        <ServerCrash className={`h-3.5 w-3.5 ${status.icon}`} />
                    )}
                    {device.online ? 'Online' : 'Offline'}
                </span>
                <span className="inline-flex items-center gap-1.5 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-800">
                    <Users className="h-3.5 w-3.5 text-sky-600" />
                    {connectedCount} perangkat terhubung
                </span>
                {(device.tags || []).map((tag) => (
                    <span
                        key={tag}
                        className="inline-flex items-center gap-1 bg-cyan-50 px-2 py-1 text-xs font-semibold text-cyan-700"
                    >
                        <Hash className="h-3 w-3" />
                        {tag}
                    </span>
                ))}
            </div>

            <div className="mb-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                    label="Suhu"
                    value={device.temperature_label}
                    icon={Thermometer}
                    tone={temp}
                    hint="Normal ≤ 50°C"
                />
                <MetricCard
                    label="RX Power"
                    value={device.rx_power_label}
                    icon={Signal}
                    tone={rx}
                    hint="Ideal −8 s/d −25 dBm"
                />
                <MetricCard
                    label="SSID"
                    value={device.ssid}
                    icon={Wifi}
                    tone={{
                        text: device.ssid ? 'text-sky-800' : 'text-ink-soft',
                        card: 'border-sky-200 bg-sky-50/70',
                        accent: 'from-sky-400 to-cyan-600',
                    }}
                />
                <MetricCard
                    label="Klien terhubung"
                    value={String(connectedCount)}
                    icon={Users}
                    tone={{
                        text: connectedCount > 0 ? 'text-teal-800' : 'text-ink-soft',
                        card: 'border-teal-200 bg-teal-50/70',
                        accent: 'from-teal-400 to-emerald-600',
                    }}
                    hint="WiFi / LAN aktif"
                />
            </div>

            {canWrite && (
                <form
                    onSubmit={saveWifi}
                    className="mb-4 space-y-4 border border-sky-200 bg-white p-5 sm:p-6"
                >
                    <div className="flex items-start gap-3">
                        <span className="inline-flex h-9 w-9 items-center justify-center bg-gradient-to-br from-sky-400 to-cyan-600 text-white">
                            <Wifi className="h-4 w-4" />
                        </span>
                        <div>
                            <h2 className="text-sm font-semibold text-ink">Ubah SSID & Password</h2>
                            <p className="mt-0.5 text-xs text-ink-soft">
                                Perubahan dikirim ke ONT melalui GenieACS (connection request). Kosongkan
                                password jika hanya ingin mengganti nama SSID.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            Nama SSID
                            <input
                                type="text"
                                value={wifiForm.data.ssid}
                                onChange={(e) => wifiForm.setData('ssid', e.target.value)}
                                className={fieldClass}
                                maxLength={32}
                                placeholder={device.ssid || 'Nama WiFi'}
                            />
                            {wifiForm.errors.ssid && (
                                <span className="mt-1 block text-xs text-rose-600">
                                    {wifiForm.errors.ssid}
                                </span>
                            )}
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            Password SSID baru
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={wifiForm.data.password}
                                onChange={(e) => wifiForm.setData('password', e.target.value)}
                                className={fieldClass}
                                minLength={8}
                                maxLength={63}
                                placeholder="Minimal 8 karakter (opsional)"
                                autoComplete="new-password"
                            />
                            {wifiForm.errors.password && (
                                <span className="mt-1 block text-xs text-rose-600">
                                    {wifiForm.errors.password}
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={() => setShowPassword((v) => !v)}
                                className="mt-1.5 text-xs font-semibold text-sky-700 hover:text-ink"
                            >
                                {showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                            </button>
                        </label>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <button
                            type="submit"
                            disabled={wifiForm.processing}
                            className="btn-action btn-action-sm btn-primary"
                        >
                            <Save className="h-4 w-4" />
                            {wifiForm.processing ? 'Mengirim...' : 'Simpan ke ONT'}
                        </button>
                        <p className="text-xs text-ink-soft">
                            Password saat ini:{' '}
                            <span className="font-mono text-ink">
                                {device.ssid_password || '—'}
                            </span>
                        </p>
                    </div>
                </form>
            )}

            <div className="mb-4 border border-ink/10 bg-white p-5 sm:p-6">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <span className="inline-flex h-8 w-8 items-center justify-center bg-gradient-to-br from-teal-400 to-emerald-600 text-white">
                            <Users className="h-4 w-4" />
                        </span>
                        <div>
                            <h2 className="text-sm font-semibold text-ink">Perangkat terhubung</h2>
                            <p className="text-xs text-ink-soft">
                                {connectedCount} perangkat · data dari AssociatedDevice / Hosts GenieACS
                            </p>
                        </div>
                    </div>
                    {canWrite && (
                        <button
                            type="button"
                            onClick={summon}
                            disabled={summoning}
                            className="btn-action btn-action-xs btn-sync"
                        >
                            <RefreshCw className={`h-3.5 w-3.5 ${summoning ? 'animate-spin' : ''}`} />
                            Refresh klien
                        </button>
                    )}
                </div>

                <div className="admin-data-scroll border border-ink/10">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-3 font-semibold">Nama perangkat</th>
                                <th className="px-4 py-3 font-semibold">IP</th>
                                <th className="px-4 py-3 font-semibold">MAC</th>
                                <th className="px-4 py-3 font-semibold">SSID / Interface</th>
                            </tr>
                        </thead>
                        <tbody>
                            {clients.map((client) => {
                                const Icon = clientIcon(client.name || client.hostname || '');
                                return (
                                    <tr
                                        key={`${client.mac}-${client.ip || ''}`}
                                        className="border-b border-ink/5 last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            <span className="inline-flex items-center gap-2 font-medium text-ink">
                                                <Icon className="h-4 w-4 shrink-0 text-teal-600" />
                                                {client.name || client.hostname || client.mac}
                                            </span>
                                            {client.hostname && client.name !== client.hostname && (
                                                <p className="mt-0.5 pl-6 text-xs text-ink-soft">
                                                    {client.hostname}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-ink-soft">
                                            {client.ip || '—'}
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-ink-soft">
                                            {client.mac || '—'}
                                        </td>
                                        <td className="px-4 py-3 text-ink-soft">
                                            {client.ssid || client.interface || '—'}
                                        </td>
                                    </tr>
                                );
                            })}
                            {clients.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-4 py-10 text-center text-ink-soft">
                                        Belum ada klien terdeteksi. Coba Summon / Refresh agar ONT
                                        melaporkan AssociatedDevice & Hosts.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="border border-ink/10 bg-white p-5 sm:p-6">
                <div className="mb-2 flex items-center gap-2">
                    <span className="inline-flex h-8 w-8 items-center justify-center bg-gradient-to-br from-cyan-400 to-sky-600 text-white">
                        <Radio className="h-4 w-4" />
                    </span>
                    <h2 className="text-sm font-semibold text-ink">Identitas perangkat</h2>
                </div>
                <dl>
                    <Row label="Device ID" value={device.id} icon={Hash} iconClass="text-cyan-600" />
                    <Row
                        label="Manufacturer"
                        value={device.manufacturer}
                        icon={Cpu}
                        iconClass="text-slate-600"
                    />
                    <Row label="Model" value={device.model} icon={Cpu} iconClass="text-slate-600" />
                    <Row
                        label="Serial Number"
                        value={device.serial}
                        icon={Hash}
                        iconClass="text-cyan-600"
                    />
                    <Row
                        label="Product Class"
                        value={device.product_class}
                        icon={Radio}
                        iconClass="text-sky-600"
                    />
                    <Row label="OUI" value={device.oui} icon={Hash} iconClass="text-ink-soft" />
                    <Row
                        label="Software Version"
                        value={device.software_version}
                        icon={Cpu}
                        iconClass="text-teal-600"
                    />
                    <Row
                        label="Hardware Version"
                        value={device.hardware_version}
                        icon={Cpu}
                        iconClass="text-teal-600"
                    />
                    <Row
                        label="Last Inform"
                        value={device.last_inform_label}
                        icon={Activity}
                        iconClass={status.icon}
                    />
                    <Row
                        label="Password SSID"
                        value={device.ssid_password || '—'}
                        icon={KeyRound}
                        iconClass="text-violet-600"
                    />
                </dl>
            </div>

            {device.raw_keys?.length > 0 && (
                <div className="mt-4 border border-ink/10 bg-white p-5 sm:p-6">
                    <h2 className="mb-2 text-sm font-semibold text-ink">Root objek TR-069</h2>
                    <p className="mb-3 text-xs text-ink-soft">
                        Objek yang tersedia pada dokumen perangkat di GenieACS.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {device.raw_keys.map((key) => (
                            <span
                                key={key}
                                className="bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-800"
                            >
                                {key}
                            </span>
                        ))}
                    </div>
                </div>
            )}
        </AdminLayout>
    );
}
