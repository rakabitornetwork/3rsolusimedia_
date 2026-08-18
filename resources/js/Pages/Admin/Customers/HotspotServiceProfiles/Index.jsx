import { Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { keepPage } from '../../../../lib/keepPage';

export default function Index({ packages }) {

    const remove = (id, name) => {
        if (!window.confirm(`Hapus Profile Layanan Hotspot "${name}"?`)) return;
        router.delete(`/admin/customers/hotspot/service-profiles/${id}`, keepPage);
    };

    return (
        <AdminLayout
            title="Profile Layanan Hotspot"
            subtitle="Kelola paket/profile yang dipakai user Hotspot"
        >
            <Head title="Profile Layanan Hotspot" />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Profile Layanan Hotspot menghubungkan paket (misalnya 120rb/150rb/250rb) ke Hotspot profile di
                    MikroTik.
                </p>
                <div className="admin-toolbar-actions">
                    <Link
                        href="/admin/customers/hotspot/service-profiles/create"
                        className="btn-action btn-action-sm btn-primary"
                    >
                        <Plus className="mr-1.5 h-4 w-4" />
                        Tambah Profile
                    </Link>
                </div>
            </div>

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Nama</th>
                            <th className="px-4 py-3 font-semibold">Harga</th>
                            <th className="px-4 py-3 font-semibold">Profile MikroTik</th>
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
                                            href={`/admin/customers/hotspot/service-profiles/${item.id}/edit`}
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
                                <td colSpan={6} className="px-4 py-10 text-center text-ink-soft">
                                    Belum ada Profile Layanan Hotspot.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
