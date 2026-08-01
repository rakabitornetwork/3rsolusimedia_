import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Cpu,
    ExternalLink,
    Hash,
    KeyRound,
    Radio,
    RefreshCw,
    ServerCrash,
    Signal,
    Thermometer,
    Wifi,
} from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import {
    onlineTone,
    rxPowerTone,
    temperatureTone,
} from '../../../../Utils/genieacsMetrics';

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

export default function Show({ device, ui_url }) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const [summoning, setSummoning] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const status = onlineTone(device.online);
    const temp = temperatureTone(device.temperature);
    const rx = rxPowerTone(device.rx_power);

    const summon = () => {
        if (summoning) return;
        setSummoning(true);
        router.post(
            `/admin/network/genieacs/devices/${encodeURIComponent(device.id)}/summon`,
            {},
            { onFinish: () => setSummoning(false) },
        );
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
                            className="border border-sky-200 bg-sky-50 px-3 text-xs font-semibold text-sky-800 hover:bg-sky-100"
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
                            className="bg-teal-600 px-3 text-xs font-semibold text-white hover:bg-teal-700 disabled:opacity-60"
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
                    label="Password SSID"
                    value={
                        device.ssid_password
                            ? showPassword
                                ? device.ssid_password
                                : '••••••••'
                            : '—'
                    }
                    icon={KeyRound}
                    tone={{
                        text: device.ssid_password ? 'text-violet-800' : 'text-ink-soft',
                        card: 'border-violet-200 bg-violet-50/70',
                        accent: 'from-violet-400 to-fuchsia-600',
                    }}
                    hint={
                        device.ssid_password ? (
                            <button
                                type="button"
                                onClick={() => setShowPassword((v) => !v)}
                                className="font-semibold text-violet-700 hover:text-violet-900"
                            >
                                {showPassword ? 'Sembunyikan' : 'Tampilkan'}
                            </button>
                        ) : null
                    }
                />
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
                </dl>
            </div>

            <div className="mt-4 border border-ink/10 bg-white p-5 sm:p-6">
                <div className="mb-2 flex items-center gap-2">
                    <span className="inline-flex h-8 w-8 items-center justify-center bg-gradient-to-br from-teal-400 to-emerald-600 text-white">
                        <Signal className="h-4 w-4" />
                    </span>
                    <h2 className="text-sm font-semibold text-ink">Optik & WiFi</h2>
                </div>
                <dl>
                    <Row
                        label="Suhu"
                        value={
                            <span className={`font-semibold ${temp.text}`}>
                                {device.temperature_label || '—'}
                            </span>
                        }
                        icon={Thermometer}
                        iconClass={temp.icon}
                    />
                    <Row
                        label="RX Power"
                        value={
                            <span className={`font-semibold ${rx.text}`}>
                                {device.rx_power_label || '—'}
                            </span>
                        }
                        icon={Signal}
                        iconClass={rx.icon}
                    />
                    <Row
                        label="SSID"
                        value={device.ssid}
                        icon={Wifi}
                        iconClass="text-sky-600"
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
