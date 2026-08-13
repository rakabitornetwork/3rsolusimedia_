import { Head, Link, usePage } from '@inertiajs/react';
import { CreditCard, Router, Wifi } from 'lucide-react';

export default function PortalLayout({
    branding,
    customer,
    token,
    title,
    active = 'home',
    children,
}) {
    const { flash } = usePage().props;
    const company = branding?.company_name || 'Portal Pelanggan';

    const nav = [
        { key: 'home', label: 'Beranda', href: `/bayar/${token}`, icon: Wifi },
        { key: 'billing', label: 'Tagihan', href: `/bayar/${token}/tagihan`, icon: CreditCard },
        { key: 'device', label: 'Perangkat', href: `/bayar/${token}/perangkat`, icon: Router },
    ];

    return (
        <div className="min-h-screen bg-gradient-to-b from-mist via-white to-mist text-ink">
            <Head title={`${title} · ${company}`} />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:py-10">
                <div className="mb-6 flex items-center justify-between gap-4">
                    <div className="flex min-w-0 items-center gap-3">
                        {branding?.logo_mark ? (
                            <img
                                src={branding.logo_mark}
                                alt=""
                                className="h-10 w-auto object-contain"
                            />
                        ) : (
                            <div className="flex h-10 w-10 items-center justify-center bg-signal/15 text-signal-deep">
                                <Wifi className="h-5 w-5" />
                            </div>
                        )}
                        <div className="min-w-0">
                            <p className="text-xs tracking-wide text-ink-soft uppercase">
                                {company}
                            </p>
                            <h1 className="truncate text-lg font-semibold text-ink">
                                {customer?.name || 'Pelanggan'}
                            </h1>
                            <p className="truncate text-xs text-ink-soft">
                                {customer?.username}
                                {customer?.phone ? ` · ${customer.phone}` : ''}
                            </p>
                        </div>
                    </div>
                    <Link
                        href="/bayar"
                        className="shrink-0 text-sm font-semibold text-signal-deep hover:underline"
                    >
                        Keluar
                    </Link>
                </div>

                <nav className="mb-5 flex gap-1 border border-ink/10 bg-white p-1">
                    {nav.map((item) => {
                        const Icon = item.icon;
                        const isActive = active === item.key;

                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={`flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5 text-sm font-semibold ${
                                    isActive
                                        ? 'bg-signal text-white'
                                        : 'text-ink-soft hover:bg-mist hover:text-ink'
                                }`}
                            >
                                <Icon className="h-4 w-4" />
                                <span className="hidden sm:inline">{item.label}</span>
                                <span className="sm:hidden">{item.label}</span>
                            </Link>
                        );
                    })}
                </nav>

                {(flash?.error || flash?.success) && (
                    <div
                        className={`mb-4 border px-4 py-3 text-sm ${
                            flash.error
                                ? 'border-red-200 bg-red-50 text-red-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        }`}
                    >
                        {flash.error || flash.success}
                    </div>
                )}

                {children}
            </div>
        </div>
    );
}
