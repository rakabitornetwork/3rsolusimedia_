import { Link } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useMemo, useState } from 'react';

const CHART_WIDTH = 640;
const CHART_HEIGHT = 148;
const MARGIN = { top: 10, right: 12, bottom: 28, left: 36 };

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

function indexShowsLabel(index, every, total) {
    if (every <= 1) return true;
    return index % every === 0 || index === total - 1;
}

function CountBarChart({ points, xLabel, yLabel }) {
    const totals = points.map((point) => point.total || 0);
    const yMax = niceMax(Math.max(...totals, 1));
    const plotW = CHART_WIDTH - MARGIN.left - MARGIN.right;
    const plotH = CHART_HEIGHT - MARGIN.top - MARGIN.bottom;
    const count = Math.max(points.length, 1);
    const gap = Math.min(8, plotW / (count * 3.2));
    const barW = Math.max(4, (plotW - gap * (count + 1)) / count);
    const yTicks = [...new Set([0, Math.round(yMax / 2), yMax])];
    const labelEvery = points.length > 16 ? 5 : points.length > 10 ? 2 : 1;

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

                {yTicks.map((value) => {
                    const y = MARGIN.top + plotH - (value / yMax) * plotH;
                    return (
                        <g key={`y-${value}`}>
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
                                {value}
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
                            className="fill-emerald-600"
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

function statusClass(status) {
    if (status === 'isolated') return 'text-rose-700';
    if (status === 'disabled') return 'text-ink-soft';
    return 'text-teal-700';
}

export default function NewCustomersCard({ data }) {
    const charts = data?.charts;
    const tabs = useMemo(
        () => ['daily', 'monthly'].map((key) => charts?.[key]).filter(Boolean),
        [charts],
    );
    const [activeKey, setActiveKey] = useState(tabs[0]?.key || 'daily');
    const active = tabs.find((tab) => tab.key === activeKey) || tabs[0];
    const recent = data?.recent || [];

    if (!active) {
        return null;
    }

    const sum = (active.points || []).reduce((acc, point) => acc + (point.total || 0), 0);

    return (
        <div className="grid gap-5 xl:grid-cols-5">
            <div className="border border-ink/10 bg-white p-5 xl:col-span-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 className="font-display flex items-center gap-2 text-base font-bold text-ink">
                            <UserPlus className="h-4 w-4 text-emerald-700" strokeWidth={1.75} />
                            Grafik pelanggan baru
                        </h2>
                        <p className="mt-1 text-xs text-ink-soft">
                            {active.subtitle} · {sum} pelanggan
                        </p>
                    </div>
                    <div className="inline-flex border border-ink/15 bg-paper">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() => setActiveKey(tab.key)}
                                className={`px-3 py-2 text-xs font-semibold ${
                                    active.key === tab.key
                                        ? 'bg-emerald-700 text-white'
                                        : 'text-ink-soft hover:bg-mist'
                                }`}
                            >
                                {tab.title}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="mt-4 border border-ink/10 bg-mist/30 p-3 sm:p-4">
                    <CountBarChart
                        points={active.points || []}
                        xLabel={active.x_label}
                        yLabel={active.y_label}
                    />
                </div>
            </div>

            <div className="border border-ink/10 bg-white xl:col-span-2">
                <div className="flex items-center justify-between border-b border-ink/10 px-4 py-3">
                    <div>
                        <h3 className="text-sm font-semibold text-ink">Terdaftar bulan ini</h3>
                        <p className="text-xs text-ink-soft">
                            {data?.total_this_month ?? 0} pelanggan · {data?.month_label}
                        </p>
                    </div>
                    <Link
                        href="/admin/customers/pppoe"
                        className="text-xs font-semibold text-signal-deep hover:underline"
                    >
                        Semua
                    </Link>
                </div>
                <ul className="divide-y divide-ink/5">
                    {recent.length === 0 && (
                        <li className="px-4 py-8 text-center text-sm text-ink-soft">
                            Belum ada pelanggan baru bulan ini.
                        </li>
                    )}
                    {recent.map((item) => (
                        <li key={item.id} className="flex items-center justify-between gap-3 px-4 py-3">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-ink">{item.name}</p>
                                <p className="truncate text-xs text-ink-soft">
                                    {item.username}
                                    {item.package ? ` · ${item.package}` : ''}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-sm font-semibold text-ink">{item.registered_label}</p>
                                <Link
                                    href={`/admin/customers/pppoe/${item.id}/edit`}
                                    className={`text-xs font-semibold hover:underline ${statusClass(item.status)}`}
                                >
                                    {item.status_label}
                                </Link>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
