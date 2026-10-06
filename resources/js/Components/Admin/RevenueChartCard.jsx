import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Coins } from 'lucide-react';

/** Selaras dengan LiveTrafficCard (640×132, maxHeight ~140). */
const CHART_WIDTH = 640;
const CHART_HEIGHT = 148;
const MARGIN = { top: 10, right: 12, bottom: 28, left: 52 };

function niceMax(value) {
    if (value <= 0) return 1;
    const exp = Math.floor(Math.log10(value));
    const fraction = value / 10 ** exp;
    let nice;
    if (fraction <= 1) nice = 1;
    else if (fraction <= 2) nice = 2;
    else if (fraction <= 5) nice = 5;
    else nice = 10;
    return nice * 10 ** exp;
}

function formatAxisRp(value) {
    if (value >= 1_000_000_000) {
        return `${(value / 1_000_000_000).toFixed(value % 1_000_000_000 === 0 ? 0 : 1)}Miliar`;
    }
    if (value >= 1_000_000) {
        return `${(value / 1_000_000).toFixed(value % 1_000_000 === 0 ? 0 : 1)}Jt`;
    }
    if (value >= 1_000) {
        return `${(value / 1_000).toFixed(value % 1_000 === 0 ? 0 : 1)}rb`;
    }
    return String(Math.round(value));
}

function indexShowsLabel(index, every, total) {
    if (every <= 1) return true;
    return index % every === 0 || index === total - 1;
}

function RevenueBarChart({ points, xLabel, yLabel }) {
    const totals = points.map((p) => p.total || 0);
    const yMax = niceMax(Math.max(...totals, 1));
    const plotW = CHART_WIDTH - MARGIN.left - MARGIN.right;
    const plotH = CHART_HEIGHT - MARGIN.top - MARGIN.bottom;
    const n = Math.max(points.length, 1);
    const gap = Math.min(12, plotW / (n * 3.2));
    const barW = Math.max(6, (plotW - gap * (n + 1)) / n);
    const yTicks = [0, 0.5, 1];
    const labelEvery = points.length > 10 ? 2 : 1;

    const bars = points.map((point, index) => {
        const height = ((point.total || 0) / yMax) * plotH;
        const x = MARGIN.left + gap + index * (barW + gap);
        const y = MARGIN.top + plotH - height;
        return { ...point, x, y, height, barW };
    });

    return (
        <div className="w-full">
            <div className="mb-1 flex items-center justify-between gap-2 text-[10px] font-semibold tracking-wide text-ink/55 uppercase">
                <span>{yLabel}</span>
                <span>{xLabel}</span>
            </div>
            <svg
                viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}
                className="w-full"
                style={{ aspectRatio: `${CHART_WIDTH} / ${CHART_HEIGHT}`, maxHeight: 156 }}
                role="img"
                aria-label={`${yLabel} terhadap ${xLabel}`}
            >
                <title>{`${yLabel} · ${xLabel}`}</title>

                {yTicks.map((ratio) => {
                    const y = MARGIN.top + plotH - ratio * plotH;
                    const value = yMax * ratio;
                    return (
                        <g key={`y-${ratio}`}>
                            <line
                                x1={MARGIN.left}
                                x2={MARGIN.left + plotW}
                                y1={y}
                                y2={y}
                                className="stroke-ink/10"
                                strokeWidth="1"
                            />
                            <text
                                x={MARGIN.left - 8}
                                y={y + 3}
                                textAnchor="end"
                                className="fill-ink/50 text-[10px]"
                            >
                                {formatAxisRp(value)}
                            </text>
                        </g>
                    );
                })}

                <line
                    x1={MARGIN.left}
                    x2={MARGIN.left}
                    y1={MARGIN.top}
                    y2={MARGIN.top + plotH}
                    className="stroke-ink/35"
                    strokeWidth="1.25"
                />
                <line
                    x1={MARGIN.left}
                    x2={MARGIN.left + plotW}
                    y1={MARGIN.top + plotH}
                    y2={MARGIN.top + plotH}
                    className="stroke-ink/35"
                    strokeWidth="1.25"
                />

                {bars.map((bar, index) => (
                    <g key={bar.key}>
                        <rect
                            x={bar.x}
                            y={bar.y}
                            width={bar.barW}
                            height={Math.max(bar.height, bar.total > 0 ? 2 : 0)}
                            className="fill-signal-deep"
                        >
                            <title>{`${bar.label}: ${bar.total_label}`}</title>
                        </rect>
                        {indexShowsLabel(index, labelEvery, bars.length) && (
                            <text
                                x={bar.x + bar.barW / 2}
                                y={CHART_HEIGHT - 8}
                                textAnchor="middle"
                                className="fill-ink/55 text-[9px]"
                            >
                                {bar.label}
                            </text>
                        )}
                    </g>
                ))}
            </svg>
        </div>
    );
}

export default function RevenueChartCard({ charts }) {
    const tabs = useMemo(
        () =>
            ['daily', 'monthly', 'half_year']
                .map((key) => charts?.[key])
                .filter(Boolean),
        [charts],
    );

    const [activeKey, setActiveKey] = useState(tabs[0]?.key || 'daily');
    const active = tabs.find((tab) => tab.key === activeKey) || tabs[0];

    if (!active) {
        return null;
    }

    const sum = (active.points || []).reduce((acc, p) => acc + (p.total || 0), 0);
    const sumLabel = `Rp ${Number(sum).toLocaleString('id-ID')}`;

    return (
        <div className="border border-ink/10 bg-white p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                        <Coins className="h-4 w-4 text-signal-deep" strokeWidth={1.75} />
                        Grafik pendapatan
                    </h2>
                    <p className="mt-1 text-xs text-ink-soft">
                        {active.subtitle} · total {sumLabel}
                    </p>
                </div>
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                    <div className="inline-flex border border-ink/15 bg-paper">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveKey(tab.key)}
                                className={`px-3 py-2 text-xs font-semibold ${
                                    active.key === tab.key
                                        ? 'bg-signal-deep text-white'
                                        : 'text-ink-soft hover:bg-mist'
                                }`}
                            >
                                {tab.title}
                            </button>
                        ))}
                    </div>
                    <Link
                        href="/admin/billing/reports"
                        className="text-xs font-semibold text-signal-deep hover:underline sm:px-1"
                    >
                        Laporan lengkap
                    </Link>
                </div>
            </div>

            <div className="mt-4 border border-ink/10 bg-mist/30 p-3 sm:p-4">
                <RevenueBarChart
                    points={active.points || []}
                    xLabel={active.x_label}
                    yLabel={active.y_label}
                />
            </div>
        </div>
    );
}
