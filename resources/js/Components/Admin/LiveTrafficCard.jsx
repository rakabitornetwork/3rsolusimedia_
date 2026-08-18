import { usePage } from '@inertiajs/react';
import { Activity, ArrowDownToLine, ArrowUpFromLine, LoaderCircle } from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';

const SPARK_POINTS = 24;
const POLL_SECONDS = 3;
const CHART_WIDTH = 640;
const CHART_HEIGHT = 132;
const MARGIN = { top: 8, right: 12, bottom: 24, left: 52 };
const SMOOTH = 0.16;
const YMAX_UP = 0.22;
const YMAX_DOWN = 0.035;
const PEAK_SOFTEN = 0.42;

function storageKey(routerId, slot = 1) {
    return slot === 2
        ? `live-traffic-iface2:${routerId}`
        : `live-traffic-iface:${routerId}`;
}

function readStoredInterface(routerId, slot = 1) {
    try {
        return window.localStorage.getItem(storageKey(routerId, slot)) || '';
    } catch {
        return '';
    }
}

function writeStoredInterface(routerId, name, slot = 1) {
    try {
        const key = storageKey(routerId, slot);
        if (name) window.localStorage.setItem(key, name);
        else window.localStorage.removeItem(key);
    } catch {
        // ignore
    }
}

function pickDefaultInterface(list, routerId) {
    if (!list?.length) return '';
    const stored = readStoredInterface(routerId, 1);
    if (stored && list.some((item) => item.name === stored)) return stored;
    const wanRunning = list.find((item) => item.is_wan && item.running);
    if (wanRunning) return wanRunning.name;
    const wan = list.find((item) => item.is_wan);
    if (wan) return wan.name;
    const running = list.find((item) => item.running);
    if (running) return running.name;
    return list[0].name;
}

/** Ethernet kedua: beda dari primary, utamakan WAN lain. */
function pickSecondInterface(list, primary, routerId) {
    if (!list?.length) return '';
    const others = list.filter((item) => item.name !== primary);
    if (!others.length) return '';

    const stored = readStoredInterface(routerId, 2);
    if (stored && stored !== primary && others.some((item) => item.name === stored)) {
        return stored;
    }

    const wanRunning = others.find((item) => item.is_wan && item.running);
    if (wanRunning) return wanRunning.name;
    const wan = others.find((item) => item.is_wan);
    if (wan) return wan.name;
    const running = others.find((item) => item.running);
    if (running) return running.name;
    return others[0].name;
}

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

const GAUGE = {
    width: 240,
    height: 142,
    cx: 120,
    cy: 118,
    radius: 86,
    start: Math.PI,
    sweep: Math.PI,
};

const GAUGE_TONES = {
    rx: {
        from: '#7dd3fc',
        to: '#0284c7',
        needle: '#0e7490',
        track: 'rgba(12, 74, 110, 0.12)',
        tick: 'rgba(12, 74, 110, 0.38)',
        card: 'border-sky-200/80 bg-gradient-to-b from-sky-50/90 to-white',
        label: 'text-sky-800',
        value: 'text-sky-950',
        muted: 'text-sky-700/70',
    },
    tx: {
        from: '#fdba74',
        to: '#ea580c',
        needle: '#c2410c',
        track: 'rgba(154, 52, 18, 0.12)',
        tick: 'rgba(154, 52, 18, 0.38)',
        card: 'border-orange-200/80 bg-gradient-to-b from-orange-50/90 to-white',
        label: 'text-orange-800',
        value: 'text-orange-950',
        muted: 'text-orange-700/70',
    },
};

function gaugePoint(radius, ratio) {
    const angle = GAUGE.start - GAUGE.sweep * Math.min(Math.max(ratio, 0), 1);
    return {
        x: GAUGE.cx + Math.cos(angle) * radius,
        y: GAUGE.cy - Math.sin(angle) * radius,
        angle,
    };
}

function gaugeArc(radius) {
    const start = gaugePoint(radius, 0);
    const end = gaugePoint(radius, 1);
    return `M ${start.x.toFixed(2)} ${start.y.toFixed(2)} A ${radius} ${radius} 0 0 1 ${end.x.toFixed(2)} ${end.y.toFixed(2)}`;
}

