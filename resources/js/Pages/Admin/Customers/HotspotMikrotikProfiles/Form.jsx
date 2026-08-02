import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Form({
    profile,
    routers,
    selected_router_id,
    ip_pools: initialPools,
    queue_types: initialQueueTypes,
}) {
    const editing = Boolean(profile);
    const [pools, setPools] = useState(initialPools || []);
    const [queueTypes, setQueueTypes] = useState(initialQueueTypes || []);
    const [loadingOptions, setLoadingOptions] = useState(false);
    const [optionsError, setOptionsError] = useState('');

    const { data, setData, post, put, processing, errors } = useForm({
        router_id: selected_router_id || routers[0]?.id || '',
        name: profile?.name || '',
        rate_limit: profile?.rate_limit || '',
        local_address: profile?.local_address || '',
        remote_address: profile?.remote_address || '',
        queue_type_rx: profile?.queue_type_rx || '',
        queue_type_tx: profile?.queue_type_tx || '',
        only_one: profile?.only_one || 'default',
        dns_server: profile?.dns_server || '',
        comment: profile?.comment || '',
    });

    const loadOptions = async (routerId) => {
        if (!routerId) {
            setPools([]);
            setQueueTypes([]);
            return;
        }

        setLoadingOptions(true);
        setOptionsError('');

        try {
            const response = await fetch(
                `/admin/customers/pppoe/mikrotik-profiles/options?router_id=${encodeURIComponent(routerId)}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );
            const payload = await response.json();

            if (!payload.ok) {
                setOptionsError(payload.message || 'Gagal mengambil data dari RouterOS');
                setPools([]);
                setQueueTypes([]);
                return;
            }

            setPools(payload.pools || []);
            setQueueTypes(payload.queue_types || []);
        } catch {
            setOptionsError('Tidak bisa mengambil data dari router');
            setPools([]);
            setQueueTypes([]);
        } finally {
            setLoadingOptions(false);
        }
    };

    const changeRouter = (routerId) => {
        setData({
            ...data,
            router_id: routerId,
            remote_address: '',
            // keep queue type choices; only reload option lists for the new router
        });
        loadOptions(routerId);
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            put(
                `/admin/customers/pppoe/mikrotik-profiles/${selected_router_id}/${encodeURIComponent(profile.id)}`,
            );
        } else {
            post('/admin/customers/pppoe/mikrotik-profiles');
        }
    };

    const remoteInList = pools.some((pool) => pool.name === data.remote_address);
    const rxInList = queueTypes.some((item) => item.name === data.queue_type_rx);
    const txInList = queueTypes.some((item) => item.name === data.queue_type_tx);

    const queueTypeOptions = (selected, inList) => (
        <>
            <option value="">{loadingOptions ? 'Memuat queue type...' : 'Pilih queue type'}</option>
            {selected && !inList && <option value={selected}>{selected}</option>}
            {queueTypes.map((item) => (
                <option key={item.name} value={item.name}>
                    {item.name}
                    {item.kind ? ` (${item.kind})` : ''}
                </option>
            ))}
        </>
    );

    return (
        <AdminLayout
            title={editing ? 'Edit Profile MikroTik' : 'Tambah Profile MikroTik'}
            subtitle="Konfigurasi PPP Profile di RouterOS"
        >
            <Head title={editing ? 'Edit Profile MikroTik' : 'Tambah Profile MikroTik'} />

            <form
                onSubmit={submit}
                className="max-w-2xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                {!editing && (
                    <label className="block text-sm font-medium text-ink">
                        Router
                        <select
                            value={data.router_id || ''}
                            onChange={(e) => changeRouter(e.target.value)}
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
                        placeholder="paket-120rb / isolir / expired"
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
                        placeholder="10M/10M atau 512k/2M"
                    />
                    <span className="mt-1 block text-xs text-ink-soft">
                        Format MikroTik: rx/tx dari sisi router (upload/download pelanggan)
                    </span>
                </label>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Queue Type Rx
                        <select
                            value={data.queue_type_rx || ''}
                            onChange={(e) => setData('queue_type_rx', e.target.value)}
                            className={fieldClass}
                            disabled={loadingOptions}
                        >
                            {queueTypeOptions(data.queue_type_rx, rxInList)}
                        </select>
                        <span className="mt-1 block text-xs text-ink-soft">
                            Rx = upload pelanggan. Isi keduanya agar tidak tertimpa default.
                        </span>
                        {errors.queue_type_rx && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.queue_type_rx}
                            </span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Queue Type Tx
                        <select
                            value={data.queue_type_tx || ''}
                            onChange={(e) => setData('queue_type_tx', e.target.value)}
                            className={fieldClass}
                            disabled={loadingOptions}
                        >
                            {queueTypeOptions(data.queue_type_tx, txInList)}
                        </select>
                        <span className="mt-1 block text-xs text-ink-soft">
                            Tx = download pelanggan (contoh: my-cake / pcq-download-default)
                        </span>
                        {errors.queue_type_tx && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.queue_type_tx}
                            </span>
                        )}
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Local address
                        <input
                            type="text"
                            value={data.local_address}
                            onChange={(e) => setData('local_address', e.target.value)}
                            className={fieldClass}
                            placeholder="IP atau pool"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Remote address
                        <select
                            value={data.remote_address || ''}
                            onChange={(e) => setData('remote_address', e.target.value)}
                            className={fieldClass}
                            disabled={loadingOptions}
                        >
                            <option value="">
                                {loadingOptions ? 'Memuat IP pool...' : 'Pilih IP pool'}
                            </option>
                            {data.remote_address && !remoteInList && (
                                <option value={data.remote_address}>{data.remote_address}</option>
                            )}
                            {pools.map((pool) => (
                                <option key={pool.name} value={pool.name}>
                                    {pool.name}
                                    {pool.ranges ? ` (${pool.ranges})` : ''}
                                </option>
                            ))}
                        </select>
                        <span className="mt-1 block text-xs text-ink-soft">
                            Diambil dari IP → Pool di MikroTik
                        </span>
                        {errors.remote_address && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.remote_address}
                            </span>
                        )}
                    </label>
                </div>

                {optionsError && (
                    <div className="border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        {optionsError}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Only one
                        <select
                            value={data.only_one || 'default'}
                            onChange={(e) => setData('only_one', e.target.value)}
                            className={fieldClass}
                        >
                            <option value="default">Default</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        DNS server
                        <input
                            type="text"
                            value={data.dns_server}
                            onChange={(e) => setData('dns_server', e.target.value)}
                            className={fieldClass}
                            placeholder="8.8.8.8"
                        />
                    </label>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Comment
                    <input
                        type="text"
                        value={data.comment}
                        onChange={(e) => setData('comment', e.target.value)}
                        className={fieldClass}
                        placeholder="Catatan singkat"
                    />
                </label>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-signal-deep px-5 py-3 text-sm font-bold text-white hover:bg-ink disabled:opacity-60"
                    >
                        {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Simpan ke MikroTik'}
                    </button>
                    <Link
                        href={`/admin/customers/pppoe/mikrotik-profiles${
                            selected_router_id ? `?router_id=${selected_router_id}` : ''
                        }`}
                        className="border border-ink/15 px-5 py-3 text-sm font-semibold text-ink-soft hover:bg-mist"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
