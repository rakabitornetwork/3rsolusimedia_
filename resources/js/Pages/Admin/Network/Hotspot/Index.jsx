import { Head, Link, router } from '@inertiajs/react';
import { Plus, Power, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';

function formatBytes(value) {
    if (value == null) return '—';
    const n = Number(value);
    if (!n) return '0 B';
    if (n >= 1024 ** 3) return `${(n / 1024 ** 3).toFixed(1)} GB`;
    if (n >= 1024 ** 2) return `${(n / 1024 ** 2).toFixed(1)} MB`;
    if (n >= 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${n} B`;
}

export default function Index({
    routers,
    selected_router_id,
    users,
    profiles,
    error,
    filters,
    stats,
    generated_vouchers,
}) {
    const [showGenerated, setShowGenerated] = useState(
        Boolean(generated_vouchers?.length),
    );

    const changeFilter = (key, value) => {
        router.get(
            '/admin/network/hotspot',
            { ...filters, router_id: selected_router_id, [key]: value },
            { preserveState: true, replace: true },
        );
    };

    const changeRouter = (routerId) => {
        router.get(
            '/admin/network/hotspot',
            { router_id: routerId },
            { preserveState: true, replace: true },
        );
    };

    const remove = (user) => {
        if (!selected_router_id) return;
        if (!window.confirm(`Hapus voucher "${user.name}" dari MikroTik?`)) return;
        router.delete(
            `/admin/network/hotspot/${selected_router_id}/${encodeURIComponent(user.id)}`,
        );
    };

    const toggle = (user) => {
        if (!selected_router_id) return;
        router.post(
            `/admin/network/hotspot/${selected_router_id}/${encodeURIComponent(user.id)}/toggle`,
            { disabled: !user.disabled },
        );
    };

    const copyGenerated = async () => {
        const text = (generated_vouchers || [])
            .map((item) => `${item.name}\t${item.password}`)
            .join('\n');
        try {
            await navigator.clipboard.writeText(text);
            window.alert('Daftar voucher disalin ke clipboard.');
        } catch {
            window.alert('Gagal menyalin. Salin manual dari tabel.');
        }
    };

    return (
        <AdminLayout
            title="Voucher Hotspot"
            subtitle="Generate dan kelola user hotspot (voucher) di RouterOS"
        >
            <Head title="Voucher Hotspot" />
            {error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}

            {showGenerated && generated_vouchers?.length > 0 && (
                <div className="mb-5 border border-signal/20 bg-white p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p className="text-sm font-semibold text-ink">
                                Voucher baru ({generated_vouchers.length})
                            </p>
                            <p className="text-xs text-ink-soft">
                                Simpan username/password sekarang. Setelah refresh, password bisa
                                tidak ditampilkan ulang.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={copyGenerated}
                                className="border border-ink/15 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-mist"
                            >
                                Salin semua
                            </button>
                            <button
                                type="button"
                                onClick={() => setShowGenerated(false)}
                                className="border border-ink/15 px-3 py-1.5 text-xs font-semibold text-ink-soft hover:bg-mist"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>
                    <div className="admin-data-scroll max-h-56 overflow-y-auto border border-ink/10">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-3 py-2">Username</th>
                                    <th className="px-3 py-2">Password</th>
                                </tr>
                            </thead>
                            <tbody>
                                {generated_vouchers.map((item) => (
                                    <tr key={item.name} className="border-t border-ink/5">
                                        <td className="px-3 py-2 font-medium text-ink">
                                            {item.name}
                                        </td>
                                        <td className="px-3 py-2 text-ink-soft">{item.password}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <div className="mb-5 grid gap-3 sm:grid-cols-4">
                {[
                    ['Total voucher', stats.total],
                    ['Online', stats.online],
                    ['Nonaktif', stats.disabled],
                    ['Ditampilkan', stats.shown],
                ].map(([label, value]) => (
                    <div key={label} className="border border-ink/10 bg-white p-4">
                        <p className="text-[11px] tracking-wide text-ink-soft uppercase">{label}</p>
                        <p className="mt-2 text-xl font-semibold text-ink">{value}</p>
                    </div>
                ))}
            </div>

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
                    <input
                        type="search"
                        defaultValue={filters.q}
                        placeholder="Cari username / comment"
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') changeFilter('q', e.currentTarget.value);
                        }}
                        className="w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal sm:w-auto"
                    />
                    <select
                        value={filters.profile || ''}
                        onChange={(e) => changeFilter('profile', e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal sm:w-auto"
                    >
                        <option value="">Semua profile</option>
                        {profiles.map((profile) => (
                            <option key={profile.name} value={profile.name}>
                                {profile.name}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.status || ''}
                        onChange={(e) => changeFilter('status', e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal sm:w-auto"
                    >
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="online">Online</option>
                        <option value="disabled">Nonaktif</option>
                    </select>
                </div>

                <div className="admin-toolbar-actions">
                    <Link
                        href={`/admin/network/hotspot/generate${
                            selected_router_id ? `?router_id=${selected_router_id}` : ''
                        }`}
                        className="bg-signal-deep px-4 text-sm font-semibold text-white hover:bg-ink"
                    >
                        <Plus className="mr-1.5 h-4 w-4" />
                        Generate Voucher
                    </Link>
                </div>
            </div>

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-4 py-3 font-semibold">Username</th>
                            <th className="px-4 py-3 font-semibold">Profile</th>
                            <th className="hidden px-4 py-3 font-semibold md:table-cell">Limit</th>
                            <th className="hidden px-4 py-3 font-semibold lg:table-cell">Usage</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((user) => (
                            <tr key={user.id || user.name} className="border-b border-ink/5 last:border-0">
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{user.name}</p>
                                    {user.comment && (
                                        <p className="text-xs text-ink-soft">{user.comment}</p>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{user.profile || '—'}</td>
                                <td className="hidden px-4 py-3 text-ink-soft md:table-cell">
                                    <p>{user.limit_uptime || '—'}</p>
                                    <p className="text-xs">{formatBytes(user.limit_bytes_total)}</p>
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft lg:table-cell">
                                    <p>{user.uptime || '—'}</p>
                                    <p className="text-xs">
                                        ↓ {formatBytes(user.bytes_in)} / ↑{' '}
                                        {formatBytes(user.bytes_out)}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    {user.disabled ? (
                                        <span className="bg-ink/10 px-2 py-1 text-xs font-semibold text-ink-soft">
                                            Nonaktif
                                        </span>
                                    ) : user.is_online ? (
                                        <span className="bg-signal/15 px-2 py-1 text-xs font-semibold text-signal-deep">
                                            Online
                                        </span>
                                    ) : (
                                        <span className="bg-mist px-2 py-1 text-xs font-semibold text-ink-soft">
                                            Siap
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="admin-actions">
                                        <button
                                            type="button"
                                            onClick={() => toggle(user)}
                                            className="inline-flex items-center gap-1 border border-ink/10 px-2.5 py-1.5 text-xs font-semibold text-ink-soft hover:bg-mist"
                                        >
                                            <Power className="h-3.5 w-3.5" />
                                            {user.disabled ? 'Aktifkan' : 'Nonaktif'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => remove(user)}
                                            className="inline-flex items-center gap-1 border border-red-100 px-2.5 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {users.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-10 text-center text-ink-soft">
                                    {selected_router_id
                                        ? 'Belum ada voucher, atau filter tidak cocok.'
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
