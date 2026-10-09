import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Coins } from 'lucide-react';
import PremiumBarChart from './PremiumBarChart';

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

            <div className="mt-4 border border-ink/10 bg-[linear-gradient(180deg,#ffffff_0%,#f3f6fa_100%)] p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)] sm:p-4">
                <PremiumBarChart
                    points={active.points || []}
                    xLabel={active.x_label}
                    yLabel={active.y_label}
                    formatY={formatAxisRp}
                    tone="signal"
                    labelEvery={(active.points || []).length > 10 ? 2 : 1}
                />
            </div>
        </div>
    );
}
