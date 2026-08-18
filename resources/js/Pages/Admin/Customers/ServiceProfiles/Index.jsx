import { Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { keepPage } from '../../../../lib/keepPage';

export default function Index({ packages, routers = [], filters = {} }) {
    const routerId = filters.router_id || '';

    const changeRouter = (value) => {
        router.get(
            '/admin/customers/pppoe/service-profiles',
            { router_id: value || '' },
            { preserveState: true, replace: true },
        );
    };

    const remove = (id, name) => {
        if (!window.confirm(`Hapus paket layanan "${name}"?`)) return;
        const qs = routerId ? `?router_id=${encodeURIComponent(routerId)}` : '';
        router.delete(`/admin/customers/pppoe/service-profiles/${id}${qs}`, keepPage);
    };

    return (
        <AdminLayout
            title="Paket Layanan"
            subtitle="Kelola paket yang dipakai pelanggan PPPoE"
        >
            <Head title="Paket Layanan" />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-end">
                    <label className="block w-full text-sm font-medium text-ink sm:w-auto">
                        RouterOS
                        <select
                            value={routerId}
                            onChange={(e) => changeRouter(e.target.value)}
                            className="mt-1.5 block w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal sm:min-w-[220px]"
                        >
                            <option value="">Semua router</option>
                            {routers.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name} ({item.host})
                                </option>
                            ))}
                        </select>
                    </label>
                    <p className="max-w-md pb-2 text-sm text-ink-soft">
                        Paket layanan menghubungkan harga ke Profile PPPoE di MikroTik per RouterOS.
                    </p>
                </div>
                <div className="admin-toolbar-actions">
                    <Link
                        href={`/admin/customers/pppoe/service-profiles/create${
                            routerId ? `?router_id=${routerId}` : ''
                        }`}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        <Plus className="mr-1.5 h-4 w-4" />
                        Tambah Paket
                    </Link>
                </div>
            </div>

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Nama</th>
                            {!routerId && (
                                <th className="px-4 py-3 font-semibold">RouterOS</th>
                            )}
                            <th className="px-4 py-3 font-semibold">Harga</th>
                            <th className="px-4 py-3 font-semibold">Profile PPPoE</th>
                            <th className="px-4 py-3 font-semibold">Pelanggan</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {packages.map((item) => (
                            <tr key={item.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{item.name}</p>
                                    {item.description && (
                                        <p className="text-xs text-ink-soft">{item.description}</p>
                                    )}
                                </td>
                                {!routerId && (
                                    <td className="px-4 py-3 text-ink-soft">
                                        {item.router_name || '—'}
                                    </td>
                                )}
                                <td className="px-4 py-3 text-ink-soft">{item.price_label}</td>
                                <td className="px-4 py-3 text-ink-soft">
                                    {item.mikrotik_profile || '—'}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{item.customers_count}</td>
                                <td className="px-4 py-3">
                                    <span
                                        className={`px-2 py-1 text-xs font-semibold ${
                                            item.is_active
                                                ? 'bg-signal/15 text-signal-deep'
                                                : 'bg-ink/10 text-ink-soft'
                                        }`}
                                    >
                                        {item.is_active ? 'Aktif' : 'Nonaktif'}
                                    </span>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="admin-actions">
                                        <Link
                                            href={`/admin/customers/pppoe/service-profiles/${item.id}/edit${
                                                routerId ? `?router_id=${routerId}` : ''
                                            }`}
                                            className="btn-action btn-action-xs btn-edit"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => remove(item.id, item.name)}
                                            className="btn-action btn-action-xs btn-danger"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {packages.length === 0 && (
                            <tr>
                                <td
                                    colSpan={routerId ? 6 : 7}
                                    className="px-4 py-10 text-center text-ink-soft"
                                >
                                    {routerId
                                        ? 'Belum ada paket layanan untuk router ini.'
                                        : 'Belum ada paket layanan.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
