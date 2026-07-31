import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
    UserCog,
    Users,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import UserAvatar from '../../../Components/UserAvatar';
import AdminLayout from '../../../Layouts/AdminLayout';

const roleBadge = {
    superadmin: 'bg-indigo-100 text-indigo-800',
    admin: 'bg-signal/15 text-signal-deep',
    teknisi: 'bg-amber-100 text-amber-900',
};

function StatWidget({ label, value, gradient, icon: Icon }) {
    return (
        <div className={`flex h-full min-h-[120px] flex-col p-4 text-white shadow-sm ${gradient}`}>
            <div className="flex items-start justify-between gap-3">
                <p className="text-[11px] tracking-wide text-white/75 uppercase">{label}</p>
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center bg-white/15">
                    <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} />
                </span>
            </div>
            <p className="font-display mt-3 text-2xl font-bold text-white">{value}</p>
        </div>
    );
}

export default function Index({ users, filters, role_options, stats, can_manage }) {
    const { auth } = usePage().props;
    const [q, setQ] = useState(filters?.q || '');
    const canWrite = auth?.user?.can_write !== false;
    const showManage = can_manage && canWrite;

    const applyFilters = (next = {}) => {
        router.get(
            '/admin/users',
            {
                q: next.q !== undefined ? next.q : q,
                role: next.role !== undefined ? next.role : filters?.role || '',
            },
            { preserveState: true, replace: true },
        );
    };

    const remove = (user) => {
        if (!window.confirm(`Hapus pengguna "${user.name}"?`)) return;
        router.delete(`/admin/users/${user.id}`);
    };

    return (
        <AdminLayout
            title="Manajemen Pengguna"
            subtitle="Kelola akun dan role akses panel admin"
        >
            <Head title="Manajemen Pengguna" />

            <div className="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatWidget
                    label="Total User"
                    value={stats.total}
                    gradient="bg-gradient-to-br from-slate-500 to-slate-900"
                    icon={Users}
                />
                <StatWidget
                    label="Superadmin"
                    value={stats.superadmin}
                    gradient="bg-gradient-to-br from-indigo-500 to-indigo-900"
                    icon={ShieldCheck}
                />
                <StatWidget
                    label="Admin"
                    value={stats.admin}
                    gradient="bg-gradient-to-br from-emerald-500 to-teal-800"
                    icon={UserCog}
                />
                <StatWidget
                    label="Teknisi"
                    value={stats.teknisi}
                    gradient="bg-gradient-to-br from-amber-400 to-orange-700"
                    icon={Wrench}
                />
            </div>

            <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters({ q });
                    }}
                    className="flex flex-wrap items-end gap-2"
                >
                    <label className="block text-sm text-ink">
                        <span className="mb-1 block text-xs font-semibold text-ink-soft">Cari</span>
                        <span className="relative block">
                            <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft" />
                            <input
                                type="search"
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                                placeholder="Nama atau email"
                                className="w-56 border border-ink/15 py-2.5 pr-3 pl-9 text-sm outline-none focus:border-signal"
                            />
                        </span>
                    </label>
                    <label className="block text-sm text-ink">
                        <span className="mb-1 block text-xs font-semibold text-ink-soft">Role</span>
                        <select
                            value={filters?.role || ''}
                            onChange={(e) => applyFilters({ role: e.target.value })}
                            className="border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal"
                        >
                            <option value="">Semua role</option>
                            {role_options.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button
                        type="submit"
                        className="border border-ink/15 bg-white px-4 py-2.5 text-sm font-semibold text-ink hover:bg-mist"
                    >
                        Filter
                    </button>
                </form>

                {showManage && (
                    <Link
                        href="/admin/users/create"
                        className="inline-flex items-center gap-2 bg-signal-deep px-4 py-2.5 text-sm font-semibold text-white hover:bg-ink"
                    >
                        <Plus className="h-4 w-4" />
                        Tambah User
                    </Link>
                )}
            </div>

            <div className="overflow-hidden border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Pengguna</th>
                            <th className="px-4 py-3 font-semibold">Role</th>
                            <th className="px-4 py-3 font-semibold">Dibuat</th>
                            <th className="px-4 py-3 font-semibold" />
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user) => (
                            <tr key={user.id} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <UserAvatar
                                            name={user.name}
                                            role={user.role}
                                            src={user.avatar_url}
                                            initials={user.initials}
                                            size="md"
                                        />
                                        <div>
                                            <p className="font-medium text-ink">{user.name}</p>
                                            <p className="text-xs text-ink-soft">{user.email}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <span
                                        className={`px-2 py-1 text-xs font-semibold ${
                                            roleBadge[user.role] || 'bg-ink/10 text-ink-soft'
                                        }`}
                                    >
                                        {user.role_label}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-ink-soft">
                                    {user.created_at
                                        ? new Date(user.created_at).toLocaleDateString('id-ID')
                                        : '—'}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {showManage && user.can_edit && (
                                            <Link
                                                href={`/admin/users/${user.id}/edit`}
                                                className="inline-flex items-center gap-1 border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-signal-deep hover:bg-mist"
                                            >
                                                <Pencil className="h-3.5 w-3.5" />
                                                Edit
                                            </Link>
                                        )}
                                        {showManage && user.can_delete && (
                                            <button
                                                type="button"
                                                onClick={() => remove(user)}
                                                className="inline-flex items-center gap-1 border border-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                                Hapus
                                            </button>
                                        )}
                                        {(!showManage || (!user.can_edit && !user.can_delete)) && (
                                            <span className="text-xs text-ink-soft">Lihat saja</span>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {users.data.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-10 text-center text-ink-soft">
                                    Tidak ada pengguna yang cocok.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {users.links?.length > 3 && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {users.links.map((link, index) => (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url || '#'}
                            preserveState
                            className={`px-3 py-1.5 text-xs font-semibold ${
                                link.active
                                    ? 'bg-signal-deep text-white'
                                    : link.url
                                      ? 'border border-ink/10 bg-white text-ink hover:bg-mist'
                                      : 'cursor-not-allowed border border-ink/5 text-ink-soft/50'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
