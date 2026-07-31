import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    Coins,
    FilePlus2,
    Hourglass,
    Search,
    ShieldCheck,
    Trash2,
    WalletCards,
} from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';

function StatWidget({ label, value, gradient, icon: Icon }) {
    return (
        <div
            className={`flex h-full min-h-[132px] flex-col p-4 text-white shadow-sm ${gradient}`}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] tracking-wide text-white/75 uppercase">{label}</p>
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center bg-white/15">
                    <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                </span>
            </div>
            <p className="font-display mt-3 text-2xl font-bold text-white">{value}</p>
            <p className="mt-auto pt-2 text-xs text-white/70">{'\u00a0'}</p>
        </div>
    );
}

function StatusBadge({ status, overdue }) {
    if (overdue && status === 'unpaid') {
        return (
            <span className="bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                Jatuh tempo
            </span>
        );
    }

    const map = {
        unpaid: 'bg-ink/10 text-ink-soft',
        paid: 'bg-signal/15 text-signal-deep',
        void: 'bg-red-50 text-red-600',
    };

    const label = {
        unpaid: 'Belum bayar',
        paid: 'Lunas',
        void: 'Dibatalkan',
    };

    return (
        <span className={`px-2 py-1 text-xs font-semibold ${map[status] || map.unpaid}`}>
            {label[status] || status}
        </span>
    );
}

function QuickPayButton({ invoice, methods }) {
    const { data, setData, post, processing } = useForm({
        method: 'cash',
        reference: '',
        notes: '',
    });

    if (invoice.status !== 'unpaid') return null;

    const pay = () => {
        if (!window.confirm(`Tandai lunas tagihan ${invoice.number} (${invoice.total_label})?`)) {
            return;
        }
        post(`/admin/billing/invoices/${invoice.id}/pay`);
    };

    return (
        <div className="flex items-center gap-2">
            <select
                value={data.method}
                onChange={(e) => setData('method', e.target.value)}
                className="border border-ink/15 px-2 py-1.5 text-xs outline-none focus:border-signal"
            >
                {methods.map((item) => (
                    <option key={item.value} value={item.value}>
                        {item.label}
                    </option>
                ))}
            </select>
            <button
                type="button"
                onClick={pay}
                disabled={processing}
                className="inline-flex items-center gap-1 border border-signal/30 bg-signal/10 px-2.5 py-1.5 text-xs font-semibold text-signal-deep hover:bg-signal/20 disabled:opacity-60"
            >
                <CheckCircle2 className="h-3.5 w-3.5" />
                Lunas
            </button>
        </div>
    );
}

