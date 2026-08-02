import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    ArrowDownToLine,
    ArrowLeft,
    ArrowUpFromLine,
    BadgeInfo,
    Cable,
    CircuitBoard,
    Clock3,
    Cpu,
    HardDrive,
    Layers,
    MemoryStick,
    Network,
    RefreshCw,
    Router,
    Server,
    Timer,
    Wifi,
} from 'lucide-react';
import LiveTrafficCard from '../../../../Components/Admin/LiveTrafficCard';
import AdminLayout from '../../../../Layouts/AdminLayout';

function formatBytes(bytes) {
    if (bytes == null) return '—';
    const value = Number(bytes);
    if (Number.isNaN(value)) return '—';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let n = value;
    let i = 0;
    while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i += 1;
    }
    return `${n.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

/** MikroTik uptime "3w2d5h30m15s" → "3w 2d 5h 30m 15s" */
function formatUptime(uptime) {
    if (uptime == null || uptime === '') return null;
    return String(uptime)
        .replace(/(\d+[wdhms])/gi, '$1 ')
        .replace(/\s+/g, ' ')
        .trim();
}

function Stat({ label, value, icon: Icon, tone }) {
    return (
        <div className={`flex min-h-[110px] flex-col p-4 text-white shadow-sm ${tone}`}>
            <div className="flex items-start justify-between gap-3">
                <p className="text-xs tracking-wide text-white/75 uppercase">{label}</p>
                <span className="inline-flex h-9 w-9 items-center justify-center bg-white/15">
                    <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} aria-hidden />
                </span>
            </div>
            <p className="font-display mt-3 text-lg font-bold leading-snug break-words">
                {value ?? '—'}
            </p>
        </div>
    );
}

function SummaryRow({ label, value, icon: Icon }) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-ink/5 py-2.5 last:border-b-0 last:pb-0 first:pt-0">
            <dt className="flex min-w-0 items-center gap-2 text-ink-soft">
                <Icon className="h-3.5 w-3.5 shrink-0 text-signal-deep" strokeWidth={1.75} aria-hidden />
                <span className="whitespace-nowrap">{label}</span>
            </dt>
            <dd className="max-w-[60%] text-right font-medium break-words text-ink">
                {value || '—'}
            </dd>
        </div>
    );
}

export default function Show({ router: item, info }) {
    const refresh = () => {
        router.get(`/admin/network/routeros/${item.id}`, {}, { preserveScroll: true });
    };

    return (
        <AdminLayout
            title={item.name}
            subtitle={`${item.host}:${item.port} · ${info.identity || 'RouterOS'}`}
        >
            <Head title={`RouterOS · ${item.name}`} />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <Link
                    href="/admin/network/routeros"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-ink-soft hover:text-ink"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali ke daftar
                </Link>
                <button
                    type="button"
                    onClick={refresh}
                    className="btn-action btn-action-sm btn-sync"
                >
                    <RefreshCw className="h-4 w-4" />
                    Refresh data
                </button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Stat
                    label="Identity"
                    value={info.identity}
                    icon={BadgeInfo}
                    tone="bg-gradient-to-br from-slate-400 to-slate-600"
                />
                <Stat
                    label="Board"
                    value={info.board}
                    icon={CircuitBoard}
                    tone="bg-gradient-to-br from-cyan-400 to-sky-600"
                />
                <Stat
                    label="Version"
                    value={info.version}
                    icon={Layers}
                    tone="bg-gradient-to-br from-indigo-400 to-blue-600"
                />
                <Stat
                    label="Uptime"
                    value={formatUptime(info.uptime)}
                    icon={Timer}
                    tone="bg-gradient-to-br from-emerald-400 to-teal-600"
                />
                <Stat
                    label="CPU Load"
                    value={info.cpu_load != null ? `${info.cpu_load}%` : null}
                    icon={Cpu}
                    tone="bg-gradient-to-br from-amber-300 to-orange-500"
                />
                <Stat
                    label="Memory"
                    value={
                        info.free_memory != null
                            ? `${formatBytes(info.free_memory)} / ${formatBytes(info.total_memory)}`
                            : null
                    }
                    icon={MemoryStick}
                    tone="bg-gradient-to-br from-violet-400 to-fuchsia-600"
                />
                <Stat
                    label="PPPoE Aktif"
                    value={info.ppp_active}
                    icon={Cable}
                    tone="bg-gradient-to-br from-teal-400 to-cyan-600"
                />
                <Stat
                    label="Hotspot Aktif"
                    value={info.hotspot_active}
                    icon={Wifi}
                    tone="bg-gradient-to-br from-rose-400 to-pink-600"
                />
            </div>

            <div className="mt-6">
                <LiveTrafficCard
                    routerId={item.id}
                    physicalInterfaces={info.physical_interfaces || []}
                />
            </div>

            <div className="mt-6 grid min-w-0 gap-4 lg:grid-cols-3">
                <div className="min-w-0 border border-ink/10 bg-white p-4 sm:p-5 lg:col-span-1">
                    <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                        <Activity className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                        Ringkasan
                    </h2>
                    <dl className="mt-3 text-sm">
                        <SummaryRow label="Platform" value={info.platform} icon={Server} />
                        <SummaryRow label="Arsitektur" value={info.architecture} icon={HardDrive} />
                        <SummaryRow
                            label="Waktu router"
                            value={[info.date, info.time].filter(Boolean).join(' ')}
                            icon={Clock3}
                        />
                        <SummaryRow label="Interface" value={info.interface_count} icon={Network} />
                        <SummaryRow
                            label="Traffic RX"
                            value={formatBytes(info.traffic?.rx_byte)}
                            icon={ArrowDownToLine}
                        />
                        <SummaryRow
                            label="Traffic TX"
                            value={formatBytes(info.traffic?.tx_byte)}
                            icon={ArrowUpFromLine}
                        />
                    </dl>
                </div>

                <div className="min-w-0 border border-ink/10 bg-white lg:col-span-2">
                    <div className="border-b border-ink/10 px-4 py-4 sm:px-5">
                        <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                            <Router className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                            Interface
                        </h2>
                        <p className="mt-1 text-xs text-ink-soft">
                            Maks. 20 interface aktif ditampilkan
                        </p>
                    </div>
                    <div className="admin-data-scroll">
                        <table className="text-left text-sm">
                            <thead className="bg-mist/40 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">Nama</th>
                                    <th className="px-4 py-3 font-semibold">Tipe</th>
                                    <th className="px-4 py-3 font-semibold">Status</th>
                                    <th className="px-4 py-3 font-semibold">RX</th>
                                    <th className="px-4 py-3 font-semibold">TX</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(info.interfaces || []).map((iface) => (
                                    <tr key={iface.name} className="border-t border-ink/5">
                                        <td className="px-4 py-3 font-medium text-ink">
                                            {iface.name}
                                        </td>
                                        <td className="px-4 py-3 text-ink-soft">{iface.type}</td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex px-2 py-1 text-xs font-semibold ${
                                                    iface.running
                                                        ? 'bg-signal/15 text-signal-deep'
                                                        : 'bg-ink/5 text-ink-soft'
                                                }`}
                                            >
                                                {iface.running ? 'Running' : 'Down'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-ink-soft">
                                            {formatBytes(iface.rx_byte)}
                                        </td>
                                        <td className="px-4 py-3 text-ink-soft">
                                            {formatBytes(iface.tx_byte)}
                                        </td>
                                    </tr>
                                ))}
                                {(info.interfaces || []).length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-8 text-center text-ink-soft"
                                        >
                                            Tidak ada data interface
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
