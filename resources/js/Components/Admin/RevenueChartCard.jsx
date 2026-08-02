import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';

const CHART_WIDTH = 720;
const CHART_HEIGHT = 280;
const MARGIN = { top: 24, right: 16, bottom: 48, left: 64 };

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

function RevenueBarChart({ points, xLabel, yLabel }) {
    const totals = points.map((p) => p.total || 0);
    const yMax = niceMax(Math.max(...totals, 1));
    const plotW = CHART_WIDTH - MARGIN.left - MARGIN.right;
    const plotH = CHART_HEIGHT - MARGIN.top - MARGIN.bottom;
    const n = Math.max(points.length, 1);
    const gap = Math.min(18, plotW / (n * 3));
    const barW = Math.max(8, (plotW - gap * (n + 1)) / n);
    const yTicks = [0, 0.25, 0.5, 0.75, 1];

    const bars = points.map((point, index) => {
        const height = ((point.total || 0) / yMax) * plotH;
        const x = MARGIN.left + gap + index * (barW + gap);
        const y = MARGIN.top + plotH - height;
        return { ...point, x, y, height, barW };
    });

    // Sparse X labels when many points
    const labelEvery = points.length > 10 ? 2 : 1;

    return (
        <div className="w-full overflow-x-auto">
            <svg
                viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}
                className="min-w-full"
                role="img"
                aria-label={`${yLabel} terhadap ${xLabel}`}
            >
                <title>{`${yLabel} · ${xLabel}`}</title>

                {/* Plot background */}
                <rect
                    x={MARGIN.left}
                    y={MARGIN.top}
                    width={plotW}
                    height={plotH}
                    className="fill-mist/40"
                />

                {/* Y grid + labels */}
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
                                className="fill-ink/55 text-[10px]"
                            >
                                {formatAxisRp(value)}
                            </text>
                        </g>
                    );
                })}

                {/* Axes */}
                <line
                    x1={MARGIN.left}
                    x2={MARGIN.left}
                    y1={MARGIN.top}
                    y2={MARGIN.top + plotH}
                    className="stroke-ink/40"
                    strokeWidth="1.5"
                />
                <line
                    x1={MARGIN.left}
                    x2={MARGIN.left + plotW}
                    y1={MARGIN.top + plotH}
                    y2={MARGIN.top + plotH}
                    className="stroke-ink/40"
                    strokeWidth="1.5"
                />

                {/* Axis titles */}
                <text
                    x={14}
                    y={MARGIN.top + plotH / 2}
                    textAnchor="middle"
                    transform={`rotate(-90 14 ${MARGIN.top + plotH / 2})`}
                    className="fill-ink/70 text-[11px] font-semibold"
                >
                    {yLabel}
                </text>
                <text
                    x={MARGIN.left + plotW / 2}
                    y={CHART_HEIGHT - 6}
                    textAnchor="middle"
                    className="fill-ink/70 text-[11px] font-semibold"
                >
                    {xLabel}
                </text>

                {/* Bars */}
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
                                y={MARGIN.top + plotH + 16}
                                textAnchor="middle"
                                className="fill-ink/60 text-[9px]"
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

function indexShowsLabel(index, every, total) {
    if (every <= 1) return true;
    return index % every === 0 || index === total - 1;
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
        <div className="border border-ink/10 bg-white">
            <div className="flex flex-col gap-3 border-b border-ink/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-ink">Grafik pendapatan</h3>
                    <p className="text-xs text-ink-soft">
                        {active.subtitle} · total {sumLabel}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <div className="inline-flex border border-ink/10">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveKey(tab.key)}
                                className={`px-3 py-1.5 text-xs font-semibold ${
                                    active.key === tab.key
                                        ? 'bg-signal-deep text-white'
                                        : 'bg-white text-ink-soft hover:bg-mist'
                                }`}
                            >
                                {tab.title}
                            </button>
                        ))}
                    </div>
                    <Link
                        href="/admin/billing/reports"
                        className="text-xs font-semibold text-signal-deep hover:underline"
                    >
                        Laporan lengkap
                    </Link>
                </div>
            </div>

            <div className="p-4">
                <RevenueBarChart
                    points={active.points || []}
                    xLabel={active.x_label}
                    yLabel={active.y_label}
                />
            </div>
        </div>
    );
}
