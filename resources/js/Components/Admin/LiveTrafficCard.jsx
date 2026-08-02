import { usePage } from '@inertiajs/react';
import { Activity, ArrowDownToLine, ArrowUpFromLine, LoaderCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const SPARK_POINTS = 24;
const POLL_SECONDS = 3;
const CHART_WIDTH = 640;
const CHART_HEIGHT = 132;
const MARGIN = { top: 8, right: 12, bottom: 24, left: 52 };
const SMOOTH = 0.16;
const YMAX_UP = 0.22;
const YMAX_DOWN = 0.035;
const PEAK_SOFTEN = 0.42;

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

function formatAxisBitrate(bps) {
    if (bps == null || Number.isNaN(Number(bps))) return '0';
    const value = Number(bps);
    if (value <= 0) return '0';
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    let n = value;
    let i = 0;
    while (n >= 1000 && i < units.length - 1) {
        n /= 1000;
        i += 1;
    }
    const digits = n >= 100 || i === 0 ? 0 : n >= 10 ? 1 : 2;
    return `${n.toFixed(digits)} ${units[i]}`;
}

function niceMax(value) {
    const n = Math.max(Number(value) || 0, 1);
    const magnitude = 10 ** Math.floor(Math.log10(n));
    const residual = n / magnitude;
    let nice = 1;
    if (residual <= 1) nice = 1;
    else if (residual <= 2) nice = 2;
    else if (residual <= 5) nice = 5;
    else nice = 10;
    return nice * magnitude;
}

function padHistory(values, length = SPARK_POINTS) {
    const arr = Array(length).fill(0);
    const src = values.slice(-length);
    for (let i = 0; i < src.length; i += 1) {
        arr[length - src.length + i] = Number(src[i]) || 0;
    }
    return arr;
}

function toPoints(values, max, plotW, plotH) {
    const safeMax = Math.max(max, 1);
    const last = Math.max(values.length - 1, 1);
    return values.map((value, index) => ({
        x: MARGIN.left + (index / last) * plotW,
        y: MARGIN.top + plotH - (value / safeMax) * plotH,
    }));
}

/** Haluskan puncak agar zig-zag tidak terlalu lancip. */
function softenPeaks(values, amount = PEAK_SOFTEN) {
    if (values.length < 3) return values;
    return values.map((value, index) => {
        if (index === 0 || index === values.length - 1) return value;
        const neighborAvg = (values[index - 1] + values[index + 1]) / 2;
        return value * (1 - amount) + neighborAvg * amount;
    });
}

/** Path kurva kuadratik lewat titik tengah — tetap zig-zag tapi lebih lembut. */
function buildSmoothPath(values, max, plotW, plotH) {
    const points = toPoints(softenPeaks(values), max, plotW, plotH);
    if (points.length === 0) return '';
    if (points.length === 1) {
        return `M ${points[0].x.toFixed(2)} ${points[0].y.toFixed(2)}`;
    }

    let d = `M ${points[0].x.toFixed(2)} ${points[0].y.toFixed(2)}`;
    for (let i = 0; i < points.length - 1; i += 1) {
        const current = points[i];
        const next = points[i + 1];
        const midX = (current.x + next.x) / 2;
        const midY = (current.y + next.y) / 2;
        d += ` Q ${current.x.toFixed(2)} ${current.y.toFixed(2)} ${midX.toFixed(2)} ${midY.toFixed(2)}`;
        if (i === points.length - 2) {
            d += ` T ${next.x.toFixed(2)} ${next.y.toFixed(2)}`;
        }
    }
    return d;
}

function buildAreaPath(values, max, plotW, plotH) {
    const line = buildSmoothPath(values, max, plotW, plotH);
    if (!line) return '';
    const baseY = MARGIN.top + plotH;
    return `${line} L ${(MARGIN.left + plotW).toFixed(2)} ${baseY.toFixed(2)} L ${MARGIN.left.toFixed(2)} ${baseY.toFixed(2)} Z`;
}

/** Zig-zag line chart with stable Y scale and continuous smooth chase. */
function TrafficChart({ values, strokeClass, axisClass = 'text-ink/45' }) {
    const sampleCount = values.length;
    const target = useMemo(() => padHistory(values), [values]);
    const [display, setDisplay] = useState(target);
    const [yMax, setYMax] = useState(() => niceMax(Math.max(...target, 1)));

    const displayRef = useRef(target);
    const targetRef = useRef(target);
    const yMaxRef = useRef(niceMax(Math.max(...target, 1)));
    const sampleCountRef = useRef(sampleCount);
    const lineRef = useRef(null);
    const fillRef = useRef(null);
    const yLabelRefs = useRef([]);
    const gridRefs = useRef([]);

    const plotW = CHART_WIDTH - MARGIN.left - MARGIN.right;
    const plotH = CHART_HEIGHT - MARGIN.top - MARGIN.bottom;
    const windowSeconds = (SPARK_POINTS - 1) * POLL_SECONDS;

    useEffect(() => {
        const prevCount = sampleCountRef.current;
        sampleCountRef.current = sampleCount;
        targetRef.current = target;

        // Reset when history cleared (ganti router/interface)
        if (sampleCount <= 1) {
            displayRef.current = [...target];
            yMaxRef.current = niceMax(Math.max(...target, 1));
            setDisplay([...target]);
            setYMax(yMaxRef.current);
            return;
        }

        // Geser kiri hanya saat buffer penuh + sample baru (efek scroll mulus)
        if (prevCount >= SPARK_POINTS && sampleCount >= SPARK_POINTS) {
            const prev = displayRef.current;
            displayRef.current = [
                ...prev.slice(1),
                prev[prev.length - 1] ?? target[target.length - 1] ?? 0,
            ];
        }
    }, [target, sampleCount]);

    useEffect(() => {
        let raf = 0;

        const paint = (linePath, areaPath, max) => {
            if (lineRef.current) lineRef.current.setAttribute('d', linePath);
            if (fillRef.current) fillRef.current.setAttribute('d', areaPath);

            [0, 0.5, 1].forEach((ratio, index) => {
                const y = MARGIN.top + plotH - ratio * plotH;
                const grid = gridRefs.current[index];
                const label = yLabelRefs.current[index];
                if (grid) {
                    grid.setAttribute('y1', String(y));
                    grid.setAttribute('y2', String(y));
                }
                if (label) {
                    label.setAttribute('y', String(y + 3));
                    label.textContent = formatAxisBitrate(max * ratio);
                }
            });
        };

        const loop = () => {
            const goal = targetRef.current;
            const current = displayRef.current;
            const next = current.map((value, index) => {
                const g = goal[index] ?? 0;
                return value + (g - value) * SMOOTH;
            });
            displayRef.current = next;

            const needed = niceMax(Math.max(...goal, 1));
            const currentMax = yMaxRef.current || needed;
            const rate = needed > currentMax ? YMAX_UP : YMAX_DOWN;
            yMaxRef.current = currentMax + (needed - currentMax) * rate;

            const linePath = buildSmoothPath(next, yMaxRef.current, plotW, plotH);
            const areaPath = buildAreaPath(next, yMaxRef.current, plotW, plotH);
            paint(linePath, areaPath, yMaxRef.current);

            // Sinkronisasi React lebih jarang agar animasi SVG tetap 60fps
            setDisplay((prev) => {
                const drifted = prev.some((value, index) => Math.abs(value - next[index]) > 1);
                return drifted ? next : prev;
            });
            setYMax((prev) =>
                Math.abs(prev - yMaxRef.current) / Math.max(prev, 1) > 0.08
                    ? yMaxRef.current
                    : prev,
            );

            raf = requestAnimationFrame(loop);
        };

        raf = requestAnimationFrame(loop);
        return () => cancelAnimationFrame(raf);
    }, [plotH, plotW]);

    const linePath = buildSmoothPath(display, yMax, plotW, plotH);
    const areaPath = buildAreaPath(display, yMax, plotW, plotH);
    const yTicks = [0, 0.5, 1];
    const xTicks = [
        { label: `-${windowSeconds}s`, x: MARGIN.left, anchor: 'start' },
        { label: `-${Math.round(windowSeconds / 2)}s`, x: MARGIN.left + plotW / 2, anchor: 'middle' },
        { label: 'sekarang', x: MARGIN.left + plotW, anchor: 'end' },
    ];

    return (
        <div className="mt-2 -mx-1 sm:-mx-2">
            <svg
                viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}
                className={`block w-full ${strokeClass}`}
                style={{ aspectRatio: `${CHART_WIDTH} / ${CHART_HEIGHT}`, maxHeight: 140 }}
                preserveAspectRatio="xMidYMid meet"
                role="img"
                aria-label="Grafik traffic live"
            >
                {yTicks.map((ratio, index) => {
                    const y = MARGIN.top + plotH - ratio * plotH;
                    return (
                        <g key={`y-${ratio}`}>
                            <line
                                ref={(node) => {
                                    gridRefs.current[index] = node;
                                }}
                                x1={MARGIN.left}
                                y1={y}
                                x2={MARGIN.left + plotW}
                                y2={y}
                                className={axisClass}
                                stroke="currentColor"
                                strokeWidth="1"
                                strokeOpacity={ratio === 0 ? 0.35 : 0.14}
                            />
                            <text
                                ref={(node) => {
                                    yLabelRefs.current[index] = node;
                                }}
                                x={MARGIN.left - 8}
                                y={y + 3}
                                textAnchor="end"
                                className={`fill-current ${axisClass}`}
                                style={{ fontSize: 10 }}
                            >
                                {formatAxisBitrate(yMax * ratio)}
                            </text>
                        </g>
                    );
                })}

                <line
                    x1={MARGIN.left}
                    y1={MARGIN.top + plotH}
                    x2={MARGIN.left + plotW}
                    y2={MARGIN.top + plotH}
                    className={axisClass}
                    stroke="currentColor"
                    strokeWidth="1"
                    strokeOpacity="0.35"
                />
                {xTicks.map((tick) => (
                    <text
                        key={tick.label}
                        x={tick.x}
                        y={CHART_HEIGHT - 8}
                        textAnchor={tick.anchor}
                        className={`fill-current ${axisClass}`}
                        style={{ fontSize: 10 }}
                    >
                        {tick.label}
                    </text>
                ))}

                <path
                    ref={fillRef}
                    d={areaPath}
                    fill="currentColor"
                    stroke="none"
                    opacity="0.14"
                />
                <path
                    ref={lineRef}
                    d={linePath}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.25"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
            </svg>
            <div className="mt-0.5 flex items-center justify-between px-1 text-[10px] font-semibold tracking-wide text-ink/40 uppercase">
                <span>Y: Bitrate</span>
                <span>X: Waktu (interval {POLL_SECONDS}s)</span>
            </div>
        </div>
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
        const timer = window.setInterval(fetchTraffic, POLL_SECONDS * 1000);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [routerId, selected]);

    const selectedMeta = useMemo(
        () => physicalInterfaces.find((item) => item.name === selected),
        [physicalInterfaces, selected],
    );

    const { auth } = usePage().props;
    const canAddRouter = Boolean(auth?.user?.is_superadmin);

    if (multiRouter && routers.length === 0) {
        return (
            <div className="border border-ink/10 bg-white p-5">
                <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                    <Activity className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                    Live Traffic
                </h2>
                <p className="mt-3 text-sm text-ink-soft">
                    Belum ada router aktif.
                    {canAddRouter
                        ? ' Tambahkan router MikroTik terlebih dahulu.'
                        : ' Hubungi Superadmin untuk menambahkan router.'}
                </p>
                {canAddRouter && (
                    <a
                        href="/admin/network/routeros/create"
                        className="mt-3 inline-flex text-sm font-semibold text-signal-deep hover:underline"
                    >
                        Tambah Router
                    </a>
                )}
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
                        Update tiap {POLL_SECONDS} detik dari API monitor-traffic
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

            <div className="mt-4 grid gap-3 xl:grid-cols-2">
                <div className="border border-sky-200/80 bg-sky-50/70 p-3 sm:p-4">
                    <div className="flex items-start justify-between gap-2">
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-sky-700 uppercase">
                            <ArrowDownToLine className="h-3.5 w-3.5 text-sky-600" />
                            Download (RX)
                        </p>
                        <p className="text-right text-xs text-sky-700/70">
                            {traffic?.rx_pps ?? 0} paket/detik
                        </p>
                    </div>
                    <p className="font-hero mt-1 text-2xl tracking-tight text-sky-950">
                        {formatBitrate(traffic?.rx_bps)}
                    </p>
                    <TrafficChart
                        values={history.rx}
                        strokeClass="text-sky-500"
                        axisClass="text-sky-800/55"
                    />
                </div>

                <div className="border border-orange-200/80 bg-orange-50/70 p-3 sm:p-4">
                    <div className="flex items-start justify-between gap-2">
                        <p className="flex items-center gap-2 text-xs font-semibold tracking-wide text-orange-700 uppercase">
                            <ArrowUpFromLine className="h-3.5 w-3.5 text-orange-600" />
                            Upload (TX)
                        </p>
                        <p className="text-right text-xs text-orange-700/70">
                            {traffic?.tx_pps ?? 0} paket/detik
                        </p>
                    </div>
                    <p className="font-hero mt-1 text-2xl tracking-tight text-orange-950">
                        {formatBitrate(traffic?.tx_bps)}
                    </p>
                    <TrafficChart
                        values={history.tx}
                        strokeClass="text-orange-500"
                        axisClass="text-orange-800/55"
                    />
                </div>
            </div>
        </div>
    );
}