function SemiGauge({ id, label, icon: Icon, value, history, pps, tone = 'rx' }) {
    const reactId = useId();
    const uid = `gauge-${String(id || reactId).replace(/[^a-zA-Z0-9_-]/g, '-')}`;
    const palette = GAUGE_TONES[tone] || GAUGE_TONES.rx;
    const target = Math.max(0, Number(value) || 0);
    const targetMax = niceMax(Math.max(target, ...(history || []), 1));

    const displayRef = useRef(0);
    const maxRef = useRef(targetMax);
    const arcRef = useRef(null);
    const needleRef = useRef(null);
    const maxLabelRef = useRef(null);
    const valueLabelRef = useRef(null);

    useEffect(() => {
        let raf = 0;

        const paint = (current, ceiling) => {
            const ratio = current / Math.max(ceiling, 1);
            if (arcRef.current) {
                arcRef.current.setAttribute('stroke-dasharray', `${(ratio * 100).toFixed(2)} 100`);
            }
            if (needleRef.current) {
                const tip = gaugePoint(GAUGE.radius - 18, ratio);
                needleRef.current.setAttribute(
                    'd',
                    `M ${GAUGE.cx.toFixed(2)} ${GAUGE.cy.toFixed(2)} L ${tip.x.toFixed(2)} ${tip.y.toFixed(2)}`,
                );
            }
            if (maxLabelRef.current) {
                maxLabelRef.current.textContent = formatAxisBitrate(ceiling);
            }
            if (valueLabelRef.current) {
                valueLabelRef.current.textContent = formatBitrate(current);
            }
        };

        const loop = () => {
            displayRef.current += (target - displayRef.current) * SMOOTH;
            const needed = targetMax;
            const currentMax = maxRef.current || needed;
            const rate = needed > currentMax ? YMAX_UP : YMAX_DOWN;
            maxRef.current = currentMax + (needed - currentMax) * rate;
            paint(displayRef.current, maxRef.current);
            raf = requestAnimationFrame(loop);
        };

        raf = requestAnimationFrame(loop);
        return () => cancelAnimationFrame(raf);
    }, [target, targetMax]);

    const ticks = [0, 0.25, 0.5, 0.75, 1];

    return (
        <div className={`min-w-0 border p-3 sm:p-4 ${palette.card}`}>
            <div className="flex items-start justify-between gap-2">
                <p className={`flex items-center gap-2 text-xs font-semibold tracking-wide uppercase ${palette.label}`}>
                    <Icon className="h-3.5 w-3.5" />
                    {label}
                </p>
                <p className={`text-right text-[11px] ${palette.muted}`}>
                    {pps ?? 0} paket/detik
                </p>
            </div>

            <div className="relative mx-auto mt-1 w-full max-w-[260px]">
                <svg
                    viewBox={`0 0 ${GAUGE.width} ${GAUGE.height}`}
                    className="block h-auto w-full"
                    role="img"
                    aria-label={`${label} ${formatBitrate(target)}`}
                >
                    <defs>
                        <linearGradient id={`${uid}-fill`} x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stopColor={palette.from} />
                            <stop offset="100%" stopColor={palette.to} />
                        </linearGradient>
                    </defs>

                    <path
                        d={gaugeArc(GAUGE.radius)}
                        fill="none"
                        stroke={palette.track}
                        strokeWidth="14"
                        strokeLinecap="round"
                    />
                    <path
                        ref={arcRef}
                        d={gaugeArc(GAUGE.radius)}
                        fill="none"
                        stroke={`url(#${uid}-fill)`}
                        strokeWidth="14"
                        strokeLinecap="round"
                        pathLength="100"
                        strokeDasharray="0 100"
                    />

                    {ticks.map((tick) => {
                        const outer = gaugePoint(GAUGE.radius - 10, tick);
                        const inner = gaugePoint(GAUGE.radius - 18, tick);
                        return (
                            <line
                                key={tick}
                                x1={inner.x}
                                y1={inner.y}
                                x2={outer.x}
                                y2={outer.y}
                                stroke={palette.tick}
                                strokeWidth={tick === 0 || tick === 1 || tick === 0.5 ? 1.6 : 1}
                            />
                        );
                    })}

                    <path
                        ref={needleRef}
                        d={`M ${GAUGE.cx} ${GAUGE.cy} L ${gaugePoint(GAUGE.radius - 18, 0).x.toFixed(2)} ${gaugePoint(GAUGE.radius - 18, 0).y.toFixed(2)}`}
                        stroke={palette.needle}
                        strokeWidth="2.4"
                        strokeLinecap="round"
                    />
                    <circle
                        cx={GAUGE.cx}
                        cy={GAUGE.cy}
                        r="6.5"
                        fill="#fff"
                        stroke={palette.needle}
                        strokeWidth="2"
                    />
                    <circle cx={GAUGE.cx} cy={GAUGE.cy} r="2.2" fill={palette.needle} />
                </svg>

                <div className="pointer-events-none absolute inset-x-0 top-[42%] text-center">
                    <p
                        ref={valueLabelRef}
                        className={`font-hero text-[1.65rem] leading-none tracking-tight ${palette.value}`}
                    >
                        {formatBitrate(target)}
                    </p>
                </div>
            </div>

            <div className={`mt-0.5 flex items-center justify-between px-1 text-[10px] font-semibold tracking-wide uppercase ${palette.muted}`}>
                <span>0</span>
                <span ref={maxLabelRef}>{formatAxisBitrate(targetMax)}</span>
            </div>
        </div>
    );
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

function softenPeaks(values, amount = PEAK_SOFTEN) {
    if (values.length < 3) return values;
    return values.map((value, index) => {
        if (index === 0 || index === values.length - 1) return value;
        const neighborAvg = (values[index - 1] + values[index + 1]) / 2;
        return value * (1 - amount) + neighborAvg * amount;
    });
}

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

function interfaceOptionLabel(iface) {
    const bits = [iface.name];
    if (iface.is_wan) bits.push('WAN');
    bits.push(iface.running ? 'up' : 'down');
    if (iface.comment) bits.push(iface.comment);
    return bits.join(' · ');
}

/** Zig-zag line chart with stable Y scale and continuous smooth chase. */
function TrafficChart({ values, strokeColor, axisColor }) {
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

        if (sampleCount <= 1) {
            displayRef.current = [...target];
            yMaxRef.current = niceMax(Math.max(...target, 1));
            setDisplay([...target]);
            setYMax(yMaxRef.current);
            return;
        }

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
        <div className="mt-2 w-full min-w-0 overflow-visible">
            <svg
                viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}
                className="block h-auto w-full"
                style={{ aspectRatio: `${CHART_WIDTH} / ${CHART_HEIGHT}`, minHeight: 120, maxHeight: 148 }}
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
                                stroke={axisColor}
                                strokeWidth="1"
                                strokeOpacity={ratio === 0 ? 0.45 : 0.18}
                            />
                            <text
                                ref={(node) => {
                                    yLabelRefs.current[index] = node;
                                }}
                                x={MARGIN.left - 8}
                                y={y + 3}
                                textAnchor="end"
                                fill={axisColor}
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
                    stroke={axisColor}
                    strokeWidth="1.25"
                    strokeOpacity="0.45"
                />
                {xTicks.map((tick) => (
                    <text
                        key={tick.label}
                        x={tick.x}
                        y={CHART_HEIGHT - 8}
                        textAnchor={tick.anchor}
                        fill={axisColor}
                        style={{ fontSize: 10 }}
                    >
                        {tick.label}
                    </text>
                ))}

                <path ref={fillRef} d={areaPath} fill={strokeColor} stroke="none" opacity="0.16" />
                <path
                    ref={lineRef}
                    d={linePath}
                    fill="none"
                    stroke={strokeColor}
                    strokeWidth="2.25"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                />
            </svg>
            <div className="mt-0.5 flex items-center justify-between px-0.5 text-[10px] font-semibold tracking-wide text-ink/40 uppercase">
                <span>Y: Bitrate</span>
                <span>X: Waktu (interval {POLL_SECONDS}s)</span>
            </div>
        </div>
    );
}

