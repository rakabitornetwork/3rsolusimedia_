import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';

const fields = [
    ['company_name', 'Nama Perusahaan', 'Tampil di situs, Google, dan panel admin.'],
    ['tagline', 'Tagline', 'Kalimat singkat identitas bisnis. Dipakai jika SEO Title kosong.'],
    ['phone', 'Telepon', 'Nomor yang bisa dihubungi pelanggan.'],
    ['whatsapp', 'WhatsApp (62...)', 'Format internasional, contoh: 62812xxxxxxxx'],
    ['email', 'Email', 'Email publik untuk pelanggan.'],
    ['address', 'Alamat', 'Alamat usaha — membantu Google menampilkan bisnis lokal.'],
    ['operating_hours', 'Jam Operasional', 'Contoh: Senin – Sabtu, 08.00 – 18.00'],
    ['instagram', 'Instagram URL', 'URL lengkap, contoh: https://instagram.com/teslatech'],
    ['facebook', 'Facebook URL', 'URL lengkap halaman Facebook.'],
];

function GooglePreview({ title, description, url }) {
    const displayTitle = title || 'Judul belum diisi';
    const displayDescription =
        description || 'Deskripsi belum diisi. Google akan mengambil teks acak dari halaman.';
    const host = url.replace(/^https?:\/\//, '').replace(/\/$/, '');

    return (
        <div className="border border-ink/10 bg-white p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-ink-soft">
                Pratinjau Google
            </p>
            <div className="mt-3 max-w-xl">
                <p className="truncate text-sm text-[#202124]">{host}</p>
                <p className="mt-1 text-xl leading-snug text-[#1a0dab]">{displayTitle}</p>
                <p className="mt-1 text-sm leading-relaxed text-[#4d5156]">{displayDescription}</p>
            </div>
        </div>
    );
}

export default function Settings({ settings }) {
    const initial = Object.fromEntries([
        ...fields.map(([key]) => [key, settings[key] || '']),
        ['seo_title', settings.seo_title || ''],
        ['seo_description', settings.seo_description || ''],
    ]);

    const { data, setData, post, processing, errors } = useForm(initial);
    const titleLen = (data.seo_title || '').length;
    const descLen = (data.seo_description || '').length;

    const submit = (e) => {
        e.preventDefault();
        post('/admin/settings');
    };

    return (
        <AdminLayout
            title="Pengaturan Situs"
            subtitle="Data kontak, identitas brand, dan tampilan website di Google"
        >
            <Head title="Pengaturan Situs" />

            <form
                onSubmit={submit}
                className="max-w-3xl space-y-6"
            >
                <div className="space-y-4 border border-ink/10 bg-white p-6 sm:p-8">
                    <div>
                        <h2 className="text-sm font-semibold text-ink">Identitas & kontak</h2>
                        <p className="mt-1 text-xs text-ink-soft">
                            Data ini dipakai di landing page, WhatsApp, dan data bisnis untuk Google.
                        </p>
                    </div>

                    {fields.map(([key, label, hint]) => (
                        <label key={key} className="block text-sm font-medium text-ink">
                            {label}
                            {key === 'address' ? (
                                <textarea
                                    rows={3}
                                    value={data[key]}
                                    onChange={(e) => setData(key, e.target.value)}
                                    className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                                />
                            ) : (
                                <input
                                    type="text"
                                    value={data[key]}
                                    onChange={(e) => setData(key, e.target.value)}
                                    className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                                />
                            )}
                            {hint && (
                                <span className="mt-1 block text-xs font-normal text-ink-soft">
                                    {hint}
                                </span>
                            )}
                            {errors[key] && (
                                <span className="mt-1 block text-xs text-red-600">
                                    {errors[key]}
                                </span>
                            )}
                        </label>
                    ))}
                </div>

                <div className="space-y-4 border border-ink/10 bg-white p-6 sm:p-8">
                    <div>
                        <h2 className="text-sm font-semibold text-ink">Tampilan di Google</h2>
                        <p className="mt-1 text-xs text-ink-soft">
                            Judul sekitar 50–60 karakter. Deskripsi sekitar 150–160 karakter. Ini yang
                            muncul sebagai cuplikan pencarian, bukan URL mentah.
                        </p>
                    </div>

                    <label className="block text-sm font-medium text-ink">
                        SEO Title
                        <input
                            type="text"
                            value={data.seo_title}
                            onChange={(e) => setData('seo_title', e.target.value)}
                            maxLength={70}
                            placeholder="Tesla Tech — Internet & WiFi Rumahan Profesional"
                            className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                        />
                        <span
                            className={`mt-1 block text-xs font-normal ${
                                titleLen > 60 ? 'text-amber-700' : 'text-ink-soft'
                            }`}
                        >
                            {titleLen}/60 karakter disarankan
                        </span>
                        {errors.seo_title && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.seo_title}
                            </span>
                        )}
                    </label>

                    <label className="block text-sm font-medium text-ink">
                        SEO Description
                        <textarea
                            rows={4}
                            value={data.seo_description}
                            onChange={(e) => setData('seo_description', e.target.value)}
                            maxLength={180}
                            placeholder="Tesla Tech menyediakan internet rumah dan pemasangan WiFi yang rapi, cepat, dan stabil. Konsultasi gratis, instalasi profesional, dan dukungan after-sales."
                            className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                        />
                        <span
                            className={`mt-1 block text-xs font-normal ${
                                descLen > 160 ? 'text-amber-700' : 'text-ink-soft'
                            }`}
                        >
                            {descLen}/160 karakter disarankan
                        </span>
                        {errors.seo_description && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.seo_description}
                            </span>
                        )}
                    </label>

                    <GooglePreview
                        title={data.seo_title || data.company_name}
                        description={data.seo_description || data.tagline}
                        url="https://teslatech.my.id"
                    />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="btn-action btn-action-sm btn-primary"
                >
                    {processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                </button>
            </form>
        </AdminLayout>
    );
}
