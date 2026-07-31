import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DatePickerField from '../../../../Components/Admin/DatePickerField';
import GpsMapPicker from '../../../../Components/Admin/GpsMapPicker';
import AdminLayout from '../../../../Layouts/AdminLayout';
import { billingDayOptions, calculateProrata } from '../../../../Utils/billingCycle';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

function todayIso() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export default function Form({
    customer,
    routers,
    packages,
    profiles: initialProfiles,
    isolir_profiles: initialIsolirProfiles,
    overdue_actions,
    billing_days: billingDaysProp,
}) {
    const editing = Boolean(customer);
    const billingDays = billingDaysProp?.length ? billingDaysProp : billingDayOptions;
    const [profiles, setProfiles] = useState(initialProfiles || []);
    const [isolirProfiles, setIsolirProfiles] = useState(initialIsolirProfiles || []);
    const [loadingProfiles, setLoadingProfiles] = useState(false);
    const [profileError, setProfileError] = useState('');

    const { data, setData, post, put, processing, errors } = useForm({
        mikrotik_router_id: customer?.mikrotik_router_id || routers[0]?.id || '',
        subscription_package_id: customer?.subscription_package_id || '',
        name: customer?.name || '',
        phone: customer?.phone || '',
        address: customer?.address || '',
        latitude: customer?.latitude ?? '',
        longitude: customer?.longitude ?? '',
        username: customer?.username || '',
        password: '',
        service_profile: customer?.service_profile || '',
        start_date: customer?.start_date || todayIso(),
        billing_day: customer?.billing_day || 10,
        overdue_action: customer?.overdue_action || 'isolir',
        isolir_profile: customer?.isolir_profile || '',
        notes: customer?.notes || '',
        is_active: customer?.is_active ?? true,
    });

    const selectedPackage = useMemo(
        () => packages.find((item) => String(item.id) === String(data.subscription_package_id)),
        [packages, data.subscription_package_id],
    );

    const prorata = useMemo(() => {
        if (!data.start_date || !data.billing_day || !selectedPackage) return null;
        return calculateProrata(data.start_date, data.billing_day, selectedPackage.price);
    }, [data.start_date, data.billing_day, selectedPackage]);

    const loadProfiles = async (routerId) => {
        if (!routerId) {
            setProfiles([]);
            setIsolirProfiles([]);
            return;
        }

        setLoadingProfiles(true);
        setProfileError('');

        try {
            const response = await fetch(
                `/admin/customers/pppoe/profiles?router_id=${encodeURIComponent(routerId)}`,
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
                setIsolirProfiles([]);
                return;
            }

            setProfiles(payload.profiles || []);
            setIsolirProfiles(payload.isolir_profiles || []);
        } catch {
            setProfileError('Tidak bisa mengambil profile dari router');
            setProfiles([]);
            setIsolirProfiles([]);
        } finally {
            setLoadingProfiles(false);
        }
    };

    useEffect(() => {
        if (data.mikrotik_router_id) {
            loadProfiles(data.mikrotik_router_id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.mikrotik_router_id]);

    useEffect(() => {
        if (!data.subscription_package_id) return;
        if (selectedPackage?.mikrotik_profile) {
            setData('service_profile', selectedPackage.mikrotik_profile);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.subscription_package_id]);

    useEffect(() => {
        if (data.overdue_action === 'bypass') {
            setData('isolir_profile', '');
        } else if (
            data.overdue_action === 'isolir' &&
            !data.isolir_profile &&
            isolirProfiles.length === 1
        ) {
            setData('isolir_profile', isolirProfiles[0].name);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.overdue_action, isolirProfiles]);

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            put(`/admin/customers/pppoe/${customer.id}`);
        } else {
            post('/admin/customers/pppoe');
        }
    };

    return (
        <AdminLayout
            title={editing ? 'Edit Pelanggan PPPoE' : 'Tambah Pelanggan PPPoE'}
            subtitle="Jatuh tempo tetap tiap bulan + tagihan pertama prorata"
        >
            <Head title={editing ? 'Edit Pelanggan PPPoE' : 'Tambah Pelanggan PPPoE'} />

            <form
                onSubmit={submit}
                className="max-w-3xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Router
                        <select
                            value={data.mikrotik_router_id}
                            onChange={(e) => setData('mikrotik_router_id', e.target.value)}
                            className={fieldClass}
                            required
                        >
                            <option value="">Pilih router</option>
                            {routers.map((routerItem) => (
                                <option key={routerItem.id} value={routerItem.id}>
                                    {routerItem.name} ({routerItem.host})
                                </option>
                            ))}
                        </select>
                        {errors.mikrotik_router_id && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.mikrotik_router_id}
                            </span>
                        )}
                    </label>

                    <label className="block text-sm font-medium text-ink">
                        Paket langganan
                        <select
                            value={data.subscription_package_id || ''}
                            onChange={(e) => setData('subscription_package_id', e.target.value)}
                            className={fieldClass}
                            required
                        >
                            <option value="">Pilih paket</option>
                            {packages.map((pkg) => (
                                <option key={pkg.id} value={pkg.id}>
                                    {pkg.name} — {pkg.price_label}
                                </option>
                            ))}
                        </select>
                        {errors.subscription_package_id && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.subscription_package_id}
                            </span>
                        )}
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Nama pelanggan
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
                        Telepon / WhatsApp
                        <input
                            type="text"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            className={fieldClass}
                        />
                    </label>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Alamat
                    <textarea
                        rows={2}
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                        className={fieldClass}
                    />
                </label>

                <GpsMapPicker
                    latitude={data.latitude}
                    longitude={data.longitude}
                    errors={{
                        latitude: errors.latitude,
                        longitude: errors.longitude,
                    }}
                    onChange={({ latitude, longitude }) => {
                        setData('latitude', latitude);
                        setData('longitude', longitude);
                    }}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Username PPPoE
                        <input
                            type="text"
                            value={data.username}
                            onChange={(e) => setData('username', e.target.value)}
                            className={fieldClass}
                            required
                        />
                        {errors.username && (
                            <span className="mt-1 block text-xs text-red-600">{errors.username}</span>
                        )}
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Password PPPoE
                        <input
                            type="text"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={fieldClass}
                            placeholder={editing ? 'Kosongkan jika tidak diganti' : ''}
                            required={!editing}
                        />
                        {errors.password && (
                            <span className="mt-1 block text-xs text-red-600">{errors.password}</span>
                        )}
                    </label>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Profile layanan (aktif)
                    <select
                        value={data.service_profile || ''}
                        onChange={(e) => setData('service_profile', e.target.value)}
                        className={fieldClass}
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
                    {errors.service_profile && (
                        <span className="mt-1 block text-xs text-red-600">
                            {errors.service_profile}
                        </span>
                    )}
                </label>

                <div className="border border-ink/10 bg-mist/30 p-4 space-y-4">
                    <div>
                        <p className="text-sm font-semibold text-ink">Siklus tagihan</p>
                        <p className="mt-1 text-xs text-ink-soft">
                            Pilih tanggal tetap tiap bulan. Jatuh tempo pertama dan tagihan prorata
                            dihitung otomatis dari tanggal mulai.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <DatePickerField
                            label="Tanggal mulai layanan"
                            value={data.start_date}
                            onChange={(value) => setData('start_date', value)}
                            error={errors.start_date}
                            required
                        />

                        <label className="block text-sm font-medium text-ink">
                            Tanggal jatuh tempo tiap bulan
                            <select
                                value={data.billing_day || ''}
                                onChange={(e) => setData('billing_day', Number(e.target.value))}
                                className={fieldClass}
                                required
                            >
                                {billingDays.map((day) => (
                                    <option key={day} value={day}>
                                        Setiap tanggal {day}
                                    </option>
                                ))}
                            </select>
                            {errors.billing_day && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.billing_day}
                                </span>
                            )}
                        </label>
                    </div>

                    {prorata ? (
                        <div className="border border-signal/20 bg-white px-4 py-3 text-sm text-ink">
                            <div className="grid gap-2 sm:grid-cols-2">
                                <p>
                                    <span className="text-ink-soft">Jatuh tempo pertama:</span>{' '}
                                    <strong>{prorata.due_date}</strong>
                                </p>
                                <p>
                                    <span className="text-ink-soft">Tagihan pertama (prorata):</span>{' '}
                                    <strong>{prorata.amount_label}</strong>
                                </p>
                            </div>
                            <p className="mt-2 text-xs text-ink-soft">{prorata.summary}</p>
                            <p className="mt-1 text-xs text-ink-soft">
                                Nilai dibulatkan ke atas kelipatan Rp 1.000. Bulan berikutnya
                                pelanggan membayar harga penuh paket
                                {selectedPackage ? ` (${selectedPackage.price_label})` : ''}.
                            </p>
                        </div>
                    ) : (
                        <p className="text-xs text-amber-700">
                            Pilih paket langganan untuk melihat hitungan prorata.
                        </p>
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Jika lewat jatuh tempo
                        <select
                            value={data.overdue_action}
                            onChange={(e) => setData('overdue_action', e.target.value)}
                            className={fieldClass}
                            required
                        >
                            {overdue_actions.map((action) => (
                                <option key={action.value} value={action.value}>
                                    {action.label}
                                </option>
                            ))}
                        </select>
                        {errors.overdue_action && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.overdue_action}
                            </span>
                        )}
                    </label>

                    <label className="block text-sm font-medium text-ink">
                        Profile isolir
                        <select
                            value={data.isolir_profile || ''}
                            onChange={(e) => setData('isolir_profile', e.target.value)}
                            className={fieldClass}
                            disabled={data.overdue_action !== 'isolir' || loadingProfiles}
                            required={data.overdue_action === 'isolir'}
                        >
                            <option value="">
                                {data.overdue_action !== 'isolir'
                                    ? 'Tidak dipakai (bypass)'
                                    : isolirProfiles.length
                                      ? 'Pilih profile isolir/expired'
                                      : 'Tidak ada profile isolir/expired'}
                            </option>
                            {isolirProfiles.map((profile) => (
                                <option key={profile.name} value={profile.name}>
                                    {profile.name}
                                </option>
                            ))}
                        </select>
                        {errors.isolir_profile && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.isolir_profile}
                            </span>
                        )}
                    </label>
                </div>

                {profileError && (
                    <div className="border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        {profileError}
                    </div>
                )}

                <label className="block text-sm font-medium text-ink">
                    Catatan
                    <textarea
                        rows={3}
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        className={fieldClass}
                    />
                </label>

                <div>
                    <label className="inline-flex items-center gap-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData('is_active', e.target.checked)}
                        />
                        Pelanggan aktif
                    </label>
                    <p className="mt-1 text-xs text-ink-soft">
                        Uncheck = status <strong>Nonaktif</strong> dan secret PPPoE di MikroTik
                        di-disable. Ini berbeda dari <strong>Isolir</strong> (otomatis saat lewat
                        jatuh tempo).
                    </p>
                </div>

                <div className="rounded-sm border border-ink/10 bg-mist/40 px-4 py-3 text-xs leading-relaxed text-ink-soft">
                    Secret PPPoE ikut dibuat/diperbarui di RouterOS. Jika sudah lewat jatuh tempo dan
                    aksi = Isolir, profile secret diganti ke profile isolir yang dipilih.
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="bg-signal-deep px-5 py-3 text-sm font-bold text-white hover:bg-ink disabled:opacity-60"
                    >
                        {processing
                            ? 'Menyimpan...'
                            : editing
                              ? 'Simpan Perubahan'
                              : 'Simpan Pelanggan'}
                    </button>
                    <Link
                        href="/admin/customers/pppoe"
                        className="border border-ink/15 px-5 py-3 text-sm font-semibold text-ink-soft hover:bg-mist"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
