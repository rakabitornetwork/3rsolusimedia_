import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PricingContentEditor from '../../Components/Admin/PricingContentEditor';
import AdminLayout from '../../Layouts/AdminLayout';

export default function SectionEdit({ section }) {
    const isPricing = section.key === 'pricing';
    const [showRawJson, setShowRawJson] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        title: section.title || '',
        subtitle: section.subtitle || '',
        body: section.body || '',
        content_json: JSON.stringify(section.content || {}, null, 2),
        cta_label: section.cta_label || '',
        cta_url: section.cta_url || '',
        is_visible: section.is_visible,
        sort_order: section.sort_order,
        image: null,
        image_secondary: null,
        remove_image: false,
        remove_image_secondary: false,
        _method: 'post',
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/admin/sections/${section.id}`, {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout
            title={`Edit: ${section.label}`}
            subtitle="Perbarui konten section landing page"
        >
            <Head title={`Edit ${section.label}`} />

            <form onSubmit={submit} className="space-y-6 border border-ink/10 bg-white p-6 sm:p-8">
                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Subtitle / Eyebrow
                        <input
                            type="text"
                            value={data.subtitle}
                            onChange={(e) => setData('subtitle', e.target.value)}
                            className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        Judul
                        <input
                            type="text"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                        />
                    </label>
                </div>

                <label className="block text-sm font-medium text-ink">
                    Deskripsi / Body
                    <textarea
                        rows={4}
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                    />
                </label>

                {isPricing ? (
                    <div className="space-y-3 border border-ink/10 bg-paper/60 p-4 sm:p-5">
                        <PricingContentEditor
                            value={data.content_json}
                            onChange={(next) => setData('content_json', next)}
                            error={errors.content_json}
                        />
                        <button
                            type="button"
                            onClick={() => setShowRawJson((v) => !v)}
                            className="text-xs font-semibold text-signal-deep hover:underline"
                        >
                            {showRawJson ? 'Sembunyikan JSON lanjutan' : 'Tampilkan JSON lanjutan'}
                        </button>
                        {showRawJson && (
                            <textarea
                                rows={12}
                                value={data.content_json}
                                onChange={(e) => setData('content_json', e.target.value)}
                                className="w-full border border-ink/15 bg-mist/40 px-4 py-3 font-mono text-xs outline-none focus:border-signal"
                                spellCheck={false}
                            />
                        )}
                    </div>
                ) : (
                    <label className="block text-sm font-medium text-ink">
                        Konten JSON (items, steps, stats, dll.)
                        <textarea
                            rows={14}
                            value={data.content_json}
                            onChange={(e) => setData('content_json', e.target.value)}
                            className="mt-2 w-full border border-ink/15 bg-mist/40 px-4 py-3 font-mono text-xs outline-none focus:border-signal"
                            spellCheck={false}
                        />
                        {errors.content_json && (
                            <span className="mt-1 block text-xs text-red-600">
                                {errors.content_json}
                            </span>
                        )}
                    </label>
                )}

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        CTA Label
                        <input
                            type="text"
                            value={data.cta_label}
                            onChange={(e) => setData('cta_label', e.target.value)}
                            className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                        />
                    </label>
                    <label className="block text-sm font-medium text-ink">
                        CTA URL
                        <input
                            type="text"
                            value={data.cta_url}
                            onChange={(e) => setData('cta_url', e.target.value)}
                            className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                            placeholder="#kontak / whatsapp / https://..."
                        />
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <label className="block text-sm font-medium text-ink">
                        Urutan
                        <input
                            type="number"
                            value={data.sort_order}
                            onChange={(e) => setData('sort_order', Number(e.target.value))}
                            className="mt-2 w-full border border-ink/15 px-4 py-3 outline-none focus:border-signal"
                        />
                    </label>
                    <label className="flex items-center gap-3 pt-8 text-sm font-medium text-ink">
                        <input
                            type="checkbox"
                            checked={data.is_visible}
                            onChange={(e) => setData('is_visible', e.target.checked)}
                        />
                        Tampilkan di landing page
                    </label>
                </div>

                <div className="grid gap-6 sm:grid-cols-2">
                    <div>
                        <p className="text-sm font-medium text-ink">Gambar utama</p>
                        {section.image && (
                            <img
                                src={section.image}
                                alt=""
                                className="mt-3 aspect-video w-full object-cover"
                            />
                        )}
                        <input
                            type="file"
                            accept="image/*"
                            className="mt-3 block w-full text-sm"
                            onChange={(e) => setData('image', e.target.files?.[0] || null)}
                        />
                        {section.image && (
                            <label className="mt-2 flex items-center gap-2 text-xs text-ink-soft">
                                <input
                                    type="checkbox"
                                    checked={data.remove_image}
                                    onChange={(e) => setData('remove_image', e.target.checked)}
                                />
                                Hapus gambar utama
                            </label>
                        )}
                    </div>
                    <div>
                        <p className="text-sm font-medium text-ink">Gambar sekunder</p>
                        {section.image_secondary && (
                            <img
                                src={section.image_secondary}
                                alt=""
                                className="mt-3 aspect-video w-full object-cover"
                            />
                        )}
                        <input
                            type="file"
                            accept="image/*"
                            className="mt-3 block w-full text-sm"
                            onChange={(e) =>
                                setData('image_secondary', e.target.files?.[0] || null)
                            }
                        />
                        {section.image_secondary && (
                            <label className="mt-2 flex items-center gap-2 text-xs text-ink-soft">
                                <input
                                    type="checkbox"
                                    checked={data.remove_image_secondary}
                                    onChange={(e) =>
                                        setData('remove_image_secondary', e.target.checked)
                                    }
                                />
                                Hapus gambar sekunder
                            </label>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </button>
                    <a
                        href="/admin/website/sections"
                        className="btn-action btn-action-sm btn-secondary"
                    >
                        Kembali
                    </a>
                </div>
            </form>
        </AdminLayout>
    );
}
