import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

export default function PrintCards({ vouchers = [] }) {
    useEffect(() => {
        const timer = window.setTimeout(() => window.print(), 400);
        return () => window.clearTimeout(timer);
    }, []);

    return (
        <div className="min-h-screen bg-white text-ink">
            <Head title="Cetak Kartu Voucher" />

            <div className="mx-auto max-w-5xl p-4 print:p-0">
                <div className="mb-4 flex items-center justify-between gap-3 print:hidden">
                    <div>
                        <h1 className="text-lg font-semibold">Cetak Kartu Voucher</h1>
                        <p className="text-sm text-ink-soft">{vouchers.length} kartu</p>
                    </div>
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        Print ulang
                    </button>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 print:grid-cols-3">
                    {vouchers.map((item) => (
                        <article
                            key={item.id || item.username}
                            className="break-inside-avoid border border-ink/30 bg-white p-3"
                        >
                            <div className="flex items-start justify-between gap-2 border-b border-dashed border-ink/20 pb-2">
                                <div>
                                    <p className="text-[10px] tracking-[0.14em] text-ink-soft uppercase">
                                        Voucher Hotspot
                                    </p>
                                    <p className="mt-1 text-xs text-ink-soft">
                                        {item.profile || 'Hotspot'}
                                        {item.limit_uptime ? ` · ${item.limit_uptime}` : ''}
                                    </p>
                                </div>
                                <p className="text-base font-bold text-ink">
                                    {item.sell_price_label || 'Rp 0'}
                                </p>
                            </div>

                            <div className="mt-3 space-y-1.5 text-sm">
                                <p>
                                    <span className="text-ink-soft">User</span>
                                    <span className="ml-2 font-mono font-semibold tracking-wide">
                                        {item.username}
                                    </span>
                                </p>
                                <p>
                                    <span className="text-ink-soft">Pass</span>
                                    <span className="ml-2 font-mono font-semibold tracking-wide">
                                        {item.password}
                                    </span>
                                </p>
                            </div>

                            {item.agent_name && (
                                <p className="mt-3 text-[11px] text-ink-soft">
                                    Agen: <span className="text-ink">{item.agent_name}</span>
                                </p>
                            )}
                        </article>
                    ))}
                </div>
            </div>
        </div>
    );
}