/**
 * @param {{
 *   routerId?: number|string|null,
 *   physicalInterfaces?: Array<{name: string, running?: boolean, comment?: string|null, is_wan?: boolean}>,
 *   routers?: Array<{id: number, name: string, host?: string}>|null,
 * }} props
 */
function InterfaceTrafficPanels({ title, meta, traffic, history, chartKey, variant = 'chart' }) {
    if (!meta) {
        return (
            <div className="min-w-0 border border-dashed border-ink/15 bg-mist/20 p-4 text-sm text-ink-soft">
                <p className="text-xs font-semibold tracking-wide text-ink/45 uppercase">{title}</p>
                <p className="mt-2">Pilih ethernet untuk mulai memantau.</p>
            </div>
        );
    }

    if (variant === 'gauge') {
        return (
            <div className="min-w-0 space-y-3">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <p className="text-xs font-semibold tracking-wide text-ink/55 uppercase">
                        {title}
                        <span className="ml-2 font-bold normal-case tracking-normal text-ink">
                            {meta.name}
                        </span>
                        {meta.is_wan ? (
                            <span className="ml-1.5 font-semibold normal-case text-signal-deep">WAN</span>
                        ) : null}
                    </p>
                    <p className="text-[11px] text-ink/45">
                        {meta.running ? 'Running' : 'Down'}
                        {meta.comment ? ` · ${meta.comment}` : ''}
                    </p>
                </div>

                <div className="grid min-w-0 gap-3 sm:grid-cols-2">
                    <SemiGauge
                        id={`${chartKey}-rx`}
                        label="Download (RX)"
                        icon={ArrowDownToLine}
                        value={traffic?.rx_bps}
                        history={history.rx}
                        pps={traffic?.rx_pps}
                        tone="rx"
                    />
                    <SemiGauge
                        id={`${chartKey}-tx`}
                        label="Upload (TX)"
                        icon={ArrowUpFromLine}
                        value={traffic?.tx_bps}
                        history={history.tx}
                        pps={traffic?.tx_pps}
                        tone="tx"
                    />
                </div>
            </div>
        );
    }

    return (
        <div className="min-w-0 space-y-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <p className="text-xs font-semibold tracking-wide text-ink/55 uppercase">
                    {title}
                    <span className="ml-2 font-bold normal-case tracking-normal text-ink">
                        {meta.name}
                    </span>
                    {meta.is_wan ? (
                        <span className="ml-1.5 font-semibold normal-case text-signal-deep">WAN</span>
                    ) : null}
                </p>
                <p className="text-[11px] text-ink/45">
                    {meta.running ? 'Running' : 'Down'}
                    {meta.comment ? ` · ${meta.comment}` : ''}
                </p>
            </div>

            <div className="min-w-0 overflow-visible border border-sky-200/80 bg-sky-50/70 p-3 sm:p-4">
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
                    key={`rx-${chartKey}`}
                    values={history.rx}
                    strokeColor="#0ea5e9"
                    axisColor="rgba(7, 89, 133, 0.55)"
                />
            </div>

            <div className="min-w-0 overflow-visible border border-orange-200/80 bg-orange-50/70 p-3 sm:p-4">
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
                    key={`tx-${chartKey}`}
                    values={history.tx}
                    strokeColor="#f97316"
                    axisColor="rgba(154, 52, 18, 0.55)"
                />
            </div>
        </div>
    );
}

