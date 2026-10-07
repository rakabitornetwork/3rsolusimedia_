import { Head, Link, usePage } from '@inertiajs/react';
import { CreditCard, Router, Terminal, Wifi } from 'lucide-react';

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
    const isVpn = customer?.ppp_service === 'l2tp';

    const nav = isVpn
        ? [
              { key: 'home', label: 'Skrip VPN', href: `/portal/${token}`, icon: Terminal },
              { key: 'billing', label: 'Tagihan', href: `/portal/${token}/tagihan`, icon: CreditCard },
          ]
        : [
              { key: 'home', label: 'Beranda', href: `/portal/${token}`, icon: Wifi },
              { key: 'billing', label: 'Tagihan', href: `/portal/${token}/tagihan`, icon: CreditCard },
              { key: 'device', label: 'Perangkat', href: `/portal/${token}/perangkat`, icon: Router },
          ];

    return (
        <div className="min-h-screen bg-paper text-ink">
            <div className="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,rgba(26,110,255,0.16),transparent_52%),linear-gradient(180deg,#e8eef2_0%,#f5f8fa_42%,#e8eef2_100%)]" />
            <Head title={`${title} · ${company}`} />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:py-10">
                <div className="mb-5 flex items-center justify-between gap-4 rounded-2xl border border-white/80 bg-white/80 px-3.5 py-3 shadow-[0_16px_40px_-28px_rgba(16,24,32,0.55)] backdrop-blur-md">
                    <div className="flex min-w-0 items-center gap-3">
                        {branding?.logo_mark ? (
                            <img
                                src={branding.logo_mark}
                                alt=""
                                className="h-10 w-auto object-contain"
                            />
                        ) : (
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-signal/15 text-signal-deep">
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
                                {isVpn ? customer?.ppp_service_label || 'VPN L2TP' : customer?.username}
                                {customer?.phone ? ` · ${customer.phone}` : ''}
                            </p>
                        </div>
                    </div>
                    <Link
                        href="/portal"
                        className="shrink-0 text-sm font-semibold text-signal-deep hover:underline"
                    >
                        Keluar
                    </Link>
                </div>

                <nav className="mb-5 flex gap-1 rounded-2xl border border-ink/10 bg-white/90 p-1 shadow-sm backdrop-blur">
                    {nav.map((item) => {
                        const Icon = item.icon;
                        const isActive = active === item.key;

                        return (
                            <Link
                                key={item.key}
                                href={item.href}
                                className={`flex flex-1 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold ${
                                    isActive
                                        ? 'bg-signal text-white shadow-[0_8px_18px_-12px_rgba(26,110,255,0.95)]'
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
                        className={`mb-4 rounded-2xl border px-4 py-3 text-sm ${
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
