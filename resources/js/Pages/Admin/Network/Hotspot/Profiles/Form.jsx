import { Head, Link, router, useForm } from '@inertiajs/react';
import AdminLayout from '../../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

const expiredModeAliases = {
    remove: 'rem',
    notice: 'ntf',
    'remove,notice': 'remc',
};

function canonicalExpiredMode(value) {
    if (!value) return '';
    return expiredModeAliases[value] || value;
}

function moneyField(value) {
    if (value == null || value === '' || Number(value) === 0) return '';
    return String(value);
}

export default function Form({
    profile,
    routers,
    selected_router_id,
    parent_queues = [],
    address_pools = [],
    expired_modes = [],
}) {
    const editing = Boolean(profile);

    const { data, setData, post, put, processing, errors, transform } = useForm({
        router_id: selected_router_id || routers[0]?.id || '',
        name: profile?.name || '',
        rate_limit: profile?.rate_limit || '',
        session_timeout: profile?.session_timeout || '',
        idle_timeout: profile?.idle_timeout || '',
        shared_users: profile?.shared_users ? Number(profile.shared_users) : 1,
        address_list: profile?.address_list || '',
        address_pool: profile?.address_pool || '',
        expired_mode: canonicalExpiredMode(profile?.expired_mode) || (editing ? '' : 'rem'),
        validity: profile?.validity || '',
        price: moneyField(profile?.price),
        selling_price: moneyField(profile?.selling_price),
        lock_user: Boolean(profile?.lock_user),
        parent_queue: profile?.parent_queue || '',
    });

    const submit = (e) => {
        e.preventDefault();

        transform((form) => ({
            ...form,
            shared_users: Number(form.shared_users || 1),
            lock_user: Boolean(form.lock_user),
            parent_queue: form.parent_queue || null,
            expired_mode: form.expired_mode || null,
            validity: form.validity || null,
            price: form.price === '' ? 0 : Number(form.price),
            selling_price: form.selling_price === '' ? 0 : Number(form.selling_price),
            rate_limit: form.rate_limit || null,
            session_timeout: form.session_timeout || null,
            idle_timeout: form.idle_timeout || null,
            address_list: form.address_list || null,
            address_pool: form.address_pool || null,
        }));

        if (editing) {
            put(
                `/admin/network/hotspot/profiles/${selected_router_id}/${encodeURIComponent(profile.id)}`,
            );
        } else {
            post('/admin/network/hotspot/profiles');
        }
    };

    return (
        <AdminLayout
            title={editing ? 'Edit Profile Hotspot' : 'Tambah Profile Hotspot'}
            subtitle="Konfigurasi Hotspot User Profile di RouterOS"
        >
            <Head title={editing ? 'Edit Profile Hotspot' : 'Tambah Profile Hotspot'} />

            <form
                onSubmit={submit}
                className="max-w-2xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                {!editing && (
                    <label className="block text-sm font-medium text-ink">
                        Router
                        <select
                            value={data.router_id || ''}
                            onChange={(e) => {
                                const routerId = e.target.value;
                                setData('router_id', routerId);
                                if (routerId) {
                                    router.get(
                                        '/admin/network/hotspot/profiles/create',
                                        { router_id: routerId },
                                        { preserveState: false, replace: true },
                                    );
                                }
                            }}
                            className={fieldClass}
                            required
                        >
                            <option value="">Pilih router</option>
                            {routers.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name} ({item.host})
                                </option>
                            ))}
                        </select>
                        {errors.router_id && (
                            <span className="mt-1 block text-xs text-red-600">{errors.router_id}</span>
                        )}
                    </label>
                )}

                <label className="block text-sm font-medium text-ink">
                    Nama profile
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={fieldClass}
                        placeholder="1jam / 1hari / unlimited"
                        required
                    />
                    {errors.name && (
                        <span className="mt-1 block text-xs text-red-600">{errors.name}</span>
                    )}
                </label>

                <label className="block text-sm font-medium text-ink">
                    Rate limit
                    <input
                        type="text"
                        value={data.rate_limit}
                        onChange={(e) => setData('rate_limit', e.target.value)}
                        className={fieldClass}
                        placeholder="512k/1M atau 2M/5M"
                    />
                    <span className="mt-1 block text-xs text-ink-soft">
                        Format: upload/download dari sisi router (contoh: 512k/1M)
                    </span>
                </label>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Session timeout
                        <input
                            type="text"
                            value={data.session_timeout}
                            onChange={(e) => setData('session_timeout', e.target.value)}
                            className={fieldClass}
                            placeholder="1h / 1d / 0s"
                        />
                        <span className="mt-1 block text-xs text-ink-soft">
                            Lama satu sesi koneksi di RouterOS. Terpisah dari validity
                            voucher. Kosongkan jika sesi boleh berjalan sampai voucher expired.
                        </span>
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Idle timeout
                        <input
                            type="text"
                            value={data.idle_timeout}
                            onChange={(e) => setData('idle_timeout', e.target.value)}
                            className={fieldClass}
                            placeholder="15m / none"
                        />
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Shared users
                        <input
                            type="number"
                            min={1}
                            max={1000}
                            value={data.shared_users}
                            onChange={(e) => setData('shared_users', Number(e.target.value))}
                            className={fieldClass}
                        />
                        <span className="mt-1 block text-xs text-ink-soft">
                            Jumlah perangkat simultan per username (voucher biasanya 1)
                        </span>
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Address pool
                        <select
                            value={data.address_pool || ''}
                            onChange={(e) => setData('address_pool', e.target.value)}
                            className={fieldClass}
                        >
                            <option value="">none</option>
                            {address_pools.map((pool) => (
                                <option key={pool.name} value={pool.name}>
                                    {pool.name}
                                    {pool.ranges ? ` (${pool.ranges})` : ''}
                                </option>
                            ))}
                        </select>
                        <span className="mt-1 block text-xs text-ink-soft">
                            Pool IP untuk user hotspot. Ambil dari `/ip/pool`.
                        </span>
                        {errors.address_pool && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.address_pool}
                            </span>
                        )}
                    </label>
                    </div>

                    <label className="block text-sm font-medium text-ink">
                        Address list
                        <input
                            type="text"
                            value={data.address_list}
                            onChange={(e) => setData('address_list', e.target.value)}
                            className={fieldClass}
                            placeholder="opsional"
                        />
                    </label>

                    <div className="border border-ink/10 bg-mist/30 p-4">
                    <p className="text-sm font-semibold text-ink">Kontrol expired & antrian</p>
                    <p className="mt-1 text-xs text-ink-soft">
                        Sesuaikan perilaku saat voucher habis, penguncian MAC, dan parent queue
                        MikroTik.
                    </p>

                    <div className="mt-3 grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            Expired mode
                            <select
                                value={data.expired_mode || ''}
                                onChange={(e) => setData('expired_mode', e.target.value)}
                                className={fieldClass}
                            >
                                {expired_modes.map((mode) => (
                                    <option key={mode.value || 'none'} value={mode.value}>
                                        {mode.label}
                                    </option>
                                ))}
                            </select>
                            <span className="mt-1 block text-xs text-ink-soft">
                                {expired_modes.find((mode) => mode.value === (data.expired_mode || ''))
                                    ?.description || 'Perilaku saat voucher expired'}
                            </span>
                            {errors.expired_mode && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.expired_mode}
                                </span>
                            )}
                        </label>

                        <label className="block text-sm font-medium text-ink">
                            Validity
                            <input
                                type="text"
                                value={data.validity}
                                onChange={(e) => setData('validity', e.target.value)}
                                className={fieldClass}
                                placeholder="1d / 7d / 12h / 5h30m"
                                required={Boolean(data.expired_mode)}
                            />
                            <span className="mt-1 block text-xs text-ink-soft">
                                Umur voucher sejak login pertama. Dipakai skrip expire
                                Mikhmon, bukan session-timeout.
                            </span>
                            {errors.validity && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.validity}
                                </span>
                            )}
                        </label>
                    </div>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            Price
                            <input
                                type="number"
                                min={0}
                                value={data.price}
                                onChange={(e) => setData('price', e.target.value)}
                                className={fieldClass}
                                placeholder="0"
                            />
                            <span className="mt-1 block text-xs text-ink-soft">
                                Harga internal. Dipakai catatan penjualan Mikhmon (remc/ntfc).
                            </span>
                            {errors.price && (
                                <span className="mt-1 block text-xs text-red-600">{errors.price}</span>
                            )}
                        </label>
                        <label className="block text-sm font-medium text-ink">
                            Selling price
                            <input
                                type="number"
                                min={0}
                                value={data.selling_price}
                                onChange={(e) => setData('selling_price', e.target.value)}
                                className={fieldClass}
                                placeholder="0"
                            />
                            <span className="mt-1 block text-xs text-ink-soft">
                                Harga yang tampil di voucher. 0 = pakai Price.
                            </span>
                            {errors.selling_price && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.selling_price}
                                </span>
                            )}
                        </label>
                    </div>

                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            Parent queue
                            <select
                                value={data.parent_queue || ''}
                                onChange={(e) => setData('parent_queue', e.target.value)}
                                className={fieldClass}
                            >
                                <option value="">Tanpa parent queue</option>
                                {parent_queues.map((queue) => (
                                    <option key={queue.name} value={queue.name}>
                                        {queue.name}
                                        {queue.target ? ` (${queue.target})` : ''}
                                    </option>
                                ))}
                            </select>
                            <span className="mt-1 block text-xs text-ink-soft">
                                Ambil dari `/queue/simple` di RouterOS
                            </span>
                            {errors.parent_queue && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.parent_queue}
                                </span>
                            )}
                        </label>
                    </div>

                    <label className="mt-4 flex cursor-pointer items-start gap-3 border border-ink/10 bg-white px-3 py-3">
                        <input
                            type="checkbox"
                            checked={Boolean(data.lock_user)}
                            onChange={(e) => setData('lock_user', e.target.checked)}
                            className="mt-0.5 h-4 w-4 border-ink/30 text-signal focus:ring-signal"
                        />
                        <span>
                            <span className="block text-sm font-medium text-ink">Lock user</span>
                            <span className="mt-0.5 block text-xs text-ink-soft">
                                Kunci voucher ke MAC address perangkat pertama yang login.
                            </span>
                            {errors.lock_user && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.lock_user}
                                </span>
                            )}
                        </span>
                    </label>
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Simpan ke MikroTik'}
                    </button>
                    <Link
                        href={`/admin/network/hotspot/profiles${
                            selected_router_id ? `?router_id=${selected_router_id}` : ''
                        }`}
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