export default function Index({ invoices, filters, stats, payment_methods }) {

    const applyFilters = (key, value) => {
        router.get(
            '/admin/billing',
            {
                ...filters,
                [key]: value,
                overdue: key === 'overdue' ? value : filters.overdue || false,
            },
            { preserveState: true, replace: true },
        );
    };

    const generate = () => {
        if (
            !window.confirm(
                'Buat tagihan untuk pelanggan aktif yang jatuh tempo dalam 7 hari (atau sudah lewat) dan belum punya tagihan?',
            )
        ) {
            return;
        }
        router.post('/admin/billing/generate');
    };

    const remove = (invoice) => {
        if (invoice.status === 'paid') {
            if (
                !window.confirm(
                    `Tagihan ${invoice.number} sudah lunas. Batalkan (void) dulu? Setelah void, Anda bisa menghapusnya.`,
                )
            ) {
                return;
            }
            router.post(`/admin/billing/invoices/${invoice.id}/void`);
            return;
        }

        if (
            !window.confirm(
                `Hapus tagihan ${invoice.number} (${invoice.total_label})? Tindakan ini tidak bisa dibatalkan.`,
            )
        ) {
            return;
        }
        router.delete(`/admin/billing/invoices/${invoice.id}`);
    };

    return (
        <AdminLayout
            title="Tagihan & Pembayaran"
            subtitle="Tagihan bulanan muncul otomatis 7 hari sebelum jatuh tempo"
        >
            <Head title="Tagihan & Pembayaran" />

            <div className="mb-5 grid items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatWidget
                    label="Belum bayar"
                    value={stats.unpaid}
                    gradient="bg-gradient-to-br from-red-500 to-red-900"
                    icon={WalletCards}
                />
                <StatWidget
                    label="Jatuh tempo"
                    value={stats.overdue}
                    gradient="bg-gradient-to-br from-yellow-400 to-yellow-700"
                    icon={Hourglass}
                />
                <StatWidget
                    label="Lunas bulan ini"
                    value={stats.paid_this_month}
                    gradient="bg-gradient-to-br from-lime-400 to-green-800"
                    icon={ShieldCheck}
                />
                <StatWidget
                    label="Omzet bulan ini"
                    value={stats.collected_this_month_label}
                    gradient="bg-gradient-to-br from-indigo-500 to-indigo-900"
                    icon={Coins}
                />
            </div>

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft" />
                        <input
                            type="search"
                            defaultValue={filters.q}
                            placeholder="Cari invoice / pelanggan..."
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') applyFilters('q', e.target.value);
                            }}
                            className="w-64 border border-ink/15 py-2 pr-3 pl-9 text-sm outline-none focus:border-signal"
                        />
                    </div>
                    <select
                        value={filters.status || ''}
                        onChange={(e) => applyFilters('status', e.target.value)}
                        className="border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal"
                    >
                        <option value="">Semua status</option>
                        <option value="unpaid">Belum bayar</option>
                        <option value="paid">Lunas</option>
                        <option value="void">Dibatalkan</option>
                    </select>
                    <label className="inline-flex items-center gap-2 border border-ink/15 px-3 py-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            checked={Boolean(filters.overdue)}
                            onChange={(e) => applyFilters('overdue', e.target.checked)}
                        />
                        Jatuh tempo saja
                    </label>
                </div>

                <button
                    type="button"
                    onClick={generate}
                    className="inline-flex items-center gap-2 bg-signal-deep px-4 py-2.5 text-sm font-semibold text-white hover:bg-ink"
                >
                    <FilePlus2 className="h-4 w-4" />
                    Generate Tagihan
                </button>
            </div>

            <div className="overflow-hidden border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Invoice</th>
                            <th className="px-4 py-3 font-semibold">Pelanggan</th>
                            <th className="hidden px-4 py-3 font-semibold md:table-cell">Tipe</th>
                            <th className="px-4 py-3 font-semibold">Jatuh tempo</th>
                            <th className="px-4 py-3 font-semibold">Total</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.data.map((item) => (
                            <tr key={item.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3">
                                    <Link
                                        href={`/admin/billing/invoices/${item.id}`}
                                        className="font-medium text-signal-deep hover:underline"
                                    >
                                        {item.number}
                                    </Link>
                                    <p className="text-xs text-ink-soft">{item.package_name || '—'}</p>
                                </td>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{item.customer?.name || '—'}</p>
                                    <p className="text-xs text-ink-soft">{item.customer?.username}</p>
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft md:table-cell">
                                    {item.type_label}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{item.due_date}</td>
                                <td className="px-4 py-3 font-medium text-ink">{item.total_label}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge status={item.status} overdue={item.is_overdue} />
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        <QuickPayButton invoice={item} methods={payment_methods} />
                                        <Link
                                            href={`/admin/billing/invoices/${item.id}`}
                                            className="border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-ink-soft hover:bg-mist"
                                        >
                                            Detail
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => remove(item)}
                                            className="inline-flex items-center gap-1 border border-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                            title={
                                                item.status === 'paid'
                                                    ? 'Batalkan (void)'
                                                    : 'Hapus tagihan'
                                            }
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            {item.status === 'paid' ? 'Void' : 'Hapus'}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {invoices.data.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-10 text-center text-ink-soft">
                                    Belum ada tagihan. Gunakan Generate Tagihan atau tambah pelanggan baru.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {invoices.links?.length > 3 && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {invoices.links.map((link, index) => (
                        <button
                            key={index}
                            type="button"
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url)}
                            className={`px-3 py-1.5 text-xs font-semibold ${
                                link.active
                                    ? 'bg-signal-deep text-white'
                                    : 'border border-ink/10 text-ink-soft hover:bg-mist'
                            } disabled:opacity-40`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