function useLiveTrafficPoll(routerId, ifaceName, slot = 1) {
    const [traffic, setTraffic] = useState(null);
    const [history, setHistory] = useState({ rx: [], tx: [] });
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!ifaceName || !routerId) {
            setTraffic(null);
            setHistory({ rx: [], tx: [] });
            setError('');
            setLoading(false);
            return undefined;
        }

        writeStoredInterface(routerId, ifaceName, slot);

        let cancelled = false;
        let busy = false;

        const fetchTraffic = async () => {
            if (busy || cancelled) return;
            busy = true;
            setLoading(true);

            try {
                const response = await fetch(
                    `/admin/network/routeros/${routerId}/traffic?interface=${encodeURIComponent(ifaceName)}`,
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
                    rx: [...prev.rx, payload.data.rx_bps].slice(-SPARK_POINTS),
                    tx: [...prev.tx, payload.data.tx_bps].slice(-SPARK_POINTS),
                }));
            } catch {
                if (!cancelled) setError('Tidak bisa mengambil data live traffic');
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
    }, [routerId, ifaceName, slot]);

    return { traffic, history, error, loading };
}

/**
 * @param {{
 *   routerId?: number|string|null,
 *   physicalInterfaces?: Array<{name: string, running?: boolean, comment?: string|null, is_wan?: boolean}>,
 *   routers?: Array<{id: number, name: string, host?: string}>|null,
 *   variant?: 'chart'|'gauge',
 * }} props
 */
