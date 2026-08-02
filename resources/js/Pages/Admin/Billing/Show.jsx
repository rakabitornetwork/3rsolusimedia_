import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Show({ invoice, payment_methods }) {
    const { data, setData, post, processing, errors } = useForm({
        method: 'cash',
        reference: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (!window.confirm(`Konfirmasi pembayaran ${invoice.total_label} untuk ${invoice.number}?`)) {
            return;
        }
        post(`/admin/billing/invoices/${invoice.id}/pay`);
    };

    const remove = () => {
        if (
            !window.confirm(
                `Hapus tagihan ${invoice.number}? Tindakan ini tidak bisa dibatalkan.`,
            )
        ) {
            return;
        }
        router.delete(`/admin/billing/invoices/${invoice.id}`);
    };

    const voidInvoice = () => {
        if (
            !window.confirm(
                `Batalkan (void) tagihan ${invoice.number}? Riwayat pembayaran tetap tersimpan.`,
            )
        ) {
            return;
        }
        router.post(`/admin/billing/invoices/${invoice.id}/void`);
    };

    return (
        <AdminLayout
            title={invoice.number}
            subtitle="Detail tagihan dan riwayat pembayaran"
        >
            <Head title={`Tagihan ${invoice.number}`} />

            <div className="mb-4">
                <Link
                    href="/admin/billing"
                    className="text-sm font-semibold text-signal-deep hover:underline"
                >
                    ← Kembali ke daftar tagihan
                </Link>
            </div>

            <div className="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                <div className="space-y-5">
                    <div className="border border-ink/10 bg-white p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs tracking-wide text-ink-soft uppercase">Invoice</p>
                                <h2 className="mt-1 text-xl font-semibold text-ink">{invoice.number}</h2>
                                <p className="mt-1 text-sm text-ink-soft">{invoice.notes || '—'}</p>
                            </div>
                            <span
                                className={`px-2.5 py-1 text-xs font-semibold ${
                                    invoice.status === 'paid'
                                        ? 'bg-signal/15 text-signal-deep'
                                        : invoice.is_overdue
                                          ? 'bg-amber-50 text-amber-700'
                                          : 'bg-ink/10 text-ink-soft'
                                }`}
                            >
                                {invoice.is_overdue && invoice.status === 'unpaid'
                                    ? 'Jatuh tempo'
                                    : invoice.status_label}
                            </span>
                        </div>

                        <dl className="mt-6 grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs text-ink-soft uppercase">Pelanggan</dt>
                                <dd className="mt-1 text-sm font-medium text-ink">
                                    {invoice.customer?.name || '—'}
                                </dd>
                                <dd className="text-xs text-ink-soft">{invoice.customer?.username}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-soft uppercase">Paket</dt>
                                <dd className="mt-1 text-sm font-medium text-ink">
                                    {invoice.package_name || '—'}
                                </dd>
                                <dd className="text-xs text-ink-soft">{invoice.package_price_label}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-soft uppercase">Periode</dt>
                                <dd className="mt-1 text-sm text-ink">
                                    {invoice.period_start} s/d {invoice.period_end}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-soft uppercase">Jatuh tempo</dt>
                                <dd className="mt-1 text-sm text-ink">{invoice.due_date}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-soft uppercase">Tipe</dt>
                                <dd className="mt-1 text-sm text-ink">{invoice.type_label}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-ink-soft uppercase">Total</dt>
                                <dd className="mt-1 text-lg font-semibold text-ink">{invoice.total_label}</dd>
                            </div>
                        </dl>
                    </div>

                    <div className="border border-ink/10 bg-white p-6">
                        <h3 className="text-sm font-semibold text-ink">Riwayat pembayaran</h3>
                        {invoice.payments?.length ? (
                            <ul className="mt-4 divide-y divide-ink/5">
                                {invoice.payments.map((payment) => (
                                    <li key={payment.id} className="flex flex-wrap justify-between gap-2 py-3">
                                        <div>
                                            <p className="text-sm font-medium text-ink">
                                                {payment.amount_label} · {payment.method_label}
                                            </p>
                                            <p className="text-xs text-ink-soft">
                                                {payment.paid_at
                                                    ? new Date(payment.paid_at).toLocaleString('id-ID')
                                                    : '—'}
                                                {payment.receiver_name
                                                    ? ` · ${payment.receiver_name}`
                                                    : ''}
                                            </p>
                                            {payment.reference && (
                                                <p className="text-xs text-ink-soft">
                                                    Ref: {payment.reference}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-3 text-sm text-ink-soft">Belum ada pembayaran.</p>
                        )}
                    </div>
                </div>

                <div className="border border-ink/10 bg-white p-6">
                    {invoice.status === 'unpaid' ? (
                        <>
                            <h3 className="text-sm font-semibold text-ink">Catat pembayaran</h3>
                            <p className="mt-1 text-sm text-ink-soft">
                                Setelah lunas, jatuh tempo pelanggan dimajukan dan sync MikroTik dijalankan.
                            </p>
                            <form onSubmit={submit} className="mt-5 space-y-4">
                                <label className="block text-sm font-medium text-ink">
                                    Metode
                                    <select
                                        value={data.method}
                                        onChange={(e) => setData('method', e.target.value)}
                                        className={fieldClass}
                                    >
                                        {payment_methods.map((item) => (
                                            <option key={item.value} value={item.value}>
                                                {item.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>

                                <label className="block text-sm font-medium text-ink">
                                    Referensi (opsional)
                                    <input
                                        type="text"
                                        value={data.reference}
                                        onChange={(e) => setData('reference', e.target.value)}
                                        className={fieldClass}
                                        placeholder="No. transfer / bukti"
                                    />
                                    {errors.reference && (
                                        <p className="mt-1 text-xs text-red-600">{errors.reference}</p>
                                    )}
                                </label>

                                <label className="block text-sm font-medium text-ink">
                                    Catatan
                                    <textarea
                                        rows={3}
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        className={fieldClass}
                                    />
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-signal-deep px-5 py-3 text-sm font-bold text-white hover:bg-ink disabled:opacity-60"
                                >
                                    {processing
                                        ? 'Memproses...'
                                        : `Tandai lunas · ${invoice.total_label}`}
                                </button>
                            </form>

                            <button
                                type="button"
                                onClick={remove}
                                className="mt-4 w-full border border-red-200 px-5 py-3 text-sm font-semibold text-red-600 hover:bg-red-50"
                            >
                                Hapus tagihan
                            </button>
                        </>
                    ) : (
                        <>
                            <h3 className="text-sm font-semibold text-ink">Status tagihan</h3>
                            <p className="mt-2 text-sm text-ink-soft">
                                Tagihan ini sudah {invoice.status_label.toLowerCase()}.
                            </p>
                            {invoice.customer && (
                                <Link
                                    href={`/admin/customers/pppoe/${invoice.customer.id}/edit`}
                                    className="mt-4 inline-block text-sm font-semibold text-signal-deep hover:underline"
                                >
                                    Lihat pelanggan
                                </Link>
                            )}
                            {invoice.status === 'paid' && (
                                <button
                                    type="button"
                                    onClick={voidInvoice}
                                    className="mt-4 w-full border border-red-200 px-5 py-3 text-sm font-semibold text-red-600 hover:bg-red-50"
                                >
                                    Batalkan (void)
                                </button>
                            )}
                            {(invoice.status === 'void' || invoice.status === 'unpaid') && (
                                <button
                                    type="button"
                                    onClick={remove}
                                    className="mt-4 w-full border border-red-200 px-5 py-3 text-sm font-semibold text-red-600 hover:bg-red-50"
                                >
                                    Hapus tagihan
                                </button>
                            )}
                        </>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
