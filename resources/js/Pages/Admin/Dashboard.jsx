import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Cable,
    Coins,
    CreditCard,
    GitCommitHorizontal,
    Hourglass,
    Plus,
    RefreshCw,
    Router,
    ShieldCheck,
    Ticket,
    Users,
    WalletCards,
    Wifi,
} from 'lucide-react';
import LiveTrafficCard from '../../Components/Admin/LiveTrafficCard';
import RevenueChartCard from '../../Components/Admin/RevenueChartCard';
import StatCard from '../../Components/Admin/StatCard';
import AdminLayout from '../../Layouts/AdminLayout';

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
    traffic_routers: trafficRouters = [],
    update_notice: updateNotice = null,
    revenue_charts: revenueCharts = null,
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

            {updateNotice?.has_update && (
                <Link
                    href={updateNotice.href}
                    className="mb-6 block border border-teal-200 bg-gradient-to-br from-teal-400 to-cyan-600 p-4 text-white shadow-sm transition hover:brightness-105"
                >
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="inline-flex items-center gap-1.5 bg-white/20 px-2.5 py-1 text-[11px] font-bold tracking-wide uppercase">
                                    <RefreshCw className="h-3.5 w-3.5" />
                                    Update tersedia
                                </span>
                                <span className="text-xs text-white/80">
                                    {updateNotice.behind} commit baru
                                    {updateNotice.remote_version
                                        ? ` · v${updateNotice.remote_version}`
                                        : ''}
                                    {updateNotice.remote_commit_short
                                        ? ` · ${updateNotice.remote_commit_short}`
                                        : ''}
                                </span>
                            </div>
                            <p className="mt-2 text-sm font-semibold text-white">
                                {updateNotice.sync_label}
                            </p>

                            {updateNotice.incoming_commits?.length > 0 && (
                                <ul className="mt-3 space-y-1.5 border-t border-white/20 pt-3">
                                    {updateNotice.incoming_commits.map((commit) => (
                                        <li
                                            key={commit.hash}
                                            className="flex items-start gap-2 text-sm text-white/90"
                                        >
                                            <GitCommitHorizontal className="mt-0.5 h-3.5 w-3.5 shrink-0 text-white/70" />
                                            <span className="min-w-0">
                                                <span className="font-mono text-xs text-white/70">
                                                    {commit.hash}
                                                </span>{' '}
                                                <span className="font-medium">{commit.subject}</span>
                                                <span className="mt-0.5 block text-xs text-white/65">
                                                    {commit.author}
                                                    {commit.date ? ` · ${commit.date}` : ''}
                                                </span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                        <span className="inline-flex items-center gap-1.5 bg-white px-3 py-2 text-xs font-semibold text-teal-800">
                            Buka halaman Update
                            <ArrowRight className="h-3.5 w-3.5" />
                        </span>
                    </div>
                </Link>
            )}

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
                    <StatCard
                        label="Total pelanggan"
                        value={stats.customers_total}
                        hint={`${stats.customers_disabled} nonaktif`}
                        href="/admin/customers/pppoe"
                        tone="cyan"
                        icon={Coins}
                    />
                    <StatCard
                        label="Aktif"
                        value={stats.customers_active}
                        hint="Koneksi berjalan normal"
                        href="/admin/customers/pppoe?status=active"
                        tone="emerald"
                        icon={ShieldCheck}
                    />
                    <StatCard
                        label="Isolir"
                        value={stats.customers_isolated}
                        hint="Perlu pembayaran / restore"
                        href="/admin/customers/pppoe?status=isolated"
                        tone="rose"
                        icon={WalletCards}
                    />
                    <StatCard
                        label="Lewat tempo"
                        value={stats.customers_overdue}
                        hint="Melewati jatuh tempo"
                        href="/admin/customers/pppoe"
                        tone="amber"
                        icon={Hourglass}
                    />
                </div>
            </section>

            <section className="mb-8">
                <h3 className="mb-3 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                    Live Traffic
                </h3>
                <LiveTrafficCard routers={trafficRouters} />
            </section>

            {revenueCharts && (
                <section className="mb-8">
                    <h3 className="mb-3 text-xs font-semibold tracking-wide text-ink-soft uppercase">
                        Pendapatan
                    </h3>
                    <RevenueChartCard charts={revenueCharts} />
                </section>
            )}

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
