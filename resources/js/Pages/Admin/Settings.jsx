import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '../../Layouts/AdminLayout';

const fields = [
    ['company_name', 'Nama Perusahaan'],
    ['tagline', 'Tagline'],
    ['phone', 'Telepon'],
    ['whatsapp', 'WhatsApp (62...)'],
    ['email', 'Email'],
    ['address', 'Alamat'],
    ['operating_hours', 'Jam Operasional'],
    ['instagram', 'Instagram URL'],
    ['facebook', 'Facebook URL'],
    ['seo_title', 'SEO Title'],
    ['seo_description', 'SEO Description'],
];

export default function Settings({ settings }) {
    const initial = Object.fromEntries(fields.map(([key]) => [key, settings[key] || '']));

    const { data, setData, post, processing, errors } = useForm(initial);

    const submit = (e) => {
        e.preventDefault();
        post('/admin/settings');
    };

    return (
        <AdminLayout
            title="Pengaturan Situs"
            subtitle="Data kontak, identitas brand, dan SEO website"
        >
            <Head title="Pengaturan Situs" />

            <form
                onSubmit={submit}
                className="max-w-3xl space-y-4 border border-ink/10 bg-white p-6 sm:p-8"
            >
                {fields.map(([key, label]) => (
                    <label key={key} className="block text-sm font-medium text-ink">
                        {label}
                        {key === 'seo_description' || key === 'address' ? (
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
                        {errors[key] && (
                            <span className="mt-1 block text-xs text-red-600">{errors[key]}</span>
                        )}
                    </label>
                ))}

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-signal-deep px-5 py-3 text-sm font-bold text-white hover:bg-ink disabled:opacity-60"
                >
                    {processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                </button>
            </form>
        </AdminLayout>
    );
}
