import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Banknote,
    CalendarRange,
    Clock3,
    Coins,
    CreditCard,
    Receipt,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';

function StatCard({ label, value, hint, icon: Icon, tone }) {
    return (
        <div className={`flex min-h-[110px] flex-col p-4 text-white shadow-sm ${tone}`}>
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] tracking-wide text-white/75 uppercase">{label}</p>
                <span className="inline-flex h-9 w-9 items-center justify-center bg-white/15">
                    <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                </span>
            </div>
            <p className="font-display mt-3 text-xl font-bold leading-snug break-words sm:text-2xl">
                {value}
            </p>
            <p className="mt-auto pt-2 text-xs text-white/75">{hint}</p>
        </div>
    );
}

function MethodBar({ item }) {
    return (
        <div className="border border-ink/10 bg-white px-4 py-3">
            <div className="flex items-center justify-between gap-3 text-sm">
                <div>
                    <p className="font-semibold text-ink">{item.label}</p>
                    <p className="text-xs text-ink-soft">{item.count} transaksi</p>
                </div>
                <div className="text-right">
                    <p className="font-semibold text-ink">{item.total_label}</p>
                    <p className="text-xs text-ink-soft">{item.percent}%</p>
                </div>
            </div>
            <div className="mt-2 h-2 bg-mist">
                <div
                    className="h-full bg-gradient-to-r from-teal-400 to-cyan-600 transition-all"
                    style={{ width: `${Math.min(100, item.percent)}%` }}
                />
            </div>
        </div>
    );
}

