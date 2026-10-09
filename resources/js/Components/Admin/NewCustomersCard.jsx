import { Link } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useMemo, useState } from 'react';
import PremiumBarChart from './PremiumBarChart';

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

                <div className="mt-4 border border-ink/10 bg-[linear-gradient(180deg,#ffffff_0%,#f3f7f5_100%)] p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.9)] sm:p-4">
                    <PremiumBarChart
                        points={active.points || []}
                        xLabel={active.x_label}
                        yLabel={active.y_label}
                        tone="emerald"
                        marginLeft={36}
                        gapCap={8}
                        minBar={4}
                        integerTicks
                        labelEvery={
                            (active.points || []).length > 16
                                ? 5
                                : (active.points || []).length > 10
                                  ? 2
                                  : 1
                        }
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
