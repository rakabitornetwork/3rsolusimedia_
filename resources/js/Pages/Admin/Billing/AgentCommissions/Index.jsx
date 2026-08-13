import { Head, router } from '@inertiajs/react';
import { Banknote, Coins, Receipt } from 'lucide-react';
import { useEffect, useState } from 'react';
import StatCard from '../../../../Components/Admin/StatCard';
import AdminLayout from '../../../../Layouts/AdminLayout';

function formatPaidAt(value) {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return value;
    }
}

export default function Index({
    filters,
    presets,
    agents = [],
    can_filter_agent: canFilterAgent = true,
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
            '/admin/billing/agent-commissions',
            {
                preset,
                agent_id: canFilterAgent ? agentId || undefined : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const applyCustom = (event) => {
        event.preventDefault();
        router.get(
            '/admin/billing/agent-commissions',
            {
                preset: 'custom',
                from,
                to,
                agent_id: canFilterAgent ? agentId || undefined : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AdminLayout
            title="Komisi Agen"
            subtitle="Komisi tetap per tagihan lunas pelanggan yang ditugaskan (setelah fitur aktif)"
        >
            <Head title="Komisi Agen" />

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
                    className={`mt-4 grid gap-3 sm:items-end ${
                        canFilterAgent ? 'sm:grid-cols-4' : 'sm:grid-cols-3'
                    }`}
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
                    {canFilterAgent && (
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
                    )}
                    <button type="submit" className="btn-action btn-action-sm btn-secondary">
                        Terapkan
                    </button>
                </form>
            </section>

            <div className="mb-6 grid gap-3 sm:grid-cols-3">
                <StatCard
                    label="Transaksi berkomisi"
                    value={summary.payment_count}
                    icon={Receipt}
                    hint="Tagihan lunas dengan komisi > 0"
                    tone="teal"
                />
                <StatCard
                    label="Omzet pelanggan agen"
                    value={summary.collected_total_label}
                    icon={Banknote}
                    hint="Total pembayaran di periode"
                    tone="indigo"
                />
                <StatCard
                    label="Total komisi"
                    value={summary.commission_total_label}
                    icon={Coins}
                    hint="Jumlah komisi tetap ter-snapshot"
                    tone="amber"
                />
            </div>

            <section className="mb-6 border border-ink/10 bg-white">
                <div className="border-b border-ink/10 px-4 py-3">
                    <h2 className="text-sm font-semibold text-ink">Ringkasan per agen</h2>
                    <p className="text-xs text-ink-soft">
                        Dihitung dari pembayaran tagihan lunas pada periode terpilih
                    </p>
                </div>
                <div className="admin-data-scroll">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-2">Agen</th>
                                <th className="px-4 py-2">Transaksi</th>
                                <th className="px-4 py-2 text-right">Omzet</th>
                                <th className="px-4 py-2 text-right">Komisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {byAgent.map((row) => (
                                <tr key={row.agent_id} className="border-t border-ink/5">
                                    <td className="px-4 py-2.5 font-medium text-ink">
                                        {row.agent_name}
                                    </td>
                                    <td className="px-4 py-2.5 text-ink-soft">{row.payment_count}</td>
                                    <td className="px-4 py-2.5 text-right text-ink">
                                        {row.collected_total_label}
                                    </td>
                                    <td className="px-4 py-2.5 text-right font-semibold text-ink">
                                        {row.commission_total_label}
                                    </td>
                                </tr>
                            ))}
                            {byAgent.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-4 py-8 text-center text-ink-soft">
                                        Belum ada komisi di periode ini.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                        {byAgent.length > 0 && (
                            <tfoot>
                                <tr className="border-t border-ink/10 bg-mist/30">
                                    <td className="px-4 py-2.5 text-sm font-semibold text-ink">
                                        Total
                                    </td>
                                    <td className="px-4 py-2.5 text-sm font-semibold text-ink">
                                        {summary.payment_count}
                                    </td>
                                    <td className="px-4 py-2.5 text-right text-sm font-semibold text-ink">
                                        {summary.collected_total_label}
                                    </td>
                                    <td className="px-4 py-2.5 text-right text-sm font-semibold text-ink">
                                        {summary.commission_total_label}
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </section>

            <section className="border border-ink/10 bg-white">
                <div className="border-b border-ink/10 px-4 py-3">
                    <h2 className="text-sm font-semibold text-ink">Pembayaran terbaru</h2>
                    <p className="text-xs text-ink-soft">Maks. 40 transaksi berkomisi di periode ini</p>
                </div>
                <div className="admin-data-scroll">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-2">Tanggal</th>
                                <th className="px-4 py-2">Invoice</th>
                                <th className="px-4 py-2">Pelanggan</th>
                                <th className="px-4 py-2">Agen</th>
                                <th className="px-4 py-2 text-right">Bayar</th>
                                <th className="px-4 py-2 text-right">Komisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {recent.map((row) => (
                                <tr key={row.id} className="border-t border-ink/5">
                                    <td className="px-4 py-2.5 whitespace-nowrap text-ink-soft">
                                        {formatPaidAt(row.paid_at)}
                                    </td>
                                    <td className="px-4 py-2.5 font-mono text-xs text-ink">
                                        {row.invoice_number || `#${row.invoice_id}`}
                                    </td>
                                    <td className="px-4 py-2.5">
                                        <p className="font-medium text-ink">
                                            {row.customer_name || '—'}
                                        </p>
                                        {row.customer_username && (
                                            <p className="font-mono text-[11px] text-ink-soft">
                                                {row.customer_username}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-2.5 text-ink">{row.agent_name || '—'}</td>
                                    <td className="px-4 py-2.5 text-right text-ink">
                                        {row.amount_label}
                                    </td>
                                    <td className="px-4 py-2.5 text-right font-semibold text-ink">
                                        {row.agent_commission_label}
                                    </td>
                                </tr>
                            ))}
                            {recent.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-ink-soft">
                                        Belum ada pembayaran berkomisi di periode ini.
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
