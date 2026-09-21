import { Head, Link, useForm } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import UserAvatar, { getInitials } from '../../../Components/UserAvatar';
import AdminLayout from '../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

function asIdList(value) {
    return (Array.isArray(value) ? value : []).map((id) => Number(id)).filter((id) => id > 0);
}

export default function Form({ user, role_options, pppoe_customers = [], routers = [] }) {
    const editing = Boolean(user);
    const [preview, setPreview] = useState(user?.avatar_url || null);
    const [custSearch, setCustSearch] = useState('');
    const [routerId, setRouterId] = useState(() => {
        const assigned = asIdList(user?.assigned_customer_ids);
        const assignedRouters = [
            ...new Set(
                pppoe_customers
                    .filter((customer) => assigned.includes(Number(customer.id)))
                    .map((customer) => customer.mikrotik_router_id)
                    .filter(Boolean)
                    .map(String),
            ),
        ];
        if (assignedRouters.length === 1) {
            return assignedRouters[0];
        }
        if (assignedRouters.length > 1) {
            return assignedRouters[0];
        }

        return routers[0]?.id ? String(routers[0].id) : '';
    });

    const { data, setData, post, processing, errors, transform } = useForm({
        name: user?.name || '',
        email: user?.email || '',
        role: user?.role || role_options[0]?.value || 'admin',
        billing_commission: user?.billing_commission ?? 0,
        assigned_customer_ids: asIdList(user?.assigned_customer_ids),
        commission_customer_ids: asIdList(user?.commission_customer_ids),
        password: '',
        password_confirmation: '',
        avatar: null,
        remove_avatar: false,
        ...(editing ? { _method: 'put' } : {}),
    });

    const assignedIds = asIdList(data.assigned_customer_ids);
    const commissionIds = asIdList(data.commission_customer_ids);

    useEffect(() => {
        if (!data.avatar) return undefined;

        const url = URL.createObjectURL(data.avatar);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [data.avatar]);

    const initials = useMemo(() => getInitials(data.name), [data.name]);

    const selectedRouter = useMemo(
        () => routers.find((router) => String(router.id) === String(routerId)) || null,
        [routers, routerId],
    );

    const routerCustomers = useMemo(() => {
        if (!routerId) return pppoe_customers;
        return pppoe_customers.filter(
            (customer) => String(customer.mikrotik_router_id) === String(routerId),
        );
    }, [pppoe_customers, routerId]);

    const filteredCustomers = useMemo(() => {
        if (!custSearch.trim()) return routerCustomers;
        const q = custSearch.toLowerCase();
        return routerCustomers.filter(
            (c) =>
                c.name.toLowerCase().includes(q) ||
                c.username.toLowerCase().includes(q) ||
                (c.phone && c.phone.includes(q)) ||
                (c.router_name && c.router_name.toLowerCase().includes(q)),
        );
    }, [routerCustomers, custSearch]);

    const visibleIds = filteredCustomers.map((customer) => Number(customer.id));
    const allVisibleSelected =
        visibleIds.length > 0 && visibleIds.every((id) => assignedIds.includes(id));
    const selectedOnRouter = routerCustomers.filter((customer) =>
        assignedIds.includes(Number(customer.id)),
    ).length;
    const commissionOnRouter = routerCustomers.filter((customer) =>
        commissionIds.includes(Number(customer.id)),
    ).length;
    const selectedOnOtherRouters = assignedIds.length - selectedOnRouter;

    const setAssignment = (nextAssigned, nextCommission) => {
        const assigned = asIdList(nextAssigned);
        setData({
            ...data,
            assigned_customer_ids: assigned,
            commission_customer_ids: asIdList(nextCommission).filter((id) => assigned.includes(id)),
        });
    };

    const toggleCustomer = (id) => {
        const numericId = Number(id);
        if (assignedIds.includes(numericId)) {
            setAssignment(
                assignedIds.filter((cId) => cId !== numericId),
                commissionIds.filter((cId) => cId !== numericId),
            );
            return;
        }

        setAssignment([...assignedIds, numericId], commissionIds);
    };

    const toggleCommission = (id) => {
        const numericId = Number(id);
        if (commissionIds.includes(numericId)) {
            setAssignment(
                assignedIds,
                commissionIds.filter((cId) => cId !== numericId),
            );
            return;
        }

        setAssignment(
            assignedIds.includes(numericId) ? assignedIds : [...assignedIds, numericId],
            [...commissionIds, numericId],
        );
    };

    const toggleAllCustomers = () => {
        if (allVisibleSelected) {
            setAssignment(
                assignedIds.filter((id) => !visibleIds.includes(id)),
                commissionIds.filter((id) => !visibleIds.includes(id)),
            );
            return;
        }

        setAssignment([...new Set([...assignedIds, ...visibleIds])], commissionIds);
    };

    const submit = (e) => {
        e.preventDefault();

        transform((form) => {
            const payload = { ...form };

            if (payload.role !== 'agen') {
                delete payload.assigned_customer_ids;
                delete payload.commission_customer_ids;
                delete payload.billing_commission;
            } else {
                payload.billing_commission = Math.max(
                    0,
                    Number.parseInt(String(payload.billing_commission || 0), 10) || 0,
                );
                payload.assigned_customer_ids = asIdList(payload.assigned_customer_ids);
                payload.commission_customer_ids = asIdList(payload.commission_customer_ids).filter(
                    (id) => payload.assigned_customer_ids.includes(id),
                );
            }

            if (!payload.password) {
                delete payload.password;
                delete payload.password_confirmation;
            }

            if (!(payload.avatar instanceof File)) {
                delete payload.avatar;
            }

            if (!payload.remove_avatar) {
                delete payload.remove_avatar;
            }

            return payload;
        });

        const url = editing ? `/admin/users/${user.id}` : '/admin/users';
        const hasFile = data.avatar instanceof File;

        post(url, {
            forceFormData: hasFile || Boolean(data.remove_avatar),
        });
    };

    const onAvatarChange = (e) => {
        const file = e.target.files?.[0] || null;
        setData('avatar', file);
        setData('remove_avatar', false);
        if (!file) {
            setPreview(user?.avatar_url || null);
        }
    };

    const clearAvatar = () => {
        setData('avatar', null);
        setData('remove_avatar', true);
        setPreview(null);
    };

    return (
        <AdminLayout
            title={editing ? 'Edit User' : 'Tambah User'}
            subtitle="Atur avatar, nama, email, password, dan role akses"
        >
            <Head title={editing ? 'Edit User' : 'Tambah User'} />

            <form
                onSubmit={submit}
                className="max-w-3xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                <div className="flex flex-wrap items-center gap-4 border border-ink/10 bg-mist/40 p-4">
                    <UserAvatar
                        name={data.name}
                        role={data.role}
                        src={preview}
                        initials={initials}
                        size="xl"
                    />
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-ink">Avatar pengguna</p>
                        <p className="mt-0.5 text-xs text-ink-soft">
                            Upload foto (JPG/PNG, maks. 2MB). Jika kosong, inisial nama dipakai otomatis.
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <label className="btn-action btn-action-xs btn-secondary">
                                <ImagePlus className="h-3.5 w-3.5" />
                                Pilih foto
                                <input
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={onAvatarChange}
                                />
                            </label>
                            {(preview || user?.avatar_url) && !data.remove_avatar && (
                                <button
                                    type="button"
                                    onClick={clearAvatar}
                                    className="btn-action btn-action-xs btn-danger"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                    Hapus avatar
                                </button>
                            )}
                        </div>
                        {errors.avatar && (
                            <span className="mt-2 block text-xs text-red-600">{errors.avatar}</span>
                        )}
                    </div>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Nama
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={fieldClass}
                        required
                    />
                    {errors.name && (
                        <span className="mt-1 block text-xs text-red-600">{errors.name}</span>
                    )}
                </label>

                <label className="block text-sm font-medium text-ink">
                    Email
                    <input
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className={fieldClass}
                        required
                    />
                    {errors.email && (
                        <span className="mt-1 block text-xs text-red-600">{errors.email}</span>
                    )}
                </label>

                <fieldset>
                    <legend className="text-sm font-medium text-ink">Role</legend>
                    <div className="mt-2 space-y-2">
                        {role_options.map((role) => (
                            <label
                                key={role.value}
                                className={`flex cursor-pointer gap-3 border px-3 py-3 text-sm transition ${
                                    data.role === role.value
                                        ? 'border-signal bg-signal/5'
                                        : 'border-ink/10 hover:bg-mist/60'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="role"
                                    value={role.value}
                                    checked={data.role === role.value}
                                    onChange={() => setData('role', role.value)}
                                    className="mt-1"
                                />
                                <span>
                                    <span className="font-semibold text-ink">{role.label}</span>
                                    <span className="mt-0.5 block text-xs text-ink-soft">
                                        {role.description}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    {errors.role && (
                        <span className="mt-1 block text-xs text-red-600">{errors.role}</span>
                    )}
                </fieldset>

                {data.role === 'agen' && (
                    <div className="space-y-4 border border-signal/30 bg-signal/5 p-4">
                        <label className="block text-sm font-medium text-ink">
                            Komisi billing (Rp / tagihan lunas)
                            <input
                                type="number"
                                min="0"
                                step="1000"
                                value={data.billing_commission}
                                onChange={(e) => setData('billing_commission', e.target.value)}
                                className={`${fieldClass} bg-white`}
                            />
                            <span className="mt-1 block text-xs font-normal text-ink-soft">
                                Nominal tetap yang di-snapshot setiap kali tagihan pelanggan agen
                                ini dilunasi.
                            </span>
                            {errors.billing_commission && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.billing_commission}
                                </span>
                            )}
                        </label>

                        <div>
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <h4 className="text-sm font-bold text-ink">
                                        Penugasan Pelanggan PPPoE
                                    </h4>
                                    <p className="text-xs text-ink-soft">
                                        Pilih RouterOS, lalu tugaskan pelanggan. Komisi hanya untuk
                                        yang ditandai khusus ({assignedIds.length} ditugaskan,{' '}
                                        {commissionIds.length} berkomisi).
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={toggleAllCustomers}
                                    disabled={filteredCustomers.length === 0}
                                    className="btn-action btn-action-xs btn-secondary"
                                >
                                    {allVisibleSelected ? 'Hapus pilihan router ini' : 'Pilih semua di router ini'}
                                </button>
                            </div>

                            <div className="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                <label className="block text-xs font-medium text-ink">
                                    RouterOS
                                    <select
                                        value={routerId}
                                        onChange={(e) => {
                                            setRouterId(e.target.value);
                                            setCustSearch('');
                                        }}
                                        className="mt-1 w-full border border-ink/15 bg-white px-3 py-2 text-sm outline-none focus:border-signal"
                                    >
                                        <option value="">Semua RouterOS</option>
                                        {routers.map((router) => (
                                            <option key={router.id} value={router.id}>
                                                {router.name}
                                                {router.host ? ` (${router.host})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="block text-xs font-medium text-ink">
                                    Cari pelanggan
                                    <input
                                        type="text"
                                        placeholder="Nama, username, atau telp..."
                                        value={custSearch}
                                        onChange={(e) => setCustSearch(e.target.value)}
                                        className="mt-1 w-full border border-ink/15 bg-white px-3 py-2 text-sm outline-none focus:border-signal"
                                    />
                                </label>
                            </div>

                            <p className="mt-2 text-[11px] text-ink-soft">
                                {selectedRouter
                                    ? `${selectedOnRouter} ditugaskan, ${commissionOnRouter} berkomisi dari ${routerCustomers.length} pelanggan ${selectedRouter.name}.`
                                    : `${assignedIds.length} ditugaskan, ${commissionIds.length} berkomisi dari ${pppoe_customers.length} pelanggan.`}
                                {routerId && selectedOnOtherRouters > 0
                                    ? ` ${selectedOnOtherRouters} pelanggan di router lain tetap terpilih.`
                                    : ''}
                            </p>

                            <div className="mt-3 max-h-60 overflow-y-auto border border-ink/10 bg-white text-xs">
                                <div className="sticky top-0 grid grid-cols-[1fr_88px] items-center border-b border-ink/10 bg-mist px-2 py-1.5 text-[11px] font-semibold tracking-wide text-ink-soft uppercase">
                                    <span>Tugaskan</span>
                                    <span className="text-right">Komisi</span>
                                </div>
                                {routers.length === 0 ? (
                                    <p className="py-2 text-center text-ink-soft">
                                        Belum ada RouterOS. Tambah router di menu Jaringan dulu.
                                    </p>
                                ) : filteredCustomers.length === 0 ? (
                                    <p className="py-2 text-center text-ink-soft">
                                        Tidak ada pelanggan PPPoE
                                        {selectedRouter ? ` di ${selectedRouter.name}` : ''}.
                                    </p>
                                ) : (
                                    filteredCustomers.map((c) => {
                                        const isChecked = assignedIds.includes(Number(c.id));
                                        const getsCommission = commissionIds.includes(Number(c.id));
                                        return (
                                            <div
                                                key={c.id}
                                                className={`grid grid-cols-[1fr_88px] items-center gap-2 px-2 py-1.5 ${
                                                    isChecked
                                                        ? 'bg-signal/10 text-ink'
                                                        : 'text-ink-soft hover:bg-mist/60'
                                                }`}
                                            >
                                                <label className="flex min-w-0 cursor-pointer items-center gap-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={isChecked}
                                                        onChange={() => toggleCustomer(c.id)}
                                                        className="h-4 w-4 rounded border-ink/20 text-signal focus:ring-signal"
                                                    />
                                                    <span className="min-w-0">
                                                        <span className={isChecked ? 'font-semibold text-ink' : 'text-ink'}>
                                                            {c.name}
                                                        </span>
                                                        <span className="ml-2 font-mono text-[11px] text-ink-soft">
                                                            ({c.username})
                                                        </span>
                                                        {!routerId && c.router_name ? (
                                                            <span className="ml-2 text-[11px] text-ink-soft">
                                                                · {c.router_name}
                                                            </span>
                                                        ) : null}
                                                        {c.phone ? (
                                                            <span className="ml-2 text-[11px] text-ink-soft">
                                                                {c.phone}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </label>
                                                <label
                                                    className="flex cursor-pointer items-center justify-end gap-1.5 border-l border-ink/10 pl-2 text-[11px]"
                                                    title="Hanya pelanggan ini yang menghasilkan komisi agen"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={getsCommission}
                                                        onChange={() => toggleCommission(c.id)}
                                                        className="h-4 w-4 rounded border-ink/20 text-signal focus:ring-signal"
                                                    />
                                                    <span className={getsCommission ? 'font-semibold text-ink' : ''}>
                                                        Ya
                                                    </span>
                                                </label>
                                            </div>
                                        );
                                    })
                                )}
                            </div>
                            {errors.assigned_customer_ids && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.assigned_customer_ids}
                                </span>
                            )}
                            {errors.commission_customer_ids && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.commission_customer_ids}
                                </span>
                            )}
                        </div>
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Password {editing && <span className="font-normal text-ink-soft">(opsional)</span>}
                        <input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={fieldClass}
                            autoComplete="new-password"
                            required={!editing}
                        />
                        {errors.password && (
                            <span className="mt-1 block text-xs text-red-600">{errors.password}</span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Konfirmasi password
                        <input
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className={fieldClass}
                            autoComplete="new-password"
                            required={!editing || Boolean(data.password)}
                        />
                    </label>
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Tambah User'}
                    </button>
                    <Link
                        href="/admin/users"
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
