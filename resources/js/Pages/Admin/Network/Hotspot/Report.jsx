import { Head, Link, router } from '@inertiajs/react';
import { Banknote, Coins, Ticket, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import StatCard from '../../../../Components/Admin/StatCard';
import AdminLayout from '../../../../Layouts/AdminLayout';

export default function Report({
    filters,
    presets,
    agents = [],
    summary,
    by_agent: byAgent,
    recent,
}) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [agentId, setAgentId] = useState(filters.agent_id || '');

    useEffect(() => {
        setFrom(filters.from);
        setTo(filters.to);
        setAgentId(filters.agent_id || '');
    }, [filters.from, filters.to, filters.agent_id]);

    const applyPreset = (preset) => {
        router.get(
            '/admin/network/hotspot/reports',
            { preset, agent_id: agentId || undefined },
            { preserveState: true, replace: true },
        );
    };

    const applyCustom = (event) => {
        event.preventDefault();
        router.get(
            '/admin/network/hotspot/reports',
            {
                preset: 'custom',
                from,
                to,
                agent_id: agentId || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AdminLayout
            title="Laporan Voucher Hotspot"
            subtitle="Omzet & komisi hanya dari voucher yang sudah terjual dan terpakai"
        >
            <Head title="Laporan Voucher Hotspot" />

            <section className="mb-6 border border-ink/10 bg-white p-4 sm:p-5">
                <div className="flex flex-wrap gap-2">
                    {presets.map((preset) => (
                        <button
                            key={preset.value}
                            type="button"
                            onClick={() => applyPreset(preset.value)}
                            className={`btn-action btn-action-xs ${
                                filters.preset === preset.value ? 'btn-primary' : 'btn-secondary'
                            }`}
                        >
                            {preset.label}
                        </button>
                    ))}
                </div>

                <form
                    onSubmit={applyCustom}
                    className="mt-4 grid gap-3 sm:grid-cols-4 sm:items-end"
                >
                    <label className="block text-sm font-medium text-ink">
                        Dari
                        <input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            className="mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Sampai
                        <input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            className="mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Agen
                        <select
                            value={agentId || ''}
                            onChange={(e) => setAgentId(e.target.value)}
                            className="mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal"
                        >
                            <option value="">Semua agen</option>
                            {agents.map((agent) => (
                                <option key={agent.id} value={agent.id}>
                                    {agent.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button type="submit" className="btn-action btn-action-sm btn-secondary">
                        Terapkan
                    </button>
                </form>
            </section>

            <div className="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Jumlah voucher"
                    value={summary.voucher_count}
                    icon={Ticket}
                    hint={`${summary.available_count} tersedia · ${summary.used_count} terpakai (generate)`}
                    tone="teal"
                />
                <StatCard
                    label="Penjualan harga dasar"
                    value={summary.base_sales_label}
                    icon={Banknote}
                    hint={`${summary.sold_count ?? 0} voucher terjual/terpakai`}
                    tone="indigo"
                />
                <StatCard
                    label="Komisi agen"
                    value={summary.commission_total_label}
                    icon={Coins}
                    hint="Hanya dari voucher terpakai"
                    tone="amber"
                />
                <StatCard
                    label="Total harga kartu"
                    value={summary.gross_total_label}
                    icon={Users}
                    hint="Harga dasar + komisi (terpakai)"
                    tone="sky"
                />
            </div>

            <div className="mb-6 grid gap-4 lg:grid-cols-2">
                <section className="border border-ink/10 bg-white">
                    <div className="border-b border-ink/10 px-4 py-3">
                        <h2 className="text-sm font-semibold text-ink">Laporan harga dasar</h2>
                        <p className="text-xs text-ink-soft">
                            Hanya voucher yang sudah terjual & terpakai di periode ini
                        </p>
                    </div>
                    <div className="admin-data-scroll">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-4 py-2">Agen / sumber</th>
                                    <th className="px-4 py-2">Voucher</th>
                                    <th className="px-4 py-2 text-right">Harga dasar</th>
                                </tr>
                            </thead>
                            <tbody>
                                {byAgent.map((row) => (
                                    <tr key={`base-${row.agent_id || 'none'}`} className="border-t border-ink/5">
                                        <td className="px-4 py-2.5 text-ink">{row.agent_name}</td>
                                        <td className="px-4 py-2.5 text-ink-soft">{row.voucher_count}</td>
                                        <td className="px-4 py-2.5 text-right font-medium text-ink">
                                            {row.base_total_label}
                                        </td>
                                    </tr>
                                ))}
                                {byAgent.length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-4 py-8 text-center text-ink-soft">
                                            Belum ada data di periode ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            {byAgent.length > 0 && (
                                <tfoot>
                                    <tr className="border-t border-ink/10 bg-mist/40">
                                        <td className="px-4 py-2.5 font-semibold text-ink" colSpan={2}>
                                            Total harga dasar
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-semibold text-ink">
                                            {summary.base_sales_label}
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </section>

                <section className="border border-ink/10 bg-white">
                    <div className="border-b border-ink/10 px-4 py-3">
                        <h2 className="text-sm font-semibold text-ink">Laporan komisi agen</h2>
                        <p className="text-xs text-ink-soft">
                            Komisi agen dari voucher yang sudah terpakai
                        </p>
                    </div>
                    <div className="admin-data-scroll">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-4 py-2">Agen</th>
                                    <th className="px-4 py-2">Voucher</th>
                                    <th className="px-4 py-2 text-right">Komisi</th>
                                    <th className="hidden px-4 py-2 text-right sm:table-cell">
                                        Total kartu
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {byAgent.map((row) => (
                                    <tr
                                        key={`commission-${row.agent_id || 'none'}`}
                                        className="border-t border-ink/5"
                                    >
                                        <td className="px-4 py-2.5 text-ink">{row.agent_name}</td>
                                        <td className="px-4 py-2.5 text-ink-soft">{row.voucher_count}</td>
                                        <td className="px-4 py-2.5 text-right font-medium text-ink">
                                            {row.commission_total_label}
                                        </td>
                                        <td className="hidden px-4 py-2.5 text-right text-ink-soft sm:table-cell">
                                            {row.sell_total_label}
                                        </td>
                                    </tr>
                                ))}
                                {byAgent.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-4 py-8 text-center text-ink-soft">
                                            Belum ada data di periode ini.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            {byAgent.length > 0 && (
                                <tfoot>
                                    <tr className="border-t border-ink/10 bg-mist/40">
                                        <td className="px-4 py-2.5 font-semibold text-ink" colSpan={2}>
                                            Total komisi
                                        </td>
                                        <td className="px-4 py-2.5 text-right font-semibold text-ink">
                                            {summary.commission_total_label}
                                        </td>
                                        <td className="hidden px-4 py-2.5 text-right font-semibold text-ink sm:table-cell">
                                            {summary.gross_total_label}
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </section>
            </div>

            <section className="border border-ink/10 bg-white">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-ink/10 px-4 py-3">
                    <div>
                        <h2 className="text-sm font-semibold text-ink">Voucher terpakai terbaru</h2>
                        <p className="text-xs text-ink-soft">
                            Maksimal 40 penjualan/pemakaian pada periode terpilih
                        </p>
                    </div>
                    <Link
                        href="/admin/network/hotspot"
                        className="btn-action btn-action-xs btn-secondary"
                    >
                        Kelola voucher
                    </Link>
                </div>
                <div className="admin-data-scroll">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-2">Username</th>
                                <th className="px-4 py-2">Agen</th>
                                <th className="px-4 py-2 text-right">Dasar</th>
                                <th className="px-4 py-2 text-right">Komisi</th>
                                <th className="px-4 py-2 text-right">Kartu</th>
                                <th className="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recent.map((item) => (
                                <tr key={item.id} className="border-t border-ink/5">
                                    <td className="px-4 py-2.5">
                                        <p className="font-medium text-ink">{item.username}</p>
                                        <p className="text-xs text-ink-soft">
                                            {item.router_name || '—'}
                                        </p>
                                    </td>
                                    <td className="px-4 py-2.5 text-ink-soft">
                                        {item.agent_name || '—'}
                                    </td>
                                    <td className="px-4 py-2.5 text-right text-ink-soft">
                                        {item.base_price_label}
                                    </td>
                                    <td className="px-4 py-2.5 text-right text-ink-soft">
                                        {item.commission_label}
                                    </td>
                                    <td className="px-4 py-2.5 text-right font-medium text-ink">
                                        {item.sell_price_label}
                                    </td>
                                    <td className="px-4 py-2.5 text-ink-soft">{item.status_label}</td>
                                </tr>
                            ))}
                            {recent.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-ink-soft">
                                        Belum ada voucher terpakai pada periode ini.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AdminLayout>
    );
}
