import { Activity, ArrowDownToLine, ArrowUpFromLine, LoaderCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const SPARK_POINTS = 24;
const SPARK_WIDTH = 128;
const SPARK_HEIGHT = 40;
const SPARK_PAD = 3;

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

function padHistory(values, length = SPARK_POINTS) {
    const arr = Array(length).fill(0);
    const src = values.slice(-length);
    for (let i = 0; i < src.length; i += 1) {
        arr[length - src.length + i] = Number(src[i]) || 0;
    }
    return arr;
}

function buildPolyline(values) {
    const max = Math.max(...values, 1);
    const last = Math.max(values.length - 1, 1);
    return values
        .map((value, index) => {
            const x = SPARK_PAD + (index / last) * (SPARK_WIDTH - SPARK_PAD * 2);
            const y =
                SPARK_HEIGHT -
                SPARK_PAD -
                (value / max) * (SPARK_HEIGHT - SPARK_PAD * 2);
            return `${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
}

/** Zig-zag line chart with eased morph when values update. */
function Spark({ values, strokeClass, fillClass }) {
    const target = useMemo(() => padHistory(values), [values]);
    const [display, setDisplay] = useState(target);
    const displayRef = useRef(target);
    const fromRef = useRef(target);
    const toRef = useRef(target);
    const startRef = useRef(0);
    const rafRef = useRef(0);

    useEffect(() => {
        fromRef.current = displayRef.current;
        toRef.current = target;
        startRef.current = performance.now();

        const duration = 720;
        const tick = (now) => {
            const t = Math.min(1, (now - startRef.current) / duration);
            const ease = 1 - (1 - t) ** 3;
            const next = fromRef.current.map(
                (value, index) => value + (toRef.current[index] - value) * ease,
            );
            displayRef.current = next;
            setDisplay(next);
            if (t < 1) {
                rafRef.current = requestAnimationFrame(tick);
            }
        };

        cancelAnimationFrame(rafRef.current);
        rafRef.current = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(rafRef.current);
    }, [target]);

    const points = buildPolyline(display);

    return (
        <svg
            viewBox={`0 0 ${SPARK_WIDTH} ${SPARK_HEIGHT}`}
            className={`h-10 w-32 overflow-visible ${strokeClass}`}
            aria-hidden
        >
            <polyline
                points={points}
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinejoin="round"
                strokeLinecap="round"
                vectorEffect="non-scaling-stroke"
            />
            <polyline
                points={`${SPARK_PAD},${SPARK_HEIGHT - SPARK_PAD} ${points} ${SPARK_WIDTH - SPARK_PAD},${SPARK_HEIGHT - SPARK_PAD}`}
                className={fillClass}
                fill="currentColor"
                stroke="none"
                opacity="0.12"
            />
        </svg>
    );
}

/**
 * @param {{
 *   routerId?: number|string|null,
 *   physicalInterfaces?: Array<{name: string, running?: boolean, comment?: string|null}>,
 *   routers?: Array<{id: number, name: string, host?: string}>|null,
 * }} props
 */
export default function LiveTrafficCard({
    routerId: initialRouterId = null,
    physicalInterfaces: initialInterfaces = [],
    routers = null,
}) {
    const multiRouter = Array.isArray(routers);
    const [routerId, setRouterId] = useState(
        () => initialRouterId || routers?.[0]?.id || null,
    );
    const [physicalInterfaces, setPhysicalInterfaces] = useState(initialInterfaces);
    const [selected, setSelected] = useState(initialInterfaces[0]?.name || '');
    const [traffic, setTraffic] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [loadingInterfaces, setLoadingInterfaces] = useState(false);
    const [history, setHistory] = useState({ rx: [], tx: [] });

    useEffect(() => {
        if (multiRouter) return;
        setPhysicalInterfaces(initialInterfaces);
        if (!selected && initialInterfaces[0]?.name) {
            setSelected(initialInterfaces[0].name);
        }
    }, [initialInterfaces, multiRouter, selected]);

    useEffect(() => {
        if (!multiRouter || !routerId) return undefined;

        let cancelled = false;

        const loadInterfaces = async () => {
            setLoadingInterfaces(true);
            setError('');
            setPhysicalInterfaces([]);
            setSelected('');
            setTraffic(null);
            setHistory({ rx: [], tx: [] });

            try {
                const response = await fetch(
                    `/admin/network/routeros/${routerId}/interfaces`,
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
                    setError(payload.message || 'Gagal mengambil daftar interface');
                    return;
                }

                const list = payload.interfaces || [];
                setPhysicalInterfaces(list);
                setSelected(list[0]?.name || '');
            } catch {
                if (!cancelled) {
                    setError('Tidak bisa mengambil daftar interface');
                }
            } finally {
                if (!cancelled) setLoadingInterfaces(false);
            }
        };

        loadInterfaces();

        return () => {
            cancelled = true;
        };
    }, [multiRouter, routerId]);

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

    if (multiRouter && routers.length === 0) {
        return (
            <div className="border border-ink/10 bg-white p-5">
                <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                    <Activity className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                    Live Traffic
                </h2>
                <p className="mt-3 text-sm text-ink-soft">
                    Belum ada router aktif. Tambahkan router MikroTik terlebih dahulu.
                </p>
                <a
                    href="/admin/network/routeros/create"
                    className="mt-3 inline-flex text-sm font-semibold text-signal-deep hover:underline"
                >
                    Tambah Router
                </a>
            </div>
        );
    }

    if (!multiRouter && !physicalInterfaces.length) {
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
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    {(loading || loadingInterfaces) && (
                        <LoaderCircle
                            className="hidden h-4 w-4 animate-spin text-signal-deep sm:block"
                            aria-hidden
                        />
                    )}
                    {multiRouter && (
                        <select
                            value={routerId || ''}
                            onChange={(e) => setRouterId(Number(e.target.value) || e.target.value)}
                            className="w-full border border-ink/15 bg-paper px-3 py-2 text-sm outline-none focus:border-signal sm:min-w-[180px] sm:w-auto"
                        >
                            {routers.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                    {item.host ? ` (${item.host})` : ''}
                                </option>
                            ))}
                        </select>
                    )}
                    <select
                        value={selected}
                        onChange={(e) => setSelected(e.target.value)}
                        disabled={loadingInterfaces || !physicalInterfaces.length}
                        className="w-full border border-ink/15 bg-paper px-3 py-2 text-sm outline-none focus:border-signal disabled:opacity-60 sm:min-w-[140px] sm:w-auto"
                    >
                        {physicalInterfaces.length === 0 && (
                            <option value="">
                                {loadingInterfaces ? 'Memuat interface...' : 'Tidak ada interface'}
                            </option>
                        )}
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
                <div className="border border-sky-200/80 bg-sky-50/70 p-4">
                    <div className="flex items-center justify-between gap-2">
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-sky-700 uppercase">
                            <ArrowDownToLine className="h-3.5 w-3.5 text-sky-600" />
                            Download (RX)
                        </p>
                        <Spark
                            values={history.rx}
                            strokeClass="text-sky-500"
                            fillClass="text-sky-500"
                        />
                    </div>
                    <p className="font-hero mt-3 text-3xl tracking-tight text-sky-950">
                        {formatBitrate(traffic?.rx_bps)}
                    </p>
                    <p className="mt-1 text-xs text-sky-700/70">{traffic?.rx_pps ?? 0} paket/detik</p>
                </div>

                <div className="border border-orange-200/80 bg-orange-50/70 p-4">
                    <div className="flex items-center justify-between gap-2">
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-orange-700 uppercase">
                            <ArrowUpFromLine className="h-3.5 w-3.5 text-orange-600" />
                            Upload (TX)
                        </p>
                        <Spark
                            values={history.tx}
                            strokeClass="text-orange-500"
                            fillClass="text-orange-500"
                        />
                    </div>
                    <p className="font-hero mt-3 text-3xl tracking-tight text-orange-950">
                        {formatBitrate(traffic?.tx_bps)}
                    </p>
                    <p className="mt-1 text-xs text-orange-700/70">{traffic?.tx_pps ?? 0} paket/detik</p>
                </div>
            </div>
        </div>
    );
}
