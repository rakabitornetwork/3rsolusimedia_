import { Head, Link, router } from '@inertiajs/react';
import { Cable, Eye, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import AdminLayout from '../../../../Layouts/AdminLayout';

function StatusBadge({ status }) {
    if (status === 'online') {
        return (
            <span className="bg-signal/15 px-2 py-1 text-xs font-semibold text-signal-deep">
                Online
            </span>
        );
    }

    if (status === 'offline') {
        return <span className="bg-red-50 px-2 py-1 text-xs font-semibold text-red-600">Offline</span>;
    }

    return <span className="bg-ink/5 px-2 py-1 text-xs font-semibold text-ink-soft">Belum dicek</span>;
}

export default function Index({ routers }) {

    const remove = (id, name) => {
        if (!window.confirm(`Hapus router "${name}"?`)) return;
        router.delete(`/admin/network/routeros/${id}`);
    };

    const test = (id) => {
        router.post(`/admin/network/routeros/${id}/test`);
    };

    return (
        <AdminLayout
            title="Router MikroTik"
            subtitle="Hubungkan router lewat API port 8728"
        >
            <Head title="Router MikroTik" />

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Tambahkan data router, lalu uji koneksi API. Pastikan di MikroTik menu IP → Services
                    → api sudah aktif di port 8728.
                </p>
                <div className="admin-toolbar-actions">
                    <Link
                        href="/admin/network/routeros/create"
                        className="bg-signal-deep px-4 text-sm font-semibold text-white hover:bg-ink"
                    >
                        <Plus className="mr-1.5 h-4 w-4" />
                        Tambah Router
                    </Link>
                </div>
            </div>

            {routers.length === 0 ? (
                <div className="border border-dashed border-ink/15 bg-white px-6 py-14 text-center">
                    <Cable className="mx-auto h-8 w-8 text-ink/25" />
                    <p className="mt-3 font-medium text-ink">Belum ada router</p>
                    <p className="mt-1 text-sm text-ink-soft">
                        Tambah router pertama untuk mulai koneksi API.
                    </p>
                    <Link
                        href="/admin/network/routeros/create"
                        className="mt-5 inline-flex bg-signal-deep px-4 py-2.5 text-sm font-semibold text-white"
                    >
                        Tambah Router
                    </Link>
                </div>
            ) : (
                <div className="admin-data-scroll border border-ink/10 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                            <tr>
                                <th className="px-4 py-3 font-semibold">Router</th>
                                <th className="px-4 py-3 font-semibold">Host / Port</th>
                                <th className="hidden px-4 py-3 font-semibold md:table-cell">User</th>
                                <th className="px-4 py-3 font-semibold">Status</th>
                                <th className="px-4 py-3 font-semibold" />
                            </tr>
                        </thead>
                        <tbody>
                            {routers.map((item) => (
                                <tr key={item.id} className="border-b border-ink/5 last:border-0">
                                    <td className="px-4 py-4">
                                        <p className="font-medium text-ink">{item.name}</p>
                                        {item.notes && (
                                            <p className="mt-0.5 text-xs text-ink-soft">{item.notes}</p>
                                        )}
                                    </td>
                                    <td className="px-4 py-4 text-ink-soft">
                                        {item.host}:{item.port}
                                        {item.use_ssl ? ' (SSL)' : ''}
                                    </td>
                                    <td className="hidden px-4 py-4 text-ink-soft md:table-cell">
                                        {item.username}
                                    </td>
                                    <td className="px-4 py-4">
                                        <StatusBadge status={item.last_status} />
                                        {item.last_message && (
                                            <p className="mt-1 max-w-[220px] truncate text-xs text-ink/45">
                                                {item.last_message}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-4 py-4">
                                        <div className="admin-actions">
                                            <button
                                                type="button"
                                                onClick={() => test(item.id)}
                                                className="inline-flex items-center gap-1 border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-ink-soft hover:bg-mist"
                                            >
                                                <RefreshCw className="h-3.5 w-3.5" />
                                                Tes
                                            </button>
                                            <Link
                                                href={`/admin/network/routeros/${item.id}`}
                                                className="inline-flex items-center gap-1 border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-signal-deep hover:bg-mist"
                                            >
                                                <Eye className="h-3.5 w-3.5" />
                                                Buka
                                            </Link>
                                            <Link
                                                href={`/admin/network/routeros/${item.id}/edit`}
                                                className="inline-flex items-center gap-1 border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-ink-soft hover:bg-mist"
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                                Edit
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => remove(item.id, item.name)}
                                                className="inline-flex items-center gap-1 border border-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AdminLayout>
    );
}
