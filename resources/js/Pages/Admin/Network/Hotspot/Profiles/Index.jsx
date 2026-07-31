import { Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import AdminLayout from '../../../../../Layouts/AdminLayout';

export default function Index({ routers, selected_router_id, profiles, error }) {

    const changeRouter = (routerId) => {
        router.get(
            '/admin/network/hotspot/profiles',
            { router_id: routerId },
            { preserveState: true, replace: true },
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

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div className="flex flex-wrap items-end gap-3">
                    <label className="block text-sm font-medium text-ink">
                        Router
                        <select
                            value={selected_router_id || ''}
                            onChange={(e) => changeRouter(e.target.value)}
                            className="mt-1.5 block min-w-[220px] border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal"
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
                <Link
                    href={`/admin/network/hotspot/profiles/create${
                        selected_router_id ? `?router_id=${selected_router_id}` : ''
                    }`}
                    className="inline-flex items-center gap-2 bg-signal-deep px-4 py-2.5 text-sm font-semibold text-white hover:bg-ink"
                >
                    <Plus className="h-4 w-4" />
                    Tambah Profile
                </Link>
            </div>

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Nama</th>
                            <th className="px-4 py-3 font-semibold">Rate Limit</th>
                            <th className="px-4 py-3 font-semibold">Session / Idle</th>
                            <th className="px-4 py-3 font-semibold">Shared Users</th>
                            <th className="px-4 py-3 font-semibold">Address List</th>
                            <th className="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        {profiles.map((item) => (
                            <tr key={item.id || item.name} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3 font-medium text-ink">{item.name}</td>
                                <td className="px-4 py-3 text-ink-soft">{item.rate_limit || '—'}</td>
                                <td className="px-4 py-3 text-ink-soft">
                                    {(item.session_timeout || '—') + ' / ' + (item.idle_timeout || '—')}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{item.shared_users || '—'}</td>
                                <td className="px-4 py-3 text-ink-soft">{item.address_list || '—'}</td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        <Link
                                            href={`/admin/network/hotspot/profiles/${selected_router_id}/edit/${encodeURIComponent(item.id)}`}
                                            className="border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-signal-deep hover:bg-mist"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => remove(item)}
                                            className="inline-flex items-center gap-1 border border-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
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
                                <td colSpan={6} className="px-4 py-10 text-center text-ink-soft">
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
