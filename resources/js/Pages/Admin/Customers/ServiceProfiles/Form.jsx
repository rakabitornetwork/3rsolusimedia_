import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AdminLayout from '../../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function Form({
    package: item,
    routers,
    router_profiles: initialProfiles,
    default_router_id,
}) {
    const editing = Boolean(item);
    const [profiles, setProfiles] = useState(initialProfiles || []);
    const [routerId, setRouterId] = useState(default_router_id || routers[0]?.id || '');
    const [loadingProfiles, setLoadingProfiles] = useState(false);
    const [profileError, setProfileError] = useState('');

    const { data, setData, post, put, processing, errors } = useForm({
        name: item?.name || '',
        price: item?.price ?? 120000,
        mikrotik_profile: item?.mikrotik_profile || '',
        description: item?.description || '',
        sort_order: item?.sort_order ?? 0,
        is_active: item?.is_active ?? true,
    });

    const loadProfiles = async (id) => {
        if (!id) {
            setProfiles([]);
            return;
        }

        setLoadingProfiles(true);
        setProfileError('');

        try {
            const response = await fetch(
                `/admin/customers/pppoe/profiles?router_id=${encodeURIComponent(id)}`,
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
                setProfileError(payload.message || 'Gagal mengambil profile RouterOS');
                setProfiles([]);
                return;
            }

            setProfiles(payload.profiles || []);
        } catch {
            setProfileError('Tidak bisa mengambil profile dari router');
            setProfiles([]);
        } finally {
            setLoadingProfiles(false);
        }
    };

    useEffect(() => {
        if (routerId) loadProfiles(routerId);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [routerId]);

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            put(`/admin/customers/pppoe/service-profiles/${item.id}`);
        } else {
            post('/admin/customers/pppoe/service-profiles');
        }
    };

    return (
        <AdminLayout
            title={editing ? 'Edit Paket Layanan' : 'Tambah Paket Layanan'}
            subtitle="Hubungkan nama paket ke Profile PPPoE di MikroTik"
        >
            <Head title={editing ? 'Edit Paket Layanan' : 'Tambah Paket Layanan'} />

            <form
                onSubmit={submit}
                className="max-w-2xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                <label className="block text-sm font-medium text-ink">
                    Nama paket
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={fieldClass}
                        placeholder="Hemat / Keluarga / Plus"
                        required
                    />
                    {errors.name && (
                        <span className="mt-1 block text-xs text-red-600">{errors.name}</span>
                    )}
                </label>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Harga (Rp)
                        <input
                            type="number"
                            min={0}
                            value={data.price}
                            onChange={(e) => setData('price', Number(e.target.value))}
                            className={fieldClass}
                            required
                        />
                        {errors.price && (
                            <span className="mt-1 block text-xs text-red-600">{errors.price}</span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Urutan tampil
                        <input
                            type="number"
                            min={0}
                            value={data.sort_order}
                            onChange={(e) => setData('sort_order', Number(e.target.value))}
                            className={fieldClass}
                        />
                    </label>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Ambil daftar profile dari router
                    <select
                        value={routerId || ''}
                        onChange={(e) => setRouterId(e.target.value)}
                        className={fieldClass}
                    >
                        <option value="">Pilih router</option>
                        {routers.map((routerItem) => (
                            <option key={routerItem.id} value={routerItem.id}>
                                {routerItem.name} ({routerItem.host})
                            </option>
                        ))}
                    </select>
                </label>

                <label className="block text-sm font-medium text-ink">
                    Profile PPPoE
                    <select
                        value={data.mikrotik_profile || ''}
                        onChange={(e) => setData('mikrotik_profile', e.target.value)}
                        className={fieldClass}
                        required
                        disabled={loadingProfiles}
                    >
                        <option value="">
                            {loadingProfiles ? 'Memuat profile...' : 'Pilih profile RouterOS'}
                        </option>
                        {profiles.map((profile) => (
                            <option key={profile.name} value={profile.name}>
                                {profile.name}
                                {profile.rate_limit ? ` (${profile.rate_limit})` : ''}
                            </option>
                        ))}
                    </select>
                    {data.mikrotik_profile &&
                        !profiles.some((profile) => profile.name === data.mikrotik_profile) && (
                            <p className="mt-1 text-xs text-ink-soft">
                                Profile tersimpan: <strong>{data.mikrotik_profile}</strong>
                            </p>
                        )}
                    {errors.mikrotik_profile && (
                        <span className="mt-1 block text-xs text-red-600">
                            {errors.mikrotik_profile}
                        </span>
                    )}
                </label>

                {profileError && (
                    <div className="border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        {profileError}
                    </div>
                )}

                <label className="block text-sm font-medium text-ink">
                    Deskripsi
                    <textarea
                        rows={3}
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        className={fieldClass}
                        placeholder="Keterangan singkat paket"
                    />
                </label>

                <label className="inline-flex items-center gap-2 text-sm text-ink">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                    />
                    Aktifkan paket layanan
                </label>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-signal-deep px-5 py-3 text-sm font-bold text-white hover:bg-ink disabled:opacity-60"
                    >
                        {processing ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Simpan Paket'}
                    </button>
                    <Link
                        href="/admin/customers/pppoe/service-profiles"
                        className="border border-ink/15 px-5 py-3 text-sm font-semibold text-ink-soft hover:bg-mist"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
