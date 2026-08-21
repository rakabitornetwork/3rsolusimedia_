import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    Ban,
    ListFilter,
    Plus,
    Power,
    Printer,
    RotateCcw,
    Ticket,
    Trash2,
    UserPlus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import LocalPagination from '../../../../Components/Admin/LocalPagination';
import StatCard from '../../../../Components/Admin/StatCard';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { keepPage } from '../../../../lib/keepPage';
import { matchesSearch, paginateItems } from '../../../../lib/search';

const PER_PAGE_OPTIONS = [25, 50, 100, 200, 500];

function formatBytes(value) {
    if (value == null) return '—';
    const n = Number(value);
    if (!n) return '0 B';
    if (n >= 1024 ** 3) return `${(n / 1024 ** 3).toFixed(1)} GB`;
    if (n >= 1024 ** 2) return `${(n / 1024 ** 2).toFixed(1)} MB`;
    if (n >= 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${n} B`;
}

function trimComment(value) {
    return String(value || '').trim();
}

function buildPrintUrl(ids) {
    const params = new URLSearchParams();
    ids.forEach((id) => params.append('ids[]', String(id)));
    return `/admin/network/hotspot/print?${params.toString()}`;
}

export default function Index({
    routers,
    selected_router_id,
    users,
    profiles,
    comments = [],
    error,
    purged_message,
    filters,
    stats,
    generated_vouchers,
    generated_batch_id,
}) {
    const [showGenerated, setShowGenerated] = useState(
        Boolean(generated_vouchers?.length),
    );
    const [selectedIds, setSelectedIds] = useState([]);
    const [query, setQuery] = useState(filters.q || '');
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(filters.per_page || 25);

    const allUsers = Array.isArray(users) ? users : users?.data ?? [];
    const filteredUsers = useMemo(
        () =>
            allUsers.filter((user) =>
                matchesSearch(query, user.name, user.comment, user.agent_name, user.profile),
            ),
        [allUsers, query],
    );
    const paged = useMemo(
        () => paginateItems(filteredUsers, page, perPage),
        [filteredUsers, page, perPage],
    );
    const rows = paged.data;

    const printableUsers = useMemo(
        () => rows.filter((user) => Boolean(user.voucher_id)),
        [rows],
    );

    const allSelected =
        printableUsers.length > 0 &&
        printableUsers.every((user) => selectedIds.includes(user.voucher_id));

    const toggleSelect = (voucherId) => {
        setSelectedIds((current) =>
            current.includes(voucherId)
                ? current.filter((id) => id !== voucherId)
                : [...current, voucherId],
        );
    };

    const toggleSelectAll = () => {
        if (allSelected) {
            setSelectedIds([]);
            return;
        }
        setSelectedIds(printableUsers.map((user) => user.voucher_id));
    };

    const printSelected = () => {
        if (selectedIds.length === 0) {
            window.alert('Pilih minimal satu voucher untuk dicetak.');
            return;
        }
        window.open(buildPrintUrl(selectedIds), '_blank', 'noopener,noreferrer');
    };

    const changeFilter = (key, value) => {
        setSelectedIds([]);
        setPage(1);
        router.get(
            '/admin/network/hotspot',
            { ...filters, router_id: selected_router_id, page: 1, [key]: value },
            { preserveState: true, replace: true },
        );
    };

    const changeRouter = (routerId) => {
        setSelectedIds([]);
        setPage(1);
        setQuery('');
        router.get(
            '/admin/network/hotspot',
            { router_id: routerId, per_page: perPage },
            { preserveState: true, replace: true },
        );
    };

    const remove = (user) => {
        if (!selected_router_id) return;
        if (!window.confirm(`Hapus voucher "${user.name}" dari MikroTik?`)) return;
        router.delete(
            `/admin/network/hotspot/${selected_router_id}/${encodeURIComponent(user.id)}`,
            keepPage,
        );
    };

    const toggle = (user) => {
        if (!selected_router_id) return;
        router.post(
            `/admin/network/hotspot/${selected_router_id}/${encodeURIComponent(user.id)}/toggle`,
            { disabled: !user.disabled },
            keepPage,
        );
    };

    const resetExpired = (user) => {
        if (!selected_router_id) return;
        if (
            !window.confirm(
                `Reset user expired "${user.name}"? Comment dan limit-uptime akan dikosongkan.`,
            )
        ) {
            return;
        }
        router.post(
            `/admin/network/hotspot/${selected_router_id}/${encodeURIComponent(user.id)}/reset`,
            {},
            keepPage,
        );
    };

    const deleteByComment = () => {
        if (!selected_router_id) return;
        const comment = (filters.comment || '').trim();
        if (!comment) {
            window.alert('Pilih komentar terlebih dahulu untuk menghapus voucher secara massal.');
            return;
        }

        const count = stats?.shown ?? rows.filter(
            (user) => trimComment(user.comment) === comment,
        ).length;

        if (
            !window.confirm(
                `Hapus ${count} voucher dengan komentar "${comment}" dari RouterOS dan aplikasi?`,
            )
        ) {
            return;
        }

        setSelectedIds([]);
        router.post('/admin/network/hotspot/delete-by-comment', {
            router_id: selected_router_id,
            comment,
        }, keepPage);
    };

    const copyGenerated = async () => {
        const text = (generated_vouchers || [])
            .map((item) => {
                const price = item.sell_price_label || '';
                const agent = item.agent_name ? `\t${item.agent_name}` : '';
                return `${item.username}\t${item.password}${agent}\t${price}`;
            })
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
            {purged_message && (
                <div className="mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {purged_message}
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
                                Kartu menampilkan harga jual (dasar + komisi agen). Cetak atau salin
                                sekarang.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {generated_batch_id && (
                                <a
                                    href={`/admin/network/hotspot/print?batch_id=${encodeURIComponent(generated_batch_id)}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="btn-action btn-action-xs btn-print"
                                >
                                    <Printer className="mr-1 h-3.5 w-3.5" />
                                    Cetak
                                </a>
                            )}
                            <button
                                type="button"
                                onClick={copyGenerated}
                                className="btn-action btn-action-xs btn-secondary"
                            >
                                Salin semua
                            </button>
                            <button
                                type="button"
                                onClick={() => setShowGenerated(false)}
                                className="btn-action btn-action-xs btn-secondary"
                            >
                                Tutup
                            </button>
                        </div>
                    </div>

                    <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 print:hidden">
                        {generated_vouchers.map((item) => (
                            <div
                                key={item.id || item.username}
                                className="border border-ink/15 bg-gradient-to-br from-white to-mist/60 p-4"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="text-[11px] tracking-wide text-ink-soft uppercase">
                                            Hotspot Voucher
                                        </p>
                                        <p className="mt-1 font-mono text-lg font-semibold text-ink">
                                            {item.username}
                                        </p>
                                    </div>
                                    <p className="text-sm font-bold text-signal-deep">
                                        {item.sell_price_label || 'Rp 0'}
                                    </p>
                                </div>
                                <p className="mt-2 text-xs text-ink-soft">
                                    Password:{' '}
                                    <span className="font-mono text-ink">{item.password}</span>
                                </p>
                                {item.agent_name && (
                                    <p className="mt-1 text-xs text-ink-soft">
                                        Agen: <span className="text-ink">{item.agent_name}</span>
                                    </p>
                                )}
                                {item.limit_uptime && (
                                    <p className="mt-1 text-xs text-ink-soft">
                                        Durasi: {item.limit_uptime}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>

                    <div className="admin-data-scroll max-h-56 overflow-y-auto border border-ink/10">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                                <tr>
                                    <th className="px-3 py-2">Username</th>
                                    <th className="px-3 py-2">Password</th>
                                    <th className="px-3 py-2">Agen</th>
                                    <th className="px-3 py-2">Harga kartu</th>
                                </tr>
                            </thead>
                            <tbody>
                                {generated_vouchers.map((item) => (
                                    <tr key={item.username} className="border-t border-ink/5">
                                        <td className="px-3 py-2 font-medium text-ink">
                                            {item.username}
                                        </td>
                                        <td className="px-3 py-2 text-ink-soft">{item.password}</td>
                                        <td className="px-3 py-2 text-ink-soft">
                                            {item.agent_name || '—'}
                                        </td>
                                        <td className="px-3 py-2 font-medium text-ink">
                                            {item.sell_price_label || 'Rp 0'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            <div className="mb-5 grid items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Total voucher"
                    value={stats.total}
                    hint="Di RouterOS"
                    tone="slate"
                    icon={Ticket}
                />
                <StatCard
                    label="Online"
                    value={stats.online}
                    hint="Sesi aktif"
                    tone="emerald"
                    icon={Activity}
                />
                <StatCard
                    label="Nonaktif"
                    value={stats.disabled}
                    hint="Disabled di router"
                    tone="rose"
                    icon={Ban}
                />
                <StatCard
                    label="Ditampilkan"
                    value={filteredUsers.length}
                    hint="Sesuai filter"
                    tone="cyan"
                    icon={ListFilter}
                />
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
                        value={query}
                        placeholder="Cari username / agen"
                        onChange={(e) => {
                            setQuery(e.currentTarget.value);
                            setPage(1);
                            setSelectedIds([]);
                        }}
                        className="w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal sm:w-auto"
                    />
                    <label className="block w-full text-sm font-medium text-ink sm:w-auto">
                        Baris / halaman
                        <select
                            value={perPage}
                            onChange={(e) => {
                                setPerPage(Number(e.target.value));
                                setPage(1);
                            }}
                            className="mt-1.5 block w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal sm:w-28"
                        >
                            {PER_PAGE_OPTIONS.map((n) => (
                                <option key={n} value={n}>
                                    {n}
                                </option>
                            ))}
                        </select>
                    </label>
                    <select
                        value={filters.comment || ''}
                        onChange={(e) => changeFilter('comment', e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal sm:min-w-[200px] sm:w-auto"
                        title="Filter berdasarkan komentar untuk cetak ulang"
                    >
                        <option value="">Semua komentar</option>
                        {comments.map((comment) => (
                            <option key={comment} value={comment}>
                                {comment}
                            </option>
                        ))}
                    </select>
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
                    <button
                        type="button"
                        onClick={printSelected}
                        disabled={selectedIds.length === 0}
                        className="btn-action btn-action-sm btn-print"
                    >
                        <Printer className="mr-1.5 h-4 w-4" />
                        Cetak
                    </button>
                    <button
                        type="button"
                        onClick={deleteByComment}
                        disabled={!selected_router_id || !(filters.comment || '').trim()}
                        className="btn-action btn-action-sm btn-danger"
                    >
                        <Trash2 className="mr-1.5 h-4 w-4" />
                        Hapus
                    </button>
                    <Link
                        href="/admin/network/hotspot/reports"
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        <BarChart3 className="mr-1.5 h-4 w-4" />
                        Laporan
                    </Link>
                    <Link
                        href={`/admin/network/hotspot/users/create${
                            selected_router_id ? `?router_id=${selected_router_id}` : ''
                        }`}
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        <UserPlus className="mr-1.5 h-4 w-4" />
                        Tambah User
                    </Link>
                    <Link
                        href={`/admin/network/hotspot/generate${
                            selected_router_id ? `?router_id=${selected_router_id}` : ''
                        }`}
                        className="btn-action btn-action-sm btn-primary"
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
                            <th className="w-10 px-4 py-3">
                                <input
                                    type="checkbox"
                                    checked={allSelected}
                                    onChange={toggleSelectAll}
                                    disabled={printableUsers.length === 0}
                                    aria-label="Pilih semua voucher yang bisa dicetak"
                                />
                            </th>
                            <th className="px-4 py-3 font-semibold">Username</th>
                            <th className="px-4 py-3 font-semibold">Profile</th>
                            <th className="hidden px-4 py-3 font-semibold md:table-cell">
                                Agen / Harga
                            </th>
                            <th className="hidden px-4 py-3 font-semibold lg:table-cell">Limit</th>
                            <th className="hidden px-4 py-3 font-semibold xl:table-cell">Usage</th>
                            <th className="px-4 py-3 font-semibold">Status</th>
                            <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((user) => (
                            <tr
                                key={user.id || user.name}
                                className="border-b border-ink/5 last:border-0"
                            >
                                <td className="px-4 py-3">
                                    {user.voucher_id ? (
                                        <input
                                            type="checkbox"
                                            checked={selectedIds.includes(user.voucher_id)}
                                            onChange={() => toggleSelect(user.voucher_id)}
                                            aria-label={`Pilih ${user.name}`}
                                        />
                                    ) : (
                                        <span className="block w-4" />
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{user.name}</p>
                                    {user.comment && (
                                        <p className="text-xs text-ink-soft">{user.comment}</p>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{user.profile || '—'}</td>
                                <td className="hidden px-4 py-3 text-ink-soft md:table-cell">
                                    <p>{user.agent_name || '—'}</p>
                                    <p className="text-xs font-medium text-ink">
                                        {user.sell_price_label || '—'}
                                    </p>
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft lg:table-cell">
                                    <p>{user.limit_uptime || '—'}</p>
                                    <p className="text-xs">{formatBytes(user.limit_bytes_total)}</p>
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft xl:table-cell">
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
                                    ) : user.is_expired ? (
                                        <span className="bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">
                                            Expired
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
                                        {user.is_expired && (
                                            <button
                                                type="button"
                                                onClick={() => resetExpired(user)}
                                                className="btn-action btn-action-xs btn-secondary"
                                            >
                                                <RotateCcw className="h-3.5 w-3.5" />
                                                Reset
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => toggle(user)}
                                            className="btn-action btn-action-xs btn-warn"
                                        >
                                            <Power className="h-3.5 w-3.5" />
                                            {user.disabled ? 'Aktifkan' : 'Nonaktif'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => remove(user)}
                                            className="btn-action btn-action-xs btn-danger"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-10 text-center text-ink-soft">
                                    {selected_router_id
                                        ? 'Belum ada voucher, atau filter tidak cocok.'
                                        : 'Pilih atau tambahkan router terlebih dahulu.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <LocalPagination
                page={paged.current_page}
                lastPage={paged.last_page}
                from={paged.from}
                to={paged.to}
                total={paged.total}
                label="voucher"
                onPage={(next) => {
                    setSelectedIds([]);
                    setPage(next);
                }}
            />
        </AdminLayout>
    );
}
