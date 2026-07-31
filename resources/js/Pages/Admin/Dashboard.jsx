import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Cable,
    Coins,
    CreditCard,
    Hourglass,
    Plus,
    Router,
    ShieldCheck,
    Ticket,
    Users,
    WalletCards,
    Wifi,
} from 'lucide-react';
import AdminLayout from '../../Layouts/AdminLayout';

function StatWidget({ label, value, hint, href, gradient, icon: Icon }) {
    const content = (
        <div
            className={`flex h-full min-h-[132px] flex-col p-4 text-white shadow-sm transition hover:brightness-105 ${gradient}`}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] tracking-wide text-white/75 uppercase">{label}</p>
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center bg-white/15">
                    <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                </span>
            </div>
            <p className="font-display mt-3 text-2xl font-bold text-white">{value}</p>
            <p className="mt-auto pt-2 text-xs text-white/70">{hint || '\u00a0'}</p>
        </div>
    );

    if (!href) return content;

    return (
        <Link href={href} className="block h-full">
            {content}
        </Link>
    );
}

function actionIcon(label) {
    if (label.includes('Pelanggan')) return Users;
    if (label.includes('Tagihan') || label.includes('Bayar')) return CreditCard;
    if (label.includes('Voucher')) return Ticket;
    if (label.includes('Router')) return Router;
    if (label.includes('Paket')) return Wifi;
    if (label.includes('Profile')) return Cable;
    return Plus;
}

