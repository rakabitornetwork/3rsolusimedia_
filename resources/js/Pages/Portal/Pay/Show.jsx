import { Head, Link, router, usePage } from '@inertiajs/react';
import { CreditCard, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';

export default function Show({
    branding,
    token,
    status,
    customer,
    unpaid,
    recent_paid,
    gateway_ready,
}) {
    const { flash } = usePage().props;
    const [payingId, setPayingId] = useState(null);
    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        if (status !== 'success' || !unpaid?.length) return undefined;

        const timer = setInterval(() => {
            router.reload({ only: ['unpaid', 'recent_paid', 'status'] });
        }, 8000);

        return () => clearInterval(timer);
    }, [status, unpaid?.length]);

    const pay = (invoiceId) => {
        if (!gateway_ready || payingId) return;
        if (!window.confirm('Lanjut ke halaman pembayaran online?')) return;
        setPayingId(invoiceId);
        router.post(
            `/bayar/${token}/pay/${invoiceId}`,
            {},
            {
                onFinish: () => setPayingId(null),
            },
        );
    };

    const refresh = () => {
        if (refreshing) return;
        setRefreshing(true);
        router.reload({
            only: ['unpaid', 'recent_paid', 'status'],
            onFinish: () => setRefreshing(false),
        });
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-mist via-white to-mist text-ink">
            <Head title={`Tagihan · ${customer?.name || branding?.company_name || 'Portal'}`} />

            <div className="mx-auto max-w-2xl px-4 py-10">
                <div className="mb-8 flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        {branding?.logo_mark ? (
                            <img
                                src={branding.logo_mark}
                                alt=""
                                className="h-10 w-auto object-contain"
                            />
                        ) : (
                            <div className="flex h-10 w-10 items-center justify-center bg-signal/15 text-signal-deep">
                                <CreditCard className="h-5 w-5" />
                            </div>
                        )}
                        <div>
                            <p className="text-xs tracking-wide text-ink-soft uppercase">
                                {branding?.company_name}
                            </p>
                            <h1 className="text-lg font-semibold text-ink">{customer?.name}</h1>
                            <p className="text-xs text-ink-soft">{customer?.username}</p>
                        </div>
                    </div>
                    <Link href="/bayar" className="text-sm font-semibold text-signal-deep hover:underline">
                        Keluar
                    </Link>
                </div>

                {(flash?.error || flash?.success || status) && (
                    <div
                        className={`mb-4 border px-4 py-3 text-sm ${
                            flash?.error || status === 'failed'
                                ? 'border-red-200 bg-red-50 text-red-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        }`}
                    >
                        {flash?.error ||
                            flash?.success ||
                            (status === 'success'
                                ? 'Jika pembayaran berhasil, status tagihan akan diperbarui otomatis.'
                                : status === 'failed'
                                  ? 'Pembayaran belum berhasil. Silakan coba lagi.'
                                  : status === 'already_paid'
                                    ? 'Tagihan sudah lunas.'
                                    : null)}
                    </div>
                )}

                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-sm font-semibold text-ink">Tagihan belum bayar</h2>
                    <button
                        type="button"
                        onClick={refresh}
                        className="inline-flex items-center text-xs font-semibold text-ink-soft hover:text-ink"
                    >
                        <RefreshCw className={`mr-1 h-3.5 w-3.5 ${refreshing ? 'animate-spin' : ''}`} />
                        Muat ulang
                    </button>
                </div>

                {unpaid?.length ? (
                    <ul className="space-y-3">
                        {unpaid.map((invoice) => (
                            <li key={invoice.id} className="border border-ink/10 bg-white p-5">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-semibold text-ink">{invoice.number}</p>
                                        <p className="mt-1 text-xs text-ink-soft">
                                            {invoice.package_name || 'Paket'} · Jatuh tempo{' '}
                                            {invoice.due_date}
                                            {invoice.is_overdue ? ' · terlambat' : ''}
                                        </p>
                                    </div>
                                    <p className="text-lg font-semibold text-ink">{invoice.total_label}</p>
                                </div>
                                {gateway_ready ? (
                                    <button
                                        type="button"
                                        onClick={() => pay(invoice.id)}
                                        disabled={payingId === invoice.id}
                                        className="mt-4 w-full bg-signal px-4 py-2.5 text-sm font-semibold text-white hover:bg-signal-deep disabled:opacity-60"
                                    >
                                        {payingId === invoice.id
                                            ? 'Menyiapkan pembayaran...'
                                            : 'Bayar online'}
                                    </button>
                                ) : (
                                    <p className="mt-3 text-sm text-ink-soft">
                                        Pembayaran online belum aktif. Hubungi admin.
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <div className="border border-ink/10 bg-white p-6 text-sm text-ink-soft">
                        Tidak ada tagihan yang belum dibayar.
                    </div>
                )}

                {recent_paid?.length > 0 && (
                    <div className="mt-8">
                        <h2 className="mb-3 text-sm font-semibold text-ink">Pembayaran terakhir</h2>
                        <ul className="divide-y divide-ink/5 border border-ink/10 bg-white">
                            {recent_paid.map((invoice) => (
                                <li
                                    key={invoice.id}
                                    className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm"
                                >
                                    <div>
                                        <p className="font-medium text-ink">{invoice.number}</p>
                                        <p className="text-xs text-ink-soft">{invoice.status_label}</p>
                                    </div>
                                    <p className="font-semibold text-ink">{invoice.total_label}</p>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </div>
    );
}
