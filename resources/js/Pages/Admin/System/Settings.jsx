import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Bell, CreditCard, ImagePlus, Settings2, ShieldAlert, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';

const fieldClass =
    'mt-1.5 w-full border border-ink/15 bg-white px-3 py-2.5 text-sm outline-none focus:border-signal disabled:bg-mist disabled:text-ink-soft';

function Section({ icon: Icon, title, description, children }) {
    return (
        <section className="border border-ink/10 bg-white">
            <div className="flex items-start gap-3 border-b border-ink/10 bg-mist/40 px-5 py-4">
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center bg-gradient-to-br from-slate-400 to-slate-600 text-white">
                    <Icon className="h-4 w-4" strokeWidth={1.75} />
                </span>
                <div>
                    <h2 className="text-sm font-semibold text-ink">{title}</h2>
                    <p className="mt-0.5 text-xs text-ink-soft">{description}</p>
                </div>
            </div>
            <div className="space-y-4 p-5 sm:p-6">{children}</div>
        </section>
    );
}

function Toggle({ label, description, checked, disabled, onChange }) {
    return (
        <label className="flex cursor-pointer items-start justify-between gap-4 border border-ink/10 px-4 py-3">
            <span>
                <span className="block text-sm font-medium text-ink">{label}</span>
                {description && (
                    <span className="mt-0.5 block text-xs text-ink-soft">{description}</span>
                )}
            </span>
            <input
                type="checkbox"
                checked={checked}
                disabled={disabled}
                onChange={(e) => onChange(e.target.checked)}
                className="mt-1 h-4 w-4 accent-signal-deep"
            />
        </label>
    );
}

