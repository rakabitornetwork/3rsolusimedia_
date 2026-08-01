import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Plus,
    RefreshCw,
    ShieldOff,
    Trash2,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';

function StatCard({ label, value, icon: Icon, tone }) {
    return (
        <div className={`flex min-h-[110px] flex-col p-4 text-white shadow-sm ${tone}`}>
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] tracking-wide text-white/75 uppercase">{label}</p>
                <span className="inline-flex h-9 w-9 items-center justify-center bg-white/15">
                    <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                </span>
            </div>
            <p className="font-display mt-3 text-2xl font-bold">{value}</p>
        </div>
    );
}

function StatusBadge({ status, overdue }) {
    if (overdue && status !== 'isolated') {
        return (
            <span className="bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                Jatuh tempo
            </span>
        );
    }

    const map = {
        active: 'bg-signal/15 text-signal-deep',
        isolated: 'bg-red-50 text-red-600',
        disabled: 'bg-ink/10 text-ink-soft',
    };

    const label = {
        active: 'Aktif',
        isolated: 'Isolir',
        disabled: 'Nonaktif',
    };

    return (
        <span className={`px-2 py-1 text-xs font-semibold ${map[status] || map.disabled}`}>
            {label[status] || status}
        </span>
    );
}

export default function Index({ customers, filters, routers, stats }) {
    const { auth } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const [selected, setSelected] = useState([]);
    const [showBulkDelete, setShowBulkDelete] = useState(false);
    const [removeSecret, setRemoveSecret] = useState(false);
    const [processing, setProcessing] = useState(false);

    const pageIds = useMemo(
        () => (customers?.data || []).map((item) => item.id),
        [customers],
    );
    const allPageSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));

    const applyFilters = (key, value) => {
        setSelected([]);
        setShowBulkDelete(false);
        router.get(
            '/admin/customers/pppoe',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );
    };

    const toggleOne = (id) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id],
        );
    };

    const togglePage = () => {
        if (allPageSelected) {
            setSelected((prev) => prev.filter((id) => !pageIds.includes(id)));
            return;
        }
        setSelected((prev) => [...new Set([...prev, ...pageIds])]);
    };

    const remove = (id) => {
        if (!canWrite) return;
        setSelected([id]);
        setRemoveSecret(false);
        setShowBulkDelete(true);
    };

    const sync = (id) => {
        router.post(`/admin/customers/pppoe/${id}/sync`);
    };

    const submitBulkDelete = () => {
        if (!canWrite || selected.length === 0) return;

        const scope = removeSecret
            ? 'data di aplikasi DAN secret di RouterOS'
            : 'data di aplikasi saja (secret RouterOS tetap ada)';

        if (
            !window.confirm(
                `Hapus ${selected.length} pelanggan (${scope})?\n\nTagihan terkait juga ikut terhapus. Tindakan ini tidak bisa dibatalkan.`,
            )
        ) {
            return;
        }

        setProcessing(true);
        router.post(
            '/admin/customers/pppoe/bulk-destroy',
            {
                ids: selected,
                remove_secret: removeSecret,
            },
            {
                onFinish: () => {
                    setProcessing(false);
                    setSelected([]);
                    setShowBulkDelete(false);
                    setRemoveSecret(false);
                },
            },
        );
    };

    return (
        <AdminLayout
            title="Pelanggan PPPoE"
            subtitle="Kelola pelanggan, jatuh tempo, dan aksi isolir/bypass"
        >
            <Head title="Pelanggan PPPoE" />

            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total"
                    value={stats.total}
                    icon={Users}
                    tone="bg-gradient-to-br from-slate-400 to-slate-600"
                />
                <StatCard
                    label="Aktif"
                    value={stats.active}
                    icon={Activity}
                    tone="bg-gradient-to-br from-teal-400 to-cyan-600"
                />
                <StatCard
                    label="Isolir"
                    value={stats.isolated}
                    icon={ShieldOff}
                    tone="bg-gradient-to-br from-rose-400 to-pink-600"
                />
                <StatCard
                    label="Lewat tempo"
                    value={stats.overdue}
                    icon={AlertTriangle}
                    tone="bg-gradient-to-br from-amber-300 to-orange-500"
                />
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                    <input
                        type="search"
                        defaultValue={filters.q}
                        placeholder="Cari nama / username / telepon"
                        className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:min-w-[220px] sm:w-auto"
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') applyFilters('q', e.currentTarget.value);
                        }}
                    />
                    <select
                        value={filters.router_id || ''}
                        onChange={(e) => applyFilters('router_id', e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                    >
                        <option value="">Semua router</option>
                        {routers.map((routerItem) => (
                            <option key={routerItem.id} value={routerItem.id}>
                                {routerItem.name}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.status || ''}
                        onChange={(e) => applyFilters('status', e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                    >
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="isolated">Isolir</option>
                        <option value="disabled">Nonaktif</option>
                    </select>
                </div>

                <div className="admin-toolbar-actions">
                    {canWrite && selected.length > 0 && (
                        <button
                            type="button"
                            onClick={() => setShowBulkDelete((v) => !v)}
                            className="border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                        >
                            <Trash2 className="mr-1.5 h-3.5 w-3.5" />
                            Hapus masal ({selected.length})
                        </button>
                    )}
                    <Link
                        href="/admin/customers/pppoe/create"
                        className="bg-signal-deep px-4 text-sm font-semibold text-white hover:bg-ink"
                    >
                        <Plus className="mr-1.5 h-4 w-4" />
                        Tambah Pelanggan
                    </Link>
                </div>
            </div>

            {showBulkDelete && selected.length > 0 && canWrite && (
                <div className="mb-4 space-y-3 border border-rose-200 bg-white p-4 sm:p-5">
                    <div>
                        <h2 className="text-sm font-semibold text-ink">
                            Hapus {selected.length} pelanggan terpilih
                        </h2>
                        <p className="mt-0.5 text-xs text-ink-soft">
                            Pilih cakupan penghapusan. Tagihan terkait pelanggan ikut terhapus.
                        </p>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <label
                            className={`flex cursor-pointer items-start gap-3 border px-3 py-3 ${
                                !removeSecret
                                    ? 'border-sky-300 bg-sky-50'
                                    : 'border-ink/10 hover:bg-mist'
                            }`}
                        >
                            <input
                                type="radio"
                                name="bulk_delete_mode"
                                checked={!removeSecret}
                                onChange={() => setRemoveSecret(false)}
                                className="mt-0.5 accent-sky-600"
                            />
                            <span>
                                <span className="block text-sm font-semibold text-ink">
                                    Hanya di aplikasi
                                </span>
                                <span className="mt-0.5 block text-xs text-ink-soft">
                                    Data pelanggan & tagihan di app dihapus. Secret PPPoE di
                                    RouterOS tetap ada.
                                </span>
                            </span>
                        </label>

                        <label
                            className={`flex cursor-pointer items-start gap-3 border px-3 py-3 ${
                                removeSecret
                                    ? 'border-rose-300 bg-rose-50'
                                    : 'border-ink/10 hover:bg-mist'
                            }`}
                        >
                            <input
                                type="radio"
                                name="bulk_delete_mode"
                                checked={removeSecret}
                                onChange={() => setRemoveSecret(true)}
                                className="mt-0.5 accent-rose-600"
                            />
                            <span>
                                <span className="block text-sm font-semibold text-ink">
                                    Aplikasi + secret RouterOS
                                </span>
                                <span className="mt-0.5 block text-xs text-ink-soft">
                                    Hapus data app dan secret `/ppp/secret` di router. Pelanggan
                                    tidak bisa login lagi.
                                </span>
                            </span>
                        </label>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={submitBulkDelete}
                            disabled={processing}
                            className="bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-60"
                        >
                            {processing ? 'Menghapus...' : 'Konfirmasi hapus'}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setShowBulkDelete(false);
                                setRemoveSecret(false);
                            }}
                            className="border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-mist"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            )}

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-3 py-3 font-semibold">
                                {canWrite && (
                                    <input
                                        type="checkbox"
                                        checked={allPageSelected}
                                        onChange={togglePage}
                                        className="accent-signal-deep"
                                        title="Pilih semua di halaman ini"
                                    />
                                )}
                            </th>
                            <th className="px-4 py-3 font-semibold">Pelanggan</th>
                            <th className="px-4 py-3 font-semibold">Username</th>
                            <th className="hidden px-4 py-3 font-semibold lg:table-cell">Paket</th>
                            <th className="px-4 py-3 font-semibold">Jatuh Tempo</th>
                            <th className="hidden px-4 py-3 font-semibold xl:table-cell">
                                Tagihan Awal
                            </th>
                            <th className="px-4 py-3 font-semibold">Aksi Tempo</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {customers.data.map((customer) => (
                            <tr key={customer.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-3 py-3">
                                    {canWrite && (
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(customer.id)}
                                            onChange={() => toggleOne(customer.id)}
                                            className="accent-signal-deep"
                                        />
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{customer.name}</p>
                                    <p className="text-xs text-ink-soft">
                                        {customer.router?.name || '—'}
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{customer.username}</td>
                                <td className="hidden px-4 py-3 text-ink-soft lg:table-cell">
                                    {customer.package?.name || '—'}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">
                                    <p>{customer.due_date}</p>
                                    {customer.billing_day && (
                                        <p className="text-xs text-ink-soft/80">
                                            tiap tgl {customer.billing_day}
                                        </p>
                                    )}
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft xl:table-cell">
                                    {customer.first_bill_amount_label || '—'}
                                    {customer.first_bill_days != null && (
                                        <p className="text-xs">{customer.first_bill_days} hari</p>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-ink-soft capitalize">
                                    {customer.overdue_action}
                                    {customer.overdue_action === 'isolir' && customer.isolir_profile
                                        ? ` → ${customer.isolir_profile}`
                                        : ''}
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge
                                        status={customer.status}
                                        overdue={customer.is_overdue}
                                    />
                                </td>
                                <td className="px-4 py-3">
                                    <div className="admin-actions">
                                        <button
                                            type="button"
                                            onClick={() => sync(customer.id)}
                                            className="inline-flex items-center gap-1 border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-ink-soft hover:bg-mist"
                                        >
                                            <RefreshCw className="h-3.5 w-3.5" />
                                            Sync
                                        </button>
                                        <Link
                                            href={`/admin/customers/pppoe/${customer.id}/edit`}
                                            className="border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-signal-deep hover:bg-mist"
                                        >
                                            Edit
                                        </Link>
                                        {canWrite && (
                                            <button
                                                type="button"
                                                onClick={() => remove(customer.id)}
                                                className="inline-flex items-center gap-1 border border-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                                Hapus
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {customers.data.length === 0 && (
                            <tr>
                                <td colSpan={9} className="px-4 py-10 text-center text-ink-soft">
                                    Belum ada pelanggan PPPoE.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {customers.last_page > 1 && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {customers.links.map((link, index) => (
                        <button
                            key={`${link.label}-${index}`}
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