export default function Report({
    filters,
    presets,
    summary,
    by_method: byMethod,
    monthly,
    recent_payments: recentPayments,
    top_unpaid: topUnpaid,
}) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    useEffect(() => {
        setFrom(filters.from);
        setTo(filters.to);
    }, [filters.from, filters.to]);

    const applyPreset = (preset) => {
        router.get(
            '/admin/billing/reports',
            { preset },
            { preserveState: true, replace: true },
        );
    };

    const applyCustom = (event) => {
        event.preventDefault();
        router.get(
            '/admin/billing/reports',
            { preset: 'custom', from, to },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AdminLayout
            title="Laporan Keuangan"
            subtitle="Ringkasan omzet, piutang, dan tren pembayaran"
        >
            <Head title="Laporan Keuangan" />

            <section className="mb-6 border border-ink/10 bg-white p-4 sm:p-5">
                <div className="flex flex-wrap items-center gap-2">
                    <CalendarRange className="h-4 w-4 text-signal-deep" />
                    <p className="text-sm font-semibold text-ink">Periode laporan</p>
                </div>
                <div className="mt-3 flex flex-wrap gap-2">
                    {presets
                        .filter((item) => item.value !== 'custom')
                        .map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                onClick={() => applyPreset(item.value)}
                                className={`px-3 py-2 text-xs font-semibold ${
                                    filters.preset === item.value
                                        ? 'bg-signal-deep text-white'
                                        : 'border border-ink/15 text-ink hover:bg-mist'
                                }`}
                            >
                                {item.label}
                            </button>
                        ))}
                </div>
                <form
                    onSubmit={applyCustom}
                    className="mt-3 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end"
                >
                    <label className="block text-sm text-ink">
                        <span className="mb-1 block text-xs font-semibold text-ink-soft">Dari</span>
                        <input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                        />
                    </label>
                    <label className="block text-sm text-ink">
                        <span className="mb-1 block text-xs font-semibold text-ink-soft">Sampai</span>
                        <input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                        />
                    </label>
                    <button
                        type="submit"
                        className="border border-ink/15 bg-white px-4 py-2 text-sm font-semibold text-ink hover:bg-mist"
                    >
                        Terapkan
                    </button>
                    <p className="self-center text-xs text-ink-soft sm:ml-2">
                        Menampilkan {filters.from} s/d {filters.to}
                    </p>
                </form>
            </section>

            <section className="mb-8">
                <h3 className="mb-3 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                    Ringkasan
                </h3>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Omzet periode"
                        value={summary.collected_label}
                        hint={`${summary.transactions} pembayaran masuk`}
                        icon={Coins}
                        tone="bg-gradient-to-br from-teal-400 to-cyan-600"
                    />
                    <StatCard
                        label="Transaksi"
                        value={summary.transactions}
                        hint="Jumlah pembayaran dalam periode"
                        icon={Receipt}
                        tone="bg-gradient-to-br from-indigo-400 to-blue-600"
                    />
                    <StatCard
                        label="Piutang aktif"
                        value={summary.unpaid_total_label}
                        hint={`${summary.unpaid_count} tagihan belum lunas`}
                        icon={Wallet}
                        tone="bg-gradient-to-br from-amber-300 to-orange-500"
                    />
                    <StatCard
                        label="Jatuh tempo"
                        value={summary.overdue_total_label}
                        hint={`${summary.overdue_count} tagihan lewat jatuh tempo`}
                        icon={AlertTriangle}
                        tone="bg-gradient-to-br from-rose-400 to-pink-600"
                    />
                </div>
            </section>

            <section className="mb-8">
                <h3 className="mb-3 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                    Per metode bayar
                </h3>
                <div className="grid gap-3 sm:grid-cols-2">
                    {byMethod.map((item) => (
                        <MethodBar key={item.method} item={item} />
                    ))}
                </div>
            </section>

            <section className="mb-8">
                <h3 className="mb-3 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                    Tren omzet 12 bulan
                </h3>
                <div className="border border-ink/10 bg-white p-4 sm:p-5">
                    <div className="space-y-2.5">
                        {monthly.map((item) => (
                            <div key={item.month} className="grid grid-cols-[5.5rem_1fr_auto] items-center gap-3">
                                <p className="text-xs font-semibold text-ink-soft">{item.label}</p>
                                <div className="h-3 bg-mist">
                                    <div
                                        className="h-full bg-gradient-to-r from-emerald-400 to-teal-600"
                                        style={{ width: `${Math.max(item.percent > 0 ? 4 : 0, item.percent)}%` }}
                                    />
                                </div>
                                <p className="min-w-[7rem] text-right text-xs font-semibold text-ink sm:min-w-[8.5rem] sm:text-sm">
                                    {item.total_label}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            <div className="grid min-w-0 gap-6 xl:grid-cols-2">
                <section className="min-w-0">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                            Pembayaran terbaru
                        </h3>
                        <Link
                            href="/admin/billing?status=paid"
                            className="text-xs font-semibold text-signal-deep hover:underline"
                        >
                            Lihat tagihan
                        </Link>
                    </div>
                    <div className="admin-data-scroll border border-ink/10 bg-white">
                        <table className="text-left text-sm">
                            <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">Invoice</th>
                                    <th className="px-4 py-3 font-semibold">Metode</th>
                                    <th className="px-4 py-3 text-right font-semibold">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentPayments.map((item) => (
                                    <tr key={item.id} className="border-b border-ink/5 last:border-0">
                                        <td className="px-4 py-3">
                                            {item.invoice_id ? (
                                                <Link
                                                    href={`/admin/billing/invoices/${item.invoice_id}`}
                                                    className="font-medium text-signal-deep hover:underline"
                                                >
                                                    {item.invoice_number || `#${item.invoice_id}`}
                                                </Link>
                                            ) : (
                                                <span className="font-medium text-ink">—</span>
                                            )}
                                            <p className="text-xs text-ink-soft">
                                                {item.customer_name || '—'}
                                                {item.paid_at ? ` · ${item.paid_at}` : ''}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-ink-soft">
                                            <span className="inline-flex items-center gap-1">
                                                {item.method === 'cash' ? (
                                                    <Banknote className="h-3.5 w-3.5" />
                                                ) : (
                                                    <CreditCard className="h-3.5 w-3.5" />
                                                )}
                                                {item.method_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold text-ink">
                                            {item.amount_label}
                                        </td>
                                    </tr>
                                ))}
                                {recentPayments.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-10 text-center text-ink-soft">
                                            Belum ada pembayaran pada periode ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="min-w-0">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <h3 className="text-xs font-semibold tracking-wide text-ink-soft uppercase">
                            Tagihan perlu perhatian
                        </h3>
                        <Link
                            href="/admin/billing?overdue=1"
                            className="text-xs font-semibold text-signal-deep hover:underline"
                        >
                            Lihat jatuh tempo
                        </Link>
                    </div>
                    <div className="admin-data-scroll border border-ink/10 bg-white">
                        <table className="text-left text-sm">
                            <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">Pelanggan</th>
                                    <th className="px-4 py-3 font-semibold">Jatuh tempo</th>
                                    <th className="px-4 py-3 text-right font-semibold">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {topUnpaid.map((item) => (
                                    <tr key={item.id} className="border-b border-ink/5 last:border-0">
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/admin/billing/invoices/${item.id}`}
                                                className="font-medium text-signal-deep hover:underline"
                                            >
                                                {item.customer_name || item.number}
                                            </Link>
                                            <p className="text-xs text-ink-soft">
                                                {item.number}
                                                {item.package_name ? ` · ${item.package_name}` : ''}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex items-center gap-1 text-xs font-semibold ${
                                                    item.is_overdue
                                                        ? 'text-rose-600'
                                                        : 'text-ink-soft'
                                                }`}
                                            >
                                                <Clock3 className="h-3.5 w-3.5" />
                                                {item.due_date || '—'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-semibold text-ink">
                                            {item.total_label}
                                        </td>
                                    </tr>
                                ))}
                                {topUnpaid.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-10 text-center text-ink-soft">
                                            Tidak ada tagihan belum lunas.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AdminLayout>
    );
}
