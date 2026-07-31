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

function Stat({ label, value, icon: Icon }) {
    return (
        <div className="border border-ink/10 bg-white p-4">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] tracking-wide text-ink-soft uppercase">{label}</p>
                <span className="inline-flex rounded-md bg-signal/10 p-1.5 text-signal-deep">
                    <Icon className="h-3.5 w-3.5" strokeWidth={1.75} aria-hidden />
                </span>
            </div>
            <p className="mt-3 text-lg font-semibold text-ink">{value ?? '—'}</p>
        </div>
    );
}

function SummaryRow({ label, value, icon: Icon }) {
    return (
        <div className="flex items-center justify-between gap-3 border-b border-ink/5 pb-2 last:border-b-0 last:pb-0">
            <dt className="flex items-center gap-2 text-ink-soft">
                <Icon className="h-3.5 w-3.5 text-signal-deep" strokeWidth={1.75} aria-hidden />
                {label}
            </dt>
            <dd className="font-medium text-ink">{value || '—'}</dd>
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
                    className="inline-flex items-center gap-2 border border-ink/10 bg-white px-3 py-2 text-sm font-semibold text-ink hover:bg-mist"
                >
                    <RefreshCw className="h-4 w-4" />
                    Refresh data
                </button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="Identity" value={info.identity} icon={BadgeInfo} />
                <Stat label="Board" value={info.board} icon={CircuitBoard} />
                <Stat label="Version" value={info.version} icon={Layers} />
                <Stat label="Uptime" value={info.uptime} icon={Timer} />
                <Stat
                    label="CPU Load"
                    value={info.cpu_load != null ? `${info.cpu_load}%` : null}
                    icon={Cpu}
                />
                <Stat
                    label="Memory"
                    value={
                        info.free_memory != null
                            ? `${formatBytes(info.free_memory)} / ${formatBytes(info.total_memory)}`
                            : null
                    }
                    icon={MemoryStick}
                />
                <Stat label="PPPoE Aktif" value={info.ppp_active} icon={Cable} />
                <Stat label="Hotspot Aktif" value={info.hotspot_active} icon={Wifi} />
            </div>

            <div className="mt-6">
                <LiveTrafficCard
                    routerId={item.id}
                    physicalInterfaces={info.physical_interfaces || []}
                />
            </div>

            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                <div className="border border-ink/10 bg-white p-5 lg:col-span-1">
                    <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                        <Activity className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                        Ringkasan
                    </h2>
                    <dl className="mt-4 space-y-3 text-sm">
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

                <div className="border border-ink/10 bg-white lg:col-span-2">
                    <div className="border-b border-ink/10 px-5 py-4">
                        <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                            <Router className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                            Interface
                        </h2>
                        <p className="mt-1 text-xs text-ink-soft">
                            Maks. 20 interface aktif ditampilkan
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
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
                                                className={`text-xs font-semibold ${
                                                    iface.running
                                                        ? 'text-signal-deep'
                                                        : 'text-ink/40'
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
