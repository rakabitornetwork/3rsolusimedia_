import {
    Activity,
    ArrowDownToLine,
    ArrowUpFromLine,
    WifiOff,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

const POLL_SECONDS = 3;
const SPARK_POINTS = 32;
const CHART_W = 360;
const CHART_H = 88;
const MARGIN = { top: 10, right: 8, bottom: 8, left: 8 };
const SMOOTH = 0.18;
const PEAK_SOFTEN = 0.38;

function formatBitrate(bps) {
    if (bps == null || Number.isNaN(Number(bps))) return '0 bps';
    let n = Number(bps);
    const units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
    let i = 0;
    while (n >= 1000 && i < units.length - 1) {
        n /= 1000;
        i += 1;
    }
    return `${n.toFixed(i === 0 ? 0 : n >= 10 ? 1 : 2)} ${units[i]}`;
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

function softenPeaks(values, amount = PEAK_SOFTEN) {
    if (values.length < 3) return values;
    return values.map((value, index) => {
        if (index === 0 || index === values.length - 1) return value;
        const neighborAvg = (values[index - 1] + values[index + 1]) / 2;
        return value * (1 - amount) + neighborAvg * amount;
    });
}

function toPoints(values, max) {
    const plotW = CHART_W - MARGIN.left - MARGIN.right;
    const plotH = CHART_H - MARGIN.top - MARGIN.bottom;
    const safeMax = Math.max(max, 1);
    const last = Math.max(values.length - 1, 1);
    return values.map((value, index) => ({
        x: MARGIN.left + (index / last) * plotW,
        y: MARGIN.top + plotH - (value / safeMax) * plotH,
    }));
}

function buildSmoothPath(values, max) {
    const points = toPoints(softenPeaks(values), max);
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

function buildAreaPath(values, max) {
    const line = buildSmoothPath(values, max);
    if (!line) return '';
    const baseY = MARGIN.top + (CHART_H - MARGIN.top - MARGIN.bottom);
    const plotW = CHART_W - MARGIN.left - MARGIN.right;
    return `${line} L ${(MARGIN.left + plotW).toFixed(2)} ${baseY.toFixed(2)} L ${MARGIN.left.toFixed(2)} ${baseY.toFixed(2)} Z`;
}

function useSmoothSeries(values) {
    const target = useMemo(() => padHistory(values), [values]);
    const [display, setDisplay] = useState(target);
    const [yMax, setYMax] = useState(() => niceMax(Math.max(...target, 1)));
    const displayRef = useRef(target);
    const targetRef = useRef(target);
    const yMaxRef = useRef(yMax);

    useEffect(() => {
        targetRef.current = target;
    }, [target]);

    useEffect(() => {
        let frame = 0;
        const tick = () => {
            const current = displayRef.current;
            const goal = targetRef.current;
            const next = current.map((value, index) => {
                const want = goal[index] ?? 0;
                return value + (want - value) * SMOOTH;
            });
            displayRef.current = next;
            setDisplay(next);

            const peak = Math.max(...next, 1);
            const desired = niceMax(peak * 1.12);
            const blended = yMaxRef.current + (desired - yMaxRef.current) * 0.12;
            yMaxRef.current = blended;
            setYMax(blended);

            frame = window.requestAnimationFrame(tick);
        };
        frame = window.requestAnimationFrame(tick);
        return () => window.cancelAnimationFrame(frame);
    }, []);

    return { display, yMax };
}

function TrafficLane({
    label,
    icon: Icon,
    value,
    values,
    stroke,
    fillFrom,
    fillTo,
    accentClass,
}) {
    const { display, yMax } = useSmoothSeries(values);
    const linePath = buildSmoothPath(display, yMax);
    const areaPath = buildAreaPath(display, yMax);
    const gradId = `portal-traffic-${label.replace(/\s+/g, '-').toLowerCase()}`;

    return (
        <div className="relative overflow-hidden rounded-sm bg-white/70 px-3.5 py-3 backdrop-blur-sm">
            <div className="relative z-10 flex items-start justify-between gap-3">
                <div className="flex items-center gap-2">
                    <span
                        className={`inline-flex h-7 w-7 items-center justify-center ${accentClass}`}
                    >
                        <Icon className="h-3.5 w-3.5" strokeWidth={2.25} />
                    </span>
                    <div>
                        <p className="text-[11px] font-semibold tracking-[0.14em] text-ink-soft uppercase">
                            {label}
                        </p>
                        <p className="mt-0.5 font-mono text-lg font-semibold tracking-tight text-ink tabular-nums transition-all duration-500">
                            {formatBitrate(value)}
                        </p>
                    </div>
                </div>
            </div>

            <svg
                viewBox={`0 0 ${CHART_W} ${CHART_H}`}
                className="relative z-10 mt-2 h-[72px] w-full"
                preserveAspectRatio="none"
            >
                <defs>
                    <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={fillFrom} stopOpacity="0.35" />
                        <stop offset="100%" stopColor={fillTo} stopOpacity="0.02" />
                    </linearGradient>
                </defs>
                <path d={areaPath} fill={`url(#${gradId})`} />
                <path
                    d={linePath}
                    fill="none"
                    stroke={stroke}
                    strokeWidth="2.4"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        </div>
    );
}

export default function PortalLiveTraffic({ token }) {
    const [traffic, setTraffic] = useState(null);
    const [history, setHistory] = useState({ rx: [], tx: [] });
    const [error, setError] = useState('');
    const [online, setOnline] = useState(null);
    const [fresh, setFresh] = useState(false);

    useEffect(() => {
        if (!token) return undefined;

        let cancelled = false;
        let busy = false;

        const fetchTraffic = async () => {
            if (busy || cancelled) return;
            busy = true;

            try {
                const response = await fetch(`/bayar/${token}/trafik`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json();
                if (cancelled) return;

                setOnline(Boolean(payload.online));

                if (!payload.ok) {
                    setError(payload.message || 'Traffic tidak tersedia');
                    setTraffic(null);
                    return;
                }

                setError('');
                setTraffic(payload.data);
                setFresh(true);
                window.setTimeout(() => {
                    if (!cancelled) setFresh(false);
                }, 700);
                setHistory((prev) => ({
                    rx: [...prev.rx, payload.data.rx_bps].slice(-SPARK_POINTS),
                    tx: [...prev.tx, payload.data.tx_bps].slice(-SPARK_POINTS),
                }));
            } catch {
                if (!cancelled) {
                    setError('Tidak bisa mengambil live traffic');
                    setOnline(null);
                }
            } finally {
                busy = false;
            }
        };

        setHistory({ rx: [], tx: [] });
        setTraffic(null);
        setError('');
        fetchTraffic();
        const timer = window.setInterval(fetchTraffic, POLL_SECONDS * 1000);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
        };
    }, [token]);

    const statusLabel =
        online === null
            ? 'Menyambungkan…'
            : online
              ? 'Sesi aktif'
              : 'Sesi offline';

    return (
        <section className="overflow-hidden border border-ink/10 bg-gradient-to-br from-[#0b1526] via-[#12203a] to-[#0f2f5c] p-5 text-white shadow-[0_18px_40px_rgba(11,21,38,0.18)]">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <Activity className="h-4 w-4 text-[#7dd3fc]" />
                        <p className="text-[11px] font-semibold tracking-[0.18em] text-white/60 uppercase">
                            Live trafik
                        </p>
                    </div>
                    <h3 className="mt-1.5 text-base font-semibold tracking-tight text-white">
                        {statusLabel}
                    </h3>
                    <p className="mt-1 text-xs text-white/55">
                        Update realtime setiap {POLL_SECONDS} detik dari sesi PPPoE Anda.
                    </p>
                </div>
                <span
                    className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold ${
                        online
                            ? 'bg-emerald-400/15 text-emerald-200'
                            : online === false
                              ? 'bg-white/10 text-white/60'
                              : 'bg-sky-400/15 text-sky-200'
                    }`}
                >
                    <span
                        className={`h-1.5 w-1.5 rounded-full ${
                            online
                                ? `bg-emerald-300 ${fresh ? 'animate-ping' : 'animate-pulse'}`
                                : 'bg-white/40'
                        }`}
                    />
                    LIVE
                </span>
            </div>

            {error && !traffic ? (
                <div className="mt-4 flex items-start gap-2 border border-white/10 bg-white/5 px-3.5 py-3 text-xs text-white/70">
                    <WifiOff className="mt-0.5 h-3.5 w-3.5 shrink-0 text-white/50" />
                    {error}
                </div>
            ) : (
                <div className="mt-4 grid gap-3">
                    <TrafficLane
                        label="Download"
                        icon={ArrowDownToLine}
                        value={traffic?.rx_bps}
                        values={history.rx}
                        stroke="#2dd4bf"
                        fillFrom="#2dd4bf"
                        fillTo="#0f766e"
                        accentClass="bg-teal-400/15 text-teal-200"
                    />
                    <TrafficLane
                        label="Upload"
                        icon={ArrowUpFromLine}
                        value={traffic?.tx_bps}
                        values={history.tx}
                        stroke="#38bdf8"
                        fillFrom="#38bdf8"
                        fillTo="#0369a1"
                        accentClass="bg-sky-400/15 text-sky-200"
                    />
                </div>
            )}
        </section>
    );
}
