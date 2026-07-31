import { Activity, ArrowDownToLine, ArrowUpFromLine, LoaderCircle } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

function formatBitrate(bps) {
    if (bps == null || Number.isNaN(Number(bps))) return '0 bps';
    const value = Number(bps);
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    let n = value;
    let i = 0;
    while (n >= 1000 && i < units.length - 1) {
        n /= 1000;
        i += 1;
    }
    return `${n.toFixed(i === 0 ? 0 : 2)} ${units[i]}`;
}

function Spark({ values, colorClass }) {
    const max = Math.max(...values, 1);

    return (
        <div className="flex h-10 items-end gap-0.5">
            {values.map((value, index) => (
                <div
                    key={`${index}-${value}`}
                    className={`w-1.5 rounded-sm ${colorClass}`}
                    style={{ height: `${Math.max(8, Math.round((value / max) * 100))}%` }}
                />
            ))}
        </div>
    );
}

export default function LiveTrafficCard({ routerId, physicalInterfaces = [] }) {
    const defaultInterface = physicalInterfaces[0]?.name || '';
    const [selected, setSelected] = useState(defaultInterface);
    const [traffic, setTraffic] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [history, setHistory] = useState({ rx: [], tx: [] });

    useEffect(() => {
        if (!selected && physicalInterfaces[0]?.name) {
            setSelected(physicalInterfaces[0].name);
        }
    }, [physicalInterfaces, selected]);

    useEffect(() => {
        if (!selected || !routerId) return undefined;

        let cancelled = false;
        let busy = false;

        const fetchTraffic = async () => {
            if (busy || cancelled) return;
            busy = true;
            setLoading(true);

            try {
                const response = await fetch(
                    `/admin/network/routeros/${routerId}/traffic?interface=${encodeURIComponent(selected)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );
                const payload = await response.json();

                if (cancelled) return;

                if (!payload.ok) {
                    setError(payload.message || 'Gagal mengambil traffic');
                    return;
                }

                setError('');
                setTraffic(payload.data);
                setHistory((prev) => ({
                    rx: [...prev.rx, payload.data.rx_bps].slice(-24),
                    tx: [...prev.tx, payload.data.tx_bps].slice(-24),
                }));
            } catch {
                if (!cancelled) {
                    setError('Tidak bisa mengambil data live traffic');
                }
            } finally {
                busy = false;
                if (!cancelled) setLoading(false);
            }
        };

        setHistory({ rx: [], tx: [] });
        setTraffic(null);
        fetchTraffic();
        const timer = window.setInterval(fetchTraffic, 2000);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [routerId, selected]);

    const selectedMeta = useMemo(
        () => physicalInterfaces.find((item) => item.name === selected),
        [physicalInterfaces, selected],
    );

    if (!physicalInterfaces.length) {
        return (
            <div className="border border-ink/10 bg-white p-5">
                <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                    <Activity className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                    Live Traffic
                </h2>
                <p className="mt-3 text-sm text-ink-soft">Tidak ada interface Ethernet fisik.</p>
            </div>
        );
    }

    return (
        <div className="border border-ink/10 bg-white p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                        <Activity className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                        Live Traffic
                    </h2>
                    <p className="mt-1 text-xs text-ink-soft">
                        Update tiap 2 detik dari API monitor-traffic
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    {loading && (
                        <LoaderCircle className="h-4 w-4 animate-spin text-signal-deep" aria-hidden />
                    )}
                    <select
                        value={selected}
                        onChange={(e) => setSelected(e.target.value)}
                        className="min-w-[160px] border border-ink/15 bg-paper px-3 py-2 text-sm outline-none focus:border-signal"
                    >
                        {physicalInterfaces.map((iface) => (
                            <option key={iface.name} value={iface.name}>
                                {iface.name}
                                {iface.running ? '' : ' (down)'}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            {selectedMeta && (
                <p className="mt-3 text-xs text-ink/45">
                    Status: {selectedMeta.running ? 'Running' : 'Down'}
                    {selectedMeta.comment ? ` · ${selectedMeta.comment}` : ''}
                </p>
            )}

            {error && (
                <div className="mt-4 border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {error}
                </div>
            )}

            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                <div className="border border-ink/10 bg-mist/30 p-4">
                    <div className="flex items-center justify-between gap-2">
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                            <ArrowDownToLine className="h-3.5 w-3.5 text-signal-deep" />
                            Download (RX)
                        </p>
                        <Spark values={history.rx} colorClass="bg-signal-bright/80" />
                    </div>
                    <p className="font-hero mt-3 text-3xl tracking-tight text-ink">
                        {formatBitrate(traffic?.rx_bps)}
                    </p>
                    <p className="mt-1 text-xs text-ink/45">{traffic?.rx_pps ?? 0} paket/detik</p>
                </div>

                <div className="border border-ink/10 bg-mist/30 p-4">
                    <div className="flex items-center justify-between gap-2">
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                            <ArrowUpFromLine className="h-3.5 w-3.5 text-signal-deep" />
                            Upload (TX)
                        </p>
                        <Spark values={history.tx} colorClass="bg-ink/50" />
                    </div>
                    <p className="font-hero mt-3 text-3xl tracking-tight text-ink">
                        {formatBitrate(traffic?.tx_bps)}
                    </p>
                    <p className="mt-1 text-xs text-ink/45">{traffic?.tx_pps ?? 0} paket/detik</p>
                </div>
            </div>
        </div>
    );
}