export default function Dashboard({
    company,
    stats,
    due_soon: dueSoon,
    attention_invoices: attentionInvoices,
    quick_actions: quickActions,
}) {
    const { auth } = usePage().props;
    const userName = auth?.user?.name || 'Admin';

    const alerts = [
        stats.customers_isolated > 0 && {
            label: `${stats.customers_isolated} pelanggan isolir`,
            href: '/admin/customers/pppoe?status=isolated',
            tone: 'danger',
        },
        stats.customers_overdue > 0 && {
            label: `${stats.customers_overdue} pelanggan lewat jatuh tempo`,
            href: '/admin/customers/pppoe',
            tone: 'warn',
        },
        stats.invoices_overdue > 0 && {
            label: `${stats.invoices_overdue} tagihan jatuh tempo`,
            href: '/admin/billing?overdue=1',
            tone: 'warn',
        },
        stats.sync_errors > 0 && {
            label: `${stats.sync_errors} gagal sync MikroTik`,
            href: '/admin/customers/pppoe',
            tone: 'danger',
        },
    ].filter(Boolean);

    return (
        <AdminLayout
            title="Dashboard"
            subtitle={`${company} · ringkasan operasional hari ini`}
        >
            <Head title="Dashboard" />

            <div className="mb-6">
                <p className="text-sm text-ink-soft">Selamat datang, {userName}</p>
            </div>

            {alerts.length > 0 && (
                <div className="mb-6 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    {alerts.map((alert) => (
                        <Link
                            key={alert.label}
                            href={alert.href}
                            className={`inline-flex items-center gap-2 border px-3 py-2.5 text-sm font-medium ${
                                alert.tone === 'danger'
                                    ? 'border-red-200 bg-red-50 text-red-700'
                                    : 'border-amber-200 bg-amber-50 text-amber-800'
                            }`}
                        >
                            <AlertTriangle className="h-4 w-4 shrink-0" />
                            {alert.label}
                        </Link>
                    ))}
                </div>
            )}

            <section className="mb-8">
                <h3 className="mb-3 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                    Pelanggan PPPoE
                </h3>
                <div className="grid items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatWidget
                        label="Total pelanggan"
                        value={stats.customers_total}
                        hint={`${stats.customers_disabled} nonaktif`}
                        href="/admin/customers/pppoe"
                        gradient="bg-gradient-to-br from-cyan-400 to-sky-600"
                        icon={Coins}
                    />
                    <StatWidget
                        label="Aktif"
                        value={stats.customers_active}
                        hint="Koneksi berjalan normal"
                        href="/admin/customers/pppoe?status=active"
                        gradient="bg-gradient-to-br from-emerald-400 to-teal-600"
                        icon={ShieldCheck}
                    />
                    <StatWidget
                        label="Isolir"
                        value={stats.customers_isolated}
                        hint="Perlu pembayaran / restore"
                        href="/admin/customers/pppoe?status=isolated"
                        gradient="bg-gradient-to-br from-rose-400 to-pink-600"
                        icon={WalletCards}
                    />
                    <StatWidget
                        label="Lewat tempo"
                        value={stats.customers_overdue}
                        hint="Melewati jatuh tempo"
                        href="/admin/customers/pppoe"
                        gradient="bg-gradient-to-br from-amber-300 to-orange-500"
                        icon={Hourglass}
                    />
                </div>
            </section>

            <section className="mb-8">
                <h3 className="mb-3 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                    Aksi cepat
                </h3>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {quickActions.map((action) => {
                        const Icon = actionIcon(action.label);
                        return (
                            <Link
                                key={action.href}
                                href={action.href}
                                className={`group flex items-start gap-3 border p-4 transition hover:border-signal/40 ${
                                    action.tone === 'primary'
                                        ? 'border-signal/30 bg-signal/10'
                                        : 'border-ink/10 bg-white hover:bg-mist/40'
                                }`}
                            >
                                <span
                                    className={`mt-0.5 inline-flex h-9 w-9 items-center justify-center ${
                                        action.tone === 'primary'
                                            ? 'bg-signal-deep text-white'
                                            : 'bg-mist text-signal-deep'
                                    }`}
                                >
                                    <Icon className="h-4 w-4" />
                                </span>
                                <span>
                                    <span className="block font-medium text-ink">{action.label}</span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        {action.description}
                                    </span>
                                </span>
                            </Link>
                        );
                    })}
                </div>
            </section>

            <div className="grid gap-5 xl:grid-cols-2">
                <section className="border border-ink/10 bg-white">
                    <div className="flex items-center justify-between border-b border-ink/10 px-4 py-3">
                        <div>
                            <h3 className="text-sm font-semibold text-ink">Jatuh tempo 7 hari</h3>
                            <p className="text-xs text-ink-soft">Pelanggan yang perlu ditagih segera</p>
                        </div>
                        <Link
                            href="/admin/billing"
                            className="text-xs font-semibold text-signal-deep hover:underline"
                        >
                            Lihat tagihan
                        </Link>
                    </div>
                    <ul className="divide-y divide-ink/5">
                        {dueSoon.length === 0 && (
                            <li className="px-4 py-8 text-center text-sm text-ink-soft">
                                Tidak ada jatuh tempo dalam 7 hari.
                            </li>
                        )}
                        {dueSoon.map((item) => (
                            <li key={item.id} className="flex items-center justify-between gap-3 px-4 py-3">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-ink">{item.name}</p>
                                    <p className="truncate text-xs text-ink-soft">
                                        {item.username}
                                        {item.package ? ` · ${item.package}` : ''}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm font-semibold text-ink">{item.due_date}</p>
                                    <Link
                                        href={`/admin/customers/pppoe/${item.id}/edit`}
                                        className="text-xs font-semibold text-signal-deep hover:underline"
                                    >
                                        Detail
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="border border-ink/10 bg-white">
                    <div className="flex items-center justify-between border-b border-ink/10 px-4 py-3">
                        <div>
                            <h3 className="text-sm font-semibold text-ink">Tagihan perlu perhatian</h3>
                            <p className="text-xs text-ink-soft">Belum bayar hingga 7 hari ke depan / overdue</p>
                        </div>
                        <Link
                            href="/admin/billing?status=unpaid"
                            className="text-xs font-semibold text-signal-deep hover:underline"
                        >
                            Semua unpaid
                        </Link>
                    </div>
                    <ul className="divide-y divide-ink/5">
                        {attentionInvoices.length === 0 && (
                            <li className="px-4 py-8 text-center text-sm text-ink-soft">
                                Tidak ada tagihan mendesak.
                            </li>
                        )}
                        {attentionInvoices.map((item) => (
                            <li key={item.id} className="flex items-center justify-between gap-3 px-4 py-3">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-ink">{item.number}</p>
                                    <p className="truncate text-xs text-ink-soft">
                                        {item.customer?.name || '—'} · {item.total_label}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p
                                        className={`text-sm font-semibold ${
                                            item.is_overdue ? 'text-amber-700' : 'text-ink'
                                        }`}
                                    >
                                        {item.due_date}
                                    </p>
                                    <Link
                                        href={`/admin/billing/invoices/${item.id}`}
                                        className="text-xs font-semibold text-signal-deep hover:underline"
                                    >
                                        Bayar
                                    </Link>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </AdminLayout>
    );
}
