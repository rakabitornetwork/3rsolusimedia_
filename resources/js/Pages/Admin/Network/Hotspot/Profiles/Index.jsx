import { Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import AdminLayout from '../../../../../Layouts/AdminLayout';

export default function Index({ routers, selected_router_id, profiles, error }) {

    const changeRouter = (routerId) => {
        router.get(
            '/admin/network/hotspot/profiles',
            { router_id: routerId },
            { preserveState: false, replace: true },
        );
    };

    const remove = (profile) => {
        if (!selected_router_id) return;
        if (!window.confirm(`Hapus profile hotspot "${profile.name}" dari MikroTik?`)) return;
        router.delete(
            `/admin/network/hotspot/profiles/${selected_router_id}/${encodeURIComponent(profile.id)}`,
        );
    };

    return (
        <AdminLayout
            title="Profile Hotspot"
            subtitle="Kelola Hotspot User Profile di RouterOS"
        >
            <Head title="Profile Hotspot" />
            {error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-end">
                    <label className="block w-full text-sm font-medium text-ink sm:w-auto">
                        Router
                        <select
                            value={selected_router_id || ''}
                            onChange={(e) => changeRouter(e.target.value)}
                            className="mt-1.5 block w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal sm:min-w-[220px]"
                        >
                            {routers.length === 0 && <option value="">Belum ada router</option>}
                            {routers.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name} ({item.host})
                                </option>
                            ))}
                        </select>
                    </label>
                    <p className="max-w-md pb-2 text-sm text-ink-soft">
                        Profile ini dipakai saat generate voucher (bandwidth, session timeout, dll).
                    </p>
                </div>
                <div className="admin-toolbar-actions">
                    <Link
                        href={`/admin/network/hotspot/profiles/create${
                            selected_router_id ? `?router_id=${selected_router_id}` : ''
                        }`}
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
                            <th className="px-4 py-3 font-semibold">Rate Limit</th>
                            <th className="hidden px-4 py-3 font-semibold lg:table-cell">
                                Session / Idle
                            </th>
                            <th className="px-4 py-3 font-semibold">Expired</th>
                            <th className="hidden px-4 py-3 font-semibold md:table-cell">Lock</th>
                            <th className="hidden px-4 py-3 font-semibold xl:table-cell">
                                Parent Queue
                            </th>
                            <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {profiles.map((item) => (
                            <tr key={item.id || item.name} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{item.name}</p>
                                    <p className="text-xs text-ink-soft md:hidden">
                                        Lock: {item.lock_user ? 'Ya' : 'Tidak'}
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{item.rate_limit || '—'}</td>
                                <td className="hidden px-4 py-3 text-ink-soft lg:table-cell">
                                    {(item.session_timeout || '—') + ' / ' + (item.idle_timeout || '—')}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{item.expired_mode || '—'}</td>
                                <td className="hidden px-4 py-3 text-ink-soft md:table-cell">
                                    {item.lock_user ? 'Ya' : 'Tidak'}
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft xl:table-cell">
                                    {item.parent_queue || '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="admin-actions">
                                        <Link
                                            href={`/admin/network/hotspot/profiles/${selected_router_id}/edit/${encodeURIComponent(item.id)}`}
                                            className="btn-action btn-action-xs btn-edit"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => remove(item)}
                                            className="btn-action btn-action-xs btn-danger"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {profiles.length === 0 && (
                            <tr>
                                <td colSpan={7} className="px-4 py-10 text-center text-ink-soft">
                                    {selected_router_id
                                        ? 'Belum ada hotspot user profile di router ini.'
                                        : 'Pilih atau tambahkan router terlebih dahulu.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