function BrandUpload({
    label,
    hint,
    preview,
    previewClass,
    disabled,
    error,
    onPick,
    onReset,
    canReset,
}) {
    return (
        <div className="border border-ink/10 p-4">
            <div className="flex flex-wrap items-center gap-4">
                <div
                    className={`flex items-center justify-center border border-ink/10 bg-mist/50 ${previewClass}`}
                >
                    {preview ? (
                        <img src={preview} alt={label} className="max-h-full max-w-full object-contain" />
                    ) : (
                        <span className="text-xs text-ink-soft">Belum ada</span>
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-ink">{label}</p>
                    <p className="mt-0.5 text-xs text-ink-soft">{hint}</p>
                    {canWriteButtons(disabled) && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            <label className="inline-flex cursor-pointer items-center gap-2 border border-ink/15 bg-white px-3 py-2 text-xs font-semibold text-ink hover:bg-mist">
                                <ImagePlus className="h-3.5 w-3.5" />
                                Pilih file
                                <input
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={onPick}
                                    disabled={disabled}
                                />
                            </label>
                            {canReset && (
                                <button
                                    type="button"
                                    onClick={onReset}
                                    className="inline-flex items-center gap-1 border border-red-100 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                    Reset default
                                </button>
                            )}
                        </div>
                    )}
                    {error && <span className="mt-2 block text-xs text-red-600">{error}</span>}
                </div>
            </div>
        </div>
    );
}

function canWriteButtons(disabled) {
    return !disabled;
}

export default function Settings({ settings, branding, timezones }) {
    const { auth, app } = usePage().props;
    const canWrite = auth?.user?.can_write !== false;
    const companyName = app?.company_name || '3R Solusi Media';

    const [markPreview, setMarkPreview] = useState(branding?.logo_mark || '');
    const [fullPreview, setFullPreview] = useState(branding?.logo_full || '');
    const [faviconPreview, setFaviconPreview] = useState(branding?.favicon || '');

    const { data, setData, post, processing, errors, transform } = useForm({
        app_timezone: settings.app_timezone || 'Asia/Jakarta',
        app_currency_label: settings.app_currency_label || 'Rp',
        app_invoice_prefix: settings.app_invoice_prefix || 'INV',
        app_billing_generate_days: Number(settings.app_billing_generate_days || 7),
        app_billing_round_to: Number(settings.app_billing_round_to || 1000),
        app_default_billing_day: Number(settings.app_default_billing_day || 1),
        app_notif_whatsapp: settings.app_notif_whatsapp === '1',
        app_notif_email: settings.app_notif_email === '1',
        app_auto_isolir: settings.app_auto_isolir !== '0',
        app_logo_mark: null,
        app_logo_full: null,
        app_favicon: null,
        remove_logo_mark: false,
        remove_logo_full: false,
        remove_favicon: false,
    });

    useEffect(() => {
        if (!(data.app_logo_mark instanceof File)) return undefined;
        const url = URL.createObjectURL(data.app_logo_mark);
        setMarkPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.app_logo_mark]);

    useEffect(() => {
        if (!(data.app_logo_full instanceof File)) return undefined;
        const url = URL.createObjectURL(data.app_logo_full);
        setFullPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.app_logo_full]);

    useEffect(() => {
        if (!(data.app_favicon instanceof File)) return undefined;
        const url = URL.createObjectURL(data.app_favicon);
        setFaviconPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [data.app_favicon]);

    const submit = (e) => {
        e.preventDefault();
        if (!canWrite) return;

        transform((form) => {
            const payload = { ...form };
            ['app_logo_mark', 'app_logo_full', 'app_favicon'].forEach((key) => {
                if (!(payload[key] instanceof File)) delete payload[key];
            });
            ['remove_logo_mark', 'remove_logo_full', 'remove_favicon'].forEach((key) => {
                if (!payload[key]) delete payload[key];
            });
            return payload;
        });

        const hasFile =
            data.app_logo_mark instanceof File ||
            data.app_logo_full instanceof File ||
            data.app_favicon instanceof File ||
            data.remove_logo_mark ||
            data.remove_logo_full ||
            data.remove_favicon;

        post('/admin/system', { forceFormData: hasFile });
    };

    return (
        <AdminLayout
            title="Pengaturan Aplikasi"
            subtitle={`Konfigurasi operasional panel ${companyName}`}
        >
            <Head title="Pengaturan Aplikasi" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Atur logo, preferensi panel, billing, dan notifikasi. Identitas website teks tetap di{' '}
                    <Link href="/admin/settings" className="font-semibold text-signal-deep hover:underline">
                        Pengaturan Situs
                    </Link>
                    .
                </p>
            </div>

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-4">
                <Section
                    icon={ImagePlus}
                    title="Logo & Ikon"
                    description="Logo navbar/website, ikon sidebar admin, dan favicon browser"
                >
                    <BrandUpload
                        label="Logo lengkap"
                        hint="Dipakai di navbar website, footer, dan halaman login. PNG/JPG, maks. 4MB."
                        preview={fullPreview}
                        previewClass="h-16 w-40 px-2"
                        disabled={!canWrite}
                        error={errors.app_logo_full}
                        canReset={Boolean(fullPreview)}
                        onPick={(e) => {
                            setData('app_logo_full', e.target.files?.[0] || null);
                            setData('remove_logo_full', false);
                        }}
                        onReset={() => {
                            setData('app_logo_full', null);
                            setData('remove_logo_full', true);
                            setFullPreview('/images/brand/logo-full.png');
                        }}
                    />
                    <BrandUpload
                        label="Ikon navbar / sidebar"
                        hint="Mark persegi untuk sidebar admin. PNG/JPG, maks. 2MB."
                        preview={markPreview}
                        previewClass="h-16 w-16"
                        disabled={!canWrite}
                        error={errors.app_logo_mark}
                        canReset={Boolean(markPreview)}
                        onPick={(e) => {
                            setData('app_logo_mark', e.target.files?.[0] || null);
                            setData('remove_logo_mark', false);
                        }}
                        onReset={() => {
                            setData('app_logo_mark', null);
                            setData('remove_logo_mark', true);
                            setMarkPreview('/images/brand/logo-mark.png');
                        }}
                    />
                    <BrandUpload
                        label="Favicon"
                        hint="Ikon tab browser. PNG/JPG/ICO, maks. 1MB."
                        preview={faviconPreview}
                        previewClass="h-12 w-12"
                        disabled={!canWrite}
                        error={errors.app_favicon}
                        canReset={Boolean(faviconPreview)}
                        onPick={(e) => {
                            setData('app_favicon', e.target.files?.[0] || null);
                            setData('remove_favicon', false);
                        }}
                        onReset={() => {
                            setData('app_favicon', null);
                            setData('remove_favicon', true);
                            setFaviconPreview('/images/brand/favicon.png');
                        }}
                    />
                </Section>

                <Section
                    icon={Settings2}
                    title="Umum"
                    description="Zona waktu dan label mata uang operasional"
                >
                    <div className="border border-ink/10 bg-mist/40 px-4 py-3 text-sm text-ink-soft">
                        Nama yang tampil di panel admin diambil dari{' '}
                        <span className="font-semibold text-ink">Nama Perusahaan</span> di{' '}
                        <Link href="/admin/settings" className="font-semibold text-signal-deep hover:underline">
                            Pengaturan Situs
                        </Link>
                        : <span className="font-semibold text-ink">{companyName}</span>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            Zona waktu
                            <select
                                value={data.app_timezone}
                                onChange={(e) => setData('app_timezone', e.target.value)}
                                className={fieldClass}
                                disabled={!canWrite}
                            >
                                {timezones.map((tz) => (
                                    <option key={tz} value={tz}>
                                        {tz}
                                    </option>
                                ))}
                            </select>
                            {errors.app_timezone && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.app_timezone}
                                </span>
                            )}
                        </label>

                        <label className="block text-sm font-medium text-ink">
                            Label mata uang
                            <input
                                type="text"
                                value={data.app_currency_label}
                                onChange={(e) => setData('app_currency_label', e.target.value)}
                                className={fieldClass}
                                disabled={!canWrite}
                                required
                            />
                            {errors.app_currency_label && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.app_currency_label}
                                </span>
                            )}
                        </label>
                    </div>
                </Section>

                <Section
                    icon={CreditCard}
                    title="Billing"
                    description="Aturan generate tagihan dan pembulatan"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            Prefiks invoice
                            <input
                                type="text"
                                value={data.app_invoice_prefix}
                                onChange={(e) => setData('app_invoice_prefix', e.target.value)}
                                className={fieldClass}
                                disabled={!canWrite}
                                required
                            />
                            {errors.app_invoice_prefix && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.app_invoice_prefix}
                                </span>
                            )}
                        </label>

                        <label className="block text-sm font-medium text-ink">
                            Tanggal billing default
                            <input
                                type="number"
                                min={1}
                                max={28}
                                value={data.app_default_billing_day}
                                onChange={(e) =>
                                    setData('app_default_billing_day', Number(e.target.value))
                                }
                                className={fieldClass}
                                disabled={!canWrite}
                                required
                            />
                            <span className="mt-1 block text-xs text-ink-soft">
                                Dipakai sebagai saran tanggal jatuh tempo (1–28).
                            </span>
                            {errors.app_default_billing_day && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.app_default_billing_day}
                                </span>
                            )}
                        </label>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block text-sm font-medium text-ink">
                            Jendela generate tagihan (hari)
                            <input
                                type="number"
                                min={1}
                                max={31}
                                value={data.app_billing_generate_days}
                                onChange={(e) =>
                                    setData('app_billing_generate_days', Number(e.target.value))
                                }
                                className={fieldClass}
                                disabled={!canWrite}
                                required
                            />
                            <span className="mt-1 block text-xs text-ink-soft">
                                Tagihan bulanan dibuat jika jatuh tempo dalam N hari ke depan.
                            </span>
                            {errors.app_billing_generate_days && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.app_billing_generate_days}
                                </span>
                            )}
                        </label>

                        <label className="block text-sm font-medium text-ink">
                            Pembulatan prorata (Rp)
                            <input
                                type="number"
                                min={1}
                                step={1}
                                value={data.app_billing_round_to}
                                onChange={(e) =>
                                    setData('app_billing_round_to', Number(e.target.value))
                                }
                                className={fieldClass}
                                disabled={!canWrite}
                                required
                            />
                            <span className="mt-1 block text-xs text-ink-soft">
                                Contoh: 1000 = dibulatkan ke atas ke kelipatan Rp 1.000.
                            </span>
                            {errors.app_billing_round_to && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors.app_billing_round_to}
                                </span>
                            )}
                        </label>
                    </div>
                </Section>

                <Section
                    icon={Bell}
                    title="Notifikasi"
                    description="Preferensi pengingat (siap untuk integrasi berikutnya)"
                >
                    <Toggle
                        label="Notifikasi WhatsApp"
                        description="Siapkan pengingat tagihan/jatuh tempo via WhatsApp."
                        checked={data.app_notif_whatsapp}
                        disabled={!canWrite}
                        onChange={(value) => setData('app_notif_whatsapp', value)}
                    />
                    <Toggle
                        label="Notifikasi Email"
                        description="Siapkan pengingat tagihan via email."
                        checked={data.app_notif_email}
                        disabled={!canWrite}
                        onChange={(value) => setData('app_notif_email', value)}
                    />
                </Section>

                <Section
                    icon={ShieldAlert}
                    title="Operasional jaringan"
                    description="Perilaku isolir dan sinkronisasi pelanggan"
                >
                    <Toggle
                        label="Auto isolir saat overdue"
                        description="Pelanggan dengan aksi isolir dapat disinkronkan ke profile isolir MikroTik."
                        checked={data.app_auto_isolir}
                        disabled={!canWrite}
                        onChange={(value) => setData('app_auto_isolir', value)}
                    />
                </Section>

                {canWrite && (
                    <div className="flex flex-wrap gap-3 pt-1">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-signal-deep px-5 py-2.5 text-sm font-semibold text-white hover:bg-ink disabled:opacity-60"
                        >
                            {processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                        </button>
                    </div>
                )}
            </form>
        </AdminLayout>
    );
}