export default function LiveTrafficCard({
    routerId: initialRouterId = null,
    physicalInterfaces: initialInterfaces = [],
    routers = null,
    variant = 'chart',
}) {
    const multiRouter = Array.isArray(routers);
    const [routerId, setRouterId] = useState(
        () => initialRouterId || routers?.[0]?.id || null,
    );
    const [physicalInterfaces, setPhysicalInterfaces] = useState(initialInterfaces);
    const [selected, setSelected] = useState(() =>
        pickDefaultInterface(initialInterfaces, initialRouterId || routers?.[0]?.id),
    );
    const [selectedB, setSelectedB] = useState(() => {
        const primary = pickDefaultInterface(
            initialInterfaces,
            initialRouterId || routers?.[0]?.id,
        );
        return pickSecondInterface(
            initialInterfaces,
            primary,
            initialRouterId || routers?.[0]?.id,
        );
    });
    const [loadingInterfaces, setLoadingInterfaces] = useState(false);
    const [listError, setListError] = useState('');

    const pollA = useLiveTrafficPoll(routerId, selected, 1);
    const pollB = useLiveTrafficPoll(routerId, selectedB, 2);

    useEffect(() => {
        if (!routerId) return undefined;

        let cancelled = false;

        const loadInterfaces = async () => {
            setLoadingInterfaces(true);
            setListError('');

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
                    if (initialInterfaces?.length && !multiRouter) {
                        setPhysicalInterfaces(initialInterfaces);
                        const primary = pickDefaultInterface(initialInterfaces, routerId);
                        setSelected(primary);
                        setSelectedB(pickSecondInterface(initialInterfaces, primary, routerId));
                    } else {
                        setPhysicalInterfaces([]);
                        setSelected('');
                        setSelectedB('');
                    }
                    setListError(payload.message || 'Gagal mengambil daftar interface');
                    return;
                }

                const list = payload.interfaces || [];
                setPhysicalInterfaces(list);
                setSelected((prev) =>
                    prev && list.some((item) => item.name === prev)
                        ? prev
                        : pickDefaultInterface(list, routerId),
                );
                setSelectedB((prevB) => {
                    // Resolve primary from storage/default for pairing.
                    const primaryStored = readStoredInterface(routerId, 1);
                    const primary =
                        primaryStored && list.some((item) => item.name === primaryStored)
                            ? primaryStored
                            : pickDefaultInterface(list, routerId);
                    if (prevB && prevB !== primary && list.some((item) => item.name === prevB)) {
                        return prevB;
                    }
                    return pickSecondInterface(list, primary, routerId);
                });
                setListError('');
            } catch {
                if (!cancelled) {
                    if (initialInterfaces?.length && !multiRouter) {
                        setPhysicalInterfaces(initialInterfaces);
                        const primary = pickDefaultInterface(initialInterfaces, routerId);
                        setSelected(primary);
                        setSelectedB(pickSecondInterface(initialInterfaces, primary, routerId));
                    }
                    setListError('Tidak bisa mengambil daftar interface');
                }
            } finally {
                if (!cancelled) setLoadingInterfaces(false);
            }
        };

        loadInterfaces();

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [routerId, multiRouter]);

    const selectedMeta = useMemo(
        () => physicalInterfaces.find((item) => item.name === selected),
        [physicalInterfaces, selected],
    );
    const selectedBMeta = useMemo(
        () => physicalInterfaces.find((item) => item.name === selectedB),
        [physicalInterfaces, selectedB],
    );

    const wanCount = useMemo(
        () => physicalInterfaces.filter((item) => item.is_wan).length,
        [physicalInterfaces],
    );

    const { auth } = usePage().props;
    const canAddRouter = Boolean(auth?.user?.is_superadmin);
    const loading = pollA.loading || pollB.loading;
    const error = listError || pollA.error || pollB.error;

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

    return (
        <div className="min-w-0 border border-ink/10 bg-white p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                        <Activity className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                        Live Traffic
                    </h2>
                    <p className="mt-1 text-xs text-ink-soft">
                        Pantau hingga 2 Ethernet (WAN). Update tiap {POLL_SECONDS} detik.
                        {wanCount > 1 ? ` · ${wanCount} interface terdeteksi sebagai WAN` : ''}
                    </p>
                </div>
                <div className="flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-end">
                    {(loading || loadingInterfaces) && (
                        <LoaderCircle
                            className="mb-2 hidden h-4 w-4 animate-spin text-signal-deep sm:block"
                            aria-hidden
                        />
                    )}
                    {multiRouter && (
                        <label className="block min-w-0 text-xs font-semibold text-ink-soft">
                            RouterOS
                            <select
                                value={routerId || ''}
                                onChange={(e) => {
                                    setRouterId(Number(e.target.value) || e.target.value);
                                    setSelected('');
                                    setSelectedB('');
                                    setPhysicalInterfaces([]);
                                }}
                                className="mt-1 block w-full border border-ink/15 bg-paper px-3 py-2 text-sm font-medium text-ink outline-none focus:border-signal sm:min-w-[180px]"
                            >
                                {routers.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                        {item.host ? ` (${item.host})` : ''}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label className="block min-w-0 text-xs font-semibold text-ink-soft">
                        Ethernet 1
                        <select
                            value={selected}
                            onChange={(e) => {
                                const next = e.target.value;
                                setSelected(next);
                                setSelectedB((prev) =>
                                    prev && prev !== next
                                        ? prev
                                        : pickSecondInterface(physicalInterfaces, next, routerId),
                                );
                            }}
                            disabled={loadingInterfaces || physicalInterfaces.length === 0}
                            className="mt-1 block w-full border border-ink/15 bg-paper px-3 py-2 text-sm font-medium text-ink outline-none focus:border-signal disabled:opacity-60 sm:min-w-[200px]"
                        >
                            {physicalInterfaces.length === 0 && (
                                <option value="">
                                    {loadingInterfaces
                                        ? 'Memuat ethernet...'
                                        : 'Tidak ada ethernet'}
                                </option>
                            )}
                            {physicalInterfaces.map((iface) => (
                                <option key={iface.name} value={iface.name}>
                                    {interfaceOptionLabel(iface)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block min-w-0 text-xs font-semibold text-ink-soft">
                        Ethernet 2
                        <select
                            value={selectedB}
                            onChange={(e) => {
                                const next = e.target.value;
                                setSelectedB(next);
                                writeStoredInterface(routerId, next, 2);
                            }}
                            disabled={loadingInterfaces || physicalInterfaces.length === 0}
                            className="mt-1 block w-full border border-ink/15 bg-paper px-3 py-2 text-sm font-medium text-ink outline-none focus:border-signal disabled:opacity-60 sm:min-w-[200px]"
                        >
                            <option value="">Tidak dipilih</option>
                            {physicalInterfaces
                                .filter((iface) => iface.name !== selected)
                                .map((iface) => (
                                    <option key={iface.name} value={iface.name}>
                                        {interfaceOptionLabel(iface)}
                                    </option>
                                ))}
                        </select>
                    </label>
                </div>
            </div>

            {error && (
                <div className="mt-4 border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {error}
                </div>
            )}

            <div className="mt-4 grid min-w-0 gap-4 xl:grid-cols-2">
                <InterfaceTrafficPanels
                    title="Ethernet 1"
                    meta={selectedMeta}
                    traffic={pollA.traffic}
                    history={pollA.history}
                    chartKey={`${routerId}-${selected}`}
                    variant={variant}
                />
                <InterfaceTrafficPanels
                    title="Ethernet 2"
                    meta={selectedBMeta}
                    traffic={pollB.traffic}
                    history={pollB.history}
                    chartKey={`${routerId}-${selectedB || 'none'}`}
                    variant={variant}
                />
            </div>
        </div>
    );
}
