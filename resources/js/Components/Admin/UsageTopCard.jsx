import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const TABS = [
    { id: 'daily', label: 'Harian' },
    { id: 'weekly', label: 'Mingguan' },
    { id: 'monthly', label: 'Bulanan' },
];

export default function UsageTopCard({ data }) {
    const { auth } = usePage().props;
    const canOpen = auth?.user?.role !== 'agen';
    const [tab, setTab] = useState('daily');
    const current = data?.[tab] || data?.daily;
    const rows = current?.rows || [];

    return (
        <div className="border border-ink/10 bg-white">
            <div className="flex flex-wrap items-end justify-between gap-3 border-b border-ink/10 px-4 py-3">
                <div>
                    <p className="text-sm font-semibold text-ink">10 pemakaian terbesar</p>
                    <p className="mt-0.5 text-xs text-ink-soft">
                        {current?.range_label || '—'} · diurutkan dari total RX + TX
                    </p>
                </div>
                <div className="flex border border-ink/10">
                    {TABS.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => setTab(item.id)}
                            className={`px-3 py-1.5 text-xs font-semibold ${
                                tab === item.id
                                    ? 'bg-signal-deep text-white'
                                    : 'bg-white text-ink-soft hover:bg-mist'
                            }`}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>
            </div>

            {rows.length === 0 ? (
                <p className="px-4 py-8 text-center text-sm text-ink-soft">
                    Belum ada pemakaian tercatat untuk periode ini.
                </p>
            ) : (
                <div className="admin-data-scroll">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-2.5 font-semibold">#</th>
                                <th className="px-4 py-2.5 font-semibold">Pelanggan</th>
                                <th className="px-4 py-2.5 font-semibold">RX</th>
                                <th className="px-4 py-2.5 font-semibold">TX</th>
                                <th className="px-4 py-2.5 font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-b border-ink/5 last:border-0">
                                    <td className="px-4 py-2.5 font-semibold text-ink-soft">{row.rank}</td>
                                    <td className="px-4 py-2.5">
                                        {canOpen ? (
                                            <Link
                                                href={`/admin/customers/pppoe/${row.id}/edit`}
                                                className="font-medium text-ink hover:text-signal-deep hover:underline"
                                            >
                                                {row.name}
                                            </Link>
                                        ) : (
                                            <p className="font-medium text-ink">{row.name}</p>
                                        )}
                                        <p className="text-xs text-ink-soft">{row.username}</p>
                                    </td>
                                    <td className="px-4 py-2.5 font-medium text-sky-700">{row.rx_label}</td>
                                    <td className="px-4 py-2.5 font-medium text-orange-700">{row.tx_label}</td>
                                    <td className="px-4 py-2.5 font-semibold text-ink">{row.total_label}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
