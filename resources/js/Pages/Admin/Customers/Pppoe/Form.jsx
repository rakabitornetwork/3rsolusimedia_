import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import DatePickerField from '../../../../Components/Admin/DatePickerField';
import GpsMapPicker from '../../../../Components/Admin/GpsMapPicker';
import AdminLayout from '../../../../Layouts/AdminLayout';
import {
    advanceDueDate,
    alignDueDate,
    billingDayFromDate,
    calculatePackageDelta,
    calculateProrata,
    calculateSpanCharge,
    calculateStopCharge,
    periodStartBeforeDue,
    suggestedDueDate,
} from '../../../../Utils/billingCycle';

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
    prefill = null,
    routers,
    agents = [],
    packages,
    profiles: initialProfiles,
    isolir_profiles: initialIsolirProfiles,
    overdue_actions,
}) {
    const editing = Boolean(customer);
    const fromSession = Boolean(prefill?.from_session) && !editing;
    const initialStart = customer?.start_date || prefill?.start_date || todayIso();
    const initialBillingDay = customer?.billing_day || prefill?.billing_day || 10;
    const initialDue =
        customer?.due_date ||
        prefill?.due_date ||
        suggestedDueDate(initialStart, initialBillingDay);
    const [profiles, setProfiles] = useState(initialProfiles || []);
    const [isolirProfiles, setIsolirProfiles] = useState(initialIsolirProfiles || []);
    const [loadingProfiles, setLoadingProfiles] = useState(false);
    const [profileError, setProfileError] = useState('');

    const { data, setData, post, put, processing, errors } = useForm({
        mikrotik_router_id:
            customer?.mikrotik_router_id ||
            prefill?.mikrotik_router_id ||
            routers[0]?.id ||
            '',
        agent_id: customer?.agent_id || '',
        agent_pays_commission: Boolean(customer?.agent_pays_commission),
        subscription_package_id:
            customer?.subscription_package_id || prefill?.subscription_package_id || '',
        name: customer?.name || prefill?.name || '',
        phone: customer?.phone || '',
        address: customer?.address || '',
        latitude: customer?.latitude ?? '',
        longitude: customer?.longitude ?? '',
        username: customer?.username || prefill?.username || '',
        password: prefill?.password || '',
        service_profile: customer?.service_profile || prefill?.service_profile || '',
        start_date: initialStart,
        due_date: initialDue,
        billing_day: initialDue ? billingDayFromDate(initialDue) : initialBillingDay,
        overdue_action: customer?.overdue_action || prefill?.overdue_action || 'isolir',
        isolir_profile: customer?.isolir_profile || prefill?.isolir_profile || '',
        notes: customer?.notes || (fromSession ? 'Diimpor dari sesi aktif PPPoE' : ''),
        is_active: customer?.is_active ?? true,
        service_change_date: todayIso(),
        stop_date: customer?.stopped_at || todayIso(),
        reactivate_date: todayIso(),
    });

    const routerPackages = useMemo(
        () =>
            packages.filter(
                (pkg) => String(pkg.mikrotik_router_id) === String(data.mikrotik_router_id),
            ),
        [packages, data.mikrotik_router_id],
    );

    const selectedPackage = useMemo(
        () =>
            routerPackages.find(
                (item) => String(item.id) === String(data.subscription_package_id),
            ),
        [routerPackages, data.subscription_package_id],
    );

    const billingInputsChanged =
        !editing ||
        data.start_date !== customer.start_date ||
        data.due_date !== customer.due_date ||
        String(data.subscription_package_id) !== String(customer.subscription_package_id);

    const deferPackageToNextMonth =
        editing &&
        Boolean(customer.package_change_defers_to_next_month) &&
        String(data.subscription_package_id) !== String(customer.subscription_package_id) &&
        data.start_date === customer.start_date;

    const packageChanged =
        editing &&
        String(data.subscription_package_id) !== String(customer.subscription_package_id);
    const dueChanged = editing && data.due_date !== customer.due_date;
    const midCyclePackageChange =
        packageChanged &&
        !deferPackageToNextMonth &&
        Boolean(customer.has_paid_invoice) &&
        customer.due_date > todayIso();
    const billingDateChange = dueChanged && !packageChanged && Boolean(customer.has_paid_invoice);
    const stopping = editing && customer.is_active && !data.is_active;
    const reactivating = editing && !customer.is_active && data.is_active;

    const prorata = useMemo(() => {
        if (stopping && customer.due_date && selectedPackage) {
            const periodStart = periodStartBeforeDue(customer.due_date, customer.billing_day);
            const settlement = calculateStopCharge(
                periodStart,
                customer.due_date,
                data.stop_date,
                selectedPackage.price,
            );
            if (settlement) {
                return {
                    due_date: data.stop_date,
                    amount_label: settlement.amount_label,
                    due_label: 'Berhenti pada:',
                    amount_label_title: 'Tagihan pemakaian:',
                    summary: settlement.summary,
                };
            }
        }

        if (reactivating && data.reactivate_date && data.due_date && selectedPackage) {
            const reactivation = calculateProrata(
                data.reactivate_date,
                data.billing_day,
                selectedPackage.price,
                data.due_date,
            );
            if (reactivation) {
                return {
                    ...reactivation,
                    due_label: 'Jatuh tempo:',
                    amount_label_title: 'Prorata aktivasi kembali:',
                    summary: `Prorata dari ${data.reactivate_date} sampai jatuh tempo. ${reactivation.summary}`,
                };
            }
        }

        if (deferPackageToNextMonth && selectedPackage && customer.due_date) {
            const today = todayIso();
            const day = billingDayFromDate(data.due_date || customer.due_date);
            let nextDue = data.due_date && data.due_date > today ? alignDueDate(data.due_date) : null;
            if (!nextDue || nextDue <= today) {
                nextDue = advanceDueDate(customer.due_date, day);
                if (nextDue && nextDue <= today) {
                    nextDue = advanceDueDate(today, day);
                }
            }

            return {
                due_date: nextDue,
                amount_label: selectedPackage.price_label,
                days: null,
                package_change: true,
                summary:
                    'Paket diganti. Tagihan bulan berikutnya memakai harga penuh paket baru. Tanggal mulai layanan pada bulan sebelumnya tidak dihitung, dan tagihan jatuh tempo yang masih terbuka diganti. Sesi PPPoE diputus agar profile baru langsung dipakai.',
            };
        }

        if (midCyclePackageChange && selectedPackage && customer.due_date && !dueChanged) {
            const periodStart = periodStartBeforeDue(customer.due_date, customer.billing_day);
            const delta = calculatePackageDelta(
                periodStart,
                customer.due_date,
                data.service_change_date,
                customer.package?.price,
                selectedPackage.price,
            );
            if (delta) {
                return {
                    due_date: customer.due_date,
                    amount_label: delta.amount_label,
                    due_label: 'Jatuh tempo siklus ini:',
                    amount_label_title: delta.direction === 'credit' ? 'Kredit:' : 'Tagihan selisih:',
                    summary: `${delta.summary} Profile RouterOS diganti saat disimpan dan sesi yang sedang tersambung diputus.`,
                };
            }
        }

        if (billingDateChange && selectedPackage && customer.last_paid_due_date) {
            const moved = calculateSpanCharge(
                customer.last_paid_due_date,
                data.due_date,
                selectedPackage.price,
            );
            if (moved) {
                return {
                    due_date: moved.due_date,
                    amount_label: moved.amount_label,
                    due_label: 'Jatuh tempo baru:',
                    amount_label_title: 'Tagihan dari bulan lunas:',
                    summary: `${moved.summary} Tagihan awal pendaftaran tidak dihitung.`,
                };
            }
        }

        if (
            editing &&
            !billingInputsChanged &&
            customer.first_bill_amount != null &&
            customer.due_date
        ) {
            return {
                due_date: customer.due_date,
                amount_label: customer.first_bill_amount_label,
                days: customer.first_bill_days,
                stored: true,
            };
        }

        if (!data.start_date || !data.due_date || !selectedPackage) return null;
        return calculateProrata(
            data.start_date,
            data.billing_day,
            selectedPackage.price,
            data.due_date,
        );
    }, [
        editing,
        billingInputsChanged,
        deferPackageToNextMonth,
        midCyclePackageChange,
        billingDateChange,
        stopping,
        reactivating,
        dueChanged,
        customer,
        data.start_date,
        data.billing_day,
        data.due_date,
        data.subscription_package_id,
        data.service_change_date,
        data.stop_date,
        data.reactivate_date,
        selectedPackage,
    ]);

    const applyStartDate = (value) => {
        setData((current) => {
            const next = { ...current, start_date: value };
            if (!editing || !current.due_date || current.due_date <= value) {
                const suggested = suggestedDueDate(value, current.billing_day);
                next.due_date = suggested;
                next.billing_day = suggested ? billingDayFromDate(suggested) : current.billing_day;
            }
            return next;
        });
    };

    const applyDueDate = (value) => {
        const aligned = alignDueDate(value);
        setData((current) => ({
            ...current,
            due_date: aligned,
            billing_day: billingDayFromDate(aligned),
        }));
    };

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

            {editing && customer?.monthly_usage && (
                <section className="mb-4 max-w-3xl border border-ink/10 bg-white p-4 sm:p-5">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h3 className="text-sm font-semibold text-ink">
                            Pemakaian {customer.monthly_usage.period_label}
                        </h3>
                        <p className="text-xs text-ink-soft">{customer.monthly_usage.reset_label}</p>
                    </div>
                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        <div className="border border-sky-200/80 bg-sky-50/70 px-3 py-2">
                            <p className="text-[11px] font-semibold tracking-wide text-sky-800 uppercase">
                                Download (RX)
                            </p>
                            <p className="mt-1 text-lg font-semibold text-sky-950">
                                {customer.monthly_usage.rx_label}
                            </p>
                        </div>
                        <div className="border border-orange-200/80 bg-orange-50/70 px-3 py-2">
                            <p className="text-[11px] font-semibold tracking-wide text-orange-800 uppercase">
                                Upload (TX)
                            </p>
                            <p className="mt-1 text-lg font-semibold text-orange-950">
                                {customer.monthly_usage.tx_label}
                            </p>
                        </div>
                        <div className="border border-ink/10 bg-mist/40 px-3 py-2">
                            <p className="text-[11px] font-semibold tracking-wide text-ink-soft uppercase">
                                Total
                            </p>
                            <p className="mt-1 text-lg font-semibold text-ink">
                                {customer.monthly_usage.total_label}
                            </p>
                        </div>
                    </div>
                    <p className="mt-3 text-xs text-ink-soft">
                        {customer.monthly_usage.has_sample
                            ? `Terakhir dibaca ${customer.monthly_usage.sampled_at || '—'}. `
                            : 'Belum ada pembacaan. '}
                        Angka bertambah tiap 5 menit selama pelanggan online, lalu mulai dari nol
                        pada tanggal 1.
                    </p>
                </section>
            )}

            {fromSession && (
                <div className="mb-4 border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                    Data diisi dari sesi aktif
                    {prefill?.secret_found
                        ? ' + secret MikroTik (password & profil ikut terisi).'
                        : ' (secret belum terbaca — isi password & paket manual).'}{' '}
                    Lengkapi paket/billing bila perlu, lalu simpan.
                </div>
            )}

            <form
                onSubmit={submit}
                className="max-w-3xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Router
                        <select
                            value={data.mikrotik_router_id}
                            onChange={(e) => {
                                const routerId = e.target.value;
                                setData((current) => {
                                    const packageStillValid = packages.some(
                                        (pkg) =>
                                            String(pkg.id) ===
                                                String(current.subscription_package_id) &&
                                            String(pkg.mikrotik_router_id) === String(routerId),
                                    );

                                    return {
                                        ...current,
                                        mikrotik_router_id: routerId,
                                        subscription_package_id: packageStillValid
                                            ? current.subscription_package_id
                                            : '',
                                        service_profile: packageStillValid
                                            ? current.service_profile
                                            : '',
                                        isolir_profile: '',
                                    };
                                });
                            }}
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
                            disabled={!data.mikrotik_router_id}
                        >
                            <option value="">
                                {!data.mikrotik_router_id
                                    ? 'Pilih router dulu'
                                    : routerPackages.length
                                      ? 'Pilih paket'
                                      : 'Tidak ada paket untuk router ini'}
                            </option>
                            {routerPackages.map((pkg) => (
                                <option key={pkg.id} value={pkg.id}>
                                    {pkg.name} — {pkg.price_label}
                                </option>
                            ))}
                        </select>
                        {data.mikrotik_router_id && routerPackages.length === 0 && (
                            <span className="mt-1 block text-xs text-amber-700">
                                Belum ada paket langganan untuk router ini.{' '}
                                <Link
                                    href={`/admin/customers/pppoe/service-profiles/create?router_id=${data.mikrotik_router_id}`}
                                    className="font-semibold underline"
                                >
                                    Tambah paket
                                </Link>
                            </span>
                        )}
                        {errors.subscription_package_id && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.subscription_package_id}
                            </span>
                        )}
                    </label>
                </div>

                {midCyclePackageChange && (
                    <DatePickerField
                        label="Tanggal ganti layanan"
                        value={data.service_change_date}
                        onChange={(value) => setData('service_change_date', value)}
                        error={errors.service_change_date}
                    />
                )}

                {agents.length > 0 && (
                    <div className="space-y-3">
                        <label className="block text-sm font-medium text-ink">
                            Agen Penanggung Jawab <span className="font-normal text-ink-soft">(opsional)</span>
                            <select
                                value={data.agent_id || ''}
                                onChange={(e) => {
                                    const next = e.target.value;
                                    setData({
                                        ...data,
                                        agent_id: next,
                                        agent_pays_commission: next
                                            ? data.agent_pays_commission
                                            : false,
                                    });
                                }}
                                className={fieldClass}
                            >
                                <option value="">Tanpa agen (dikelola admin/superadmin)</option>
                                {agents.map((ag) => (
                                    <option key={ag.id} value={ag.id}>
                                        {ag.name}
                                    </option>
                                ))}
                            </select>
                            {errors.agent_id && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.agent_id}
                                </span>
                            )}
                        </label>
                        {data.agent_id ? (
                            <label className="flex cursor-pointer items-start gap-2 text-sm text-ink">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.agent_pays_commission)}
                                    onChange={(e) => setData('agent_pays_commission', e.target.checked)}
                                    className="mt-0.5 h-4 w-4 rounded border-ink/20 text-signal focus:ring-signal"
                                />
                                <span>
                                    <span className="font-medium">Agen mendapat komisi dari pelanggan ini</span>
                                    <span className="mt-0.5 block text-xs font-normal text-ink-soft">
                                        Kosongkan jika agen hanya menugaskan/mengelola, tanpa komisi tagihan.
                                    </span>
                                </span>
                            </label>
                        ) : null}
                    </div>
                )}

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
                            Pilih tanggal jatuh tempo pertama secara lengkap (hari, bulan, tahun).
                            Tanggal yang sama dipakai setiap bulan berikutnya. Tagihan pertama
                            dihitung prorata dari tanggal mulai sampai jatuh tempo.
                        </p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <DatePickerField
                            label="Tanggal mulai layanan"
                            value={data.start_date}
                            onChange={applyStartDate}
                            error={errors.start_date}
                            required
                        />

                        <DatePickerField
                            label="Tanggal jatuh tempo tiap bulan"
                            value={data.due_date}
                            onChange={applyDueDate}
                            error={errors.due_date || errors.billing_day}
                            required
                        />
                    </div>
                    <p className="text-xs text-ink-soft">
                        Contoh: mulai 20 Agustus 2026 dan jatuh tempo 20 September 2026. Tagihan
                        berikutnya setiap tanggal {data.billing_day || '—'}. Tanggal 29–31 disimpan
                        sebagai tanggal 28.
                    </p>

                    {prorata ? (
                        <div className="border border-signal/20 bg-white px-4 py-3 text-sm text-ink">
                            <div className="grid gap-2 sm:grid-cols-2">
                                <p>
                                    <span className="text-ink-soft">
                                        {prorata.due_label ||
                                            (prorata.package_change
                                                ? 'Jatuh tempo bulan berikutnya:'
                                                : 'Jatuh tempo pertama:')}
                                    </span>{' '}
                                    <strong>{prorata.due_date}</strong>
                                </p>
                                <p>
                                    <span className="text-ink-soft">
                                        {prorata.amount_label_title ||
                                            (prorata.package_change
                                                ? 'Tagihan paket baru:'
                                                : 'Tagihan pertama (prorata):')}
                                    </span>{' '}
                                    <strong>{prorata.amount_label}</strong>
                                </p>
                            </div>
                            <p className="mt-2 text-xs text-ink-soft">
                                {prorata.package_change
                                    ? prorata.summary
                                    : prorata.stored
                                      ? `Prorata tersimpan (${prorata.days ?? '—'} hari). Nominal ini tidak dihitung ulang saat catatan atau data lain disimpan.`
                                      : prorata.summary}
                            </p>
                            {!prorata.package_change && !prorata.due_label && (
                                <p className="mt-1 text-xs text-ink-soft">
                                    Nilai dibulatkan ke atas kelipatan Rp 1.000. Bulan berikutnya
                                    pelanggan membayar harga penuh paket
                                    {selectedPackage ? ` (${selectedPackage.price_label})` : ''}.
                                </p>
                            )}
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

                {editing && customer.has_active_grace && (
                    <div className="border border-sky-100 bg-sky-50/60 px-4 py-3 text-xs text-sky-800">
                        Grace aktif s/d {customer.grace_until}
                        {customer.grace_note ? ` — ${customer.grace_note}` : ''}. Aksi toleransi &amp;
                        gabung 2 bulan ada di Tagihan &amp; Pembayaran.
                    </div>
                )}

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
                            onChange={(e) => {
                                const checked = e.target.checked;
                                setData((current) => {
                                    const next = { ...current, is_active: checked };
                                    if (checked && customer && !customer.is_active) {
                                        const from = current.reactivate_date || todayIso();
                                        if (!current.due_date || current.due_date <= from) {
                                            const suggested = suggestedDueDate(from, current.billing_day);
                                            next.due_date = suggested;
                                            next.billing_day = suggested
                                                ? billingDayFromDate(suggested)
                                                : current.billing_day;
                                        }
                                    }
                                    return next;
                                });
                            }}
                        />
                        Pelanggan aktif
                    </label>
                    <p className="mt-1 text-xs text-ink-soft">
                        Uncheck = status <strong>Nonaktif</strong> dan secret PPPoE di MikroTik
                        di-disable. Ini berbeda dari <strong>Isolir</strong> (otomatis saat lewat
                        jatuh tempo).
                    </p>
                    {stopping && (
                        <div className="mt-3">
                            <DatePickerField
                                label="Tanggal berhenti"
                                value={data.stop_date}
                                onChange={(value) => setData('stop_date', value)}
                                error={errors.stop_date}
                            />
                            <p className="mt-1 text-xs text-ink-soft">
                                Tagihan dihitung sampai tanggal ini jika masih sebelum jatuh tempo.
                                Secret PPPoE dinonaktifkan.
                            </p>
                        </div>
                    )}
                    {reactivating && (
                        <div className="mt-3">
                            <DatePickerField
                                label="Tanggal aktif kembali"
                                value={data.reactivate_date}
                                onChange={(value) => setData('reactivate_date', value)}
                                error={errors.reactivate_date}
                            />
                            <p className="mt-1 text-xs text-ink-soft">
                                Prorata dihitung dari tanggal ini sampai jatuh tempo yang dipilih.
                            </p>
                        </div>
                    )}
                    {editing && Number(customer.billing_credit) > 0 && (
                        <p className="mt-2 text-xs text-ink-soft">
                            Kredit tagihan tersimpan: <strong>{customer.billing_credit_label}</strong>
                        </p>
                    )}
                </div>

                <div className="rounded-sm border border-ink/10 bg-mist/40 px-4 py-3 text-xs leading-relaxed text-ink-soft">
                    Secret PPPoE ikut dibuat/diperbarui di RouterOS. Jika sudah lewat jatuh tempo dan
                    aksi = Isolir, profile secret diganti ke profile isolir yang dipilih.
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        {processing
                            ? 'Menyimpan...'
                            : editing
                              ? 'Simpan Perubahan'
                              : 'Simpan Pelanggan'}
                    </button>
                    <Link
                        href="/admin/customers/pppoe"
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </AdminLayout>
    );
}
