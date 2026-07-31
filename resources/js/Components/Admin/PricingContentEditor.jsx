import { Plus, Trash2 } from 'lucide-react';

const emptyPlan = () => ({
    name: '',
    badge: '',
    featured: false,
    description: '',
    price: '',
    period: '',
    features: [''],
    cta_label: 'Pilih Paket',
    cta_url: 'whatsapp',
});

function parseContent(contentJson) {
    try {
        const parsed = JSON.parse(contentJson || '{}');
        return {
            note: parsed.note || '',
            plans: Array.isArray(parsed.plans)
                ? parsed.plans.map((plan) => ({
                      name: plan.name || '',
                      badge: plan.badge || '',
                      featured: Boolean(plan.featured),
                      description: plan.description || '',
                      price: plan.price || '',
                      period: plan.period || '',
                      features:
                          Array.isArray(plan.features) && plan.features.length
                              ? plan.features
                              : [''],
                      cta_label: plan.cta_label || 'Pilih Paket',
                      cta_url: plan.cta_url || 'whatsapp',
                  }))
                : [emptyPlan()],
        };
    } catch {
        return { note: '', plans: [emptyPlan()] };
    }
}

function toContentJson({ note, plans }) {
    return JSON.stringify(
        {
            note: note.trim(),
            plans: plans.map((plan) => ({
                name: plan.name.trim(),
                badge: plan.badge.trim() || null,
                featured: Boolean(plan.featured),
                description: plan.description.trim(),
                price: plan.price.trim(),
                period: plan.period.trim(),
                features: plan.features.map((f) => f.trim()).filter(Boolean),
                cta_label: plan.cta_label.trim() || 'Pilih Paket',
                cta_url: plan.cta_url.trim() || 'whatsapp',
            })),
        },
        null,
        2,
    );
}

const fieldClass =
    'mt-1.5 w-full border border-ink/15 px-3 py-2.5 text-sm outline-none focus:border-signal';

export default function PricingContentEditor({ value, onChange, error }) {
    const content = parseContent(value);

    const sync = (next) => {
        onChange(toContentJson(next));
    };

    const updateNote = (note) => sync({ ...content, note });

    const updatePlan = (index, patch) => {
        const plans = content.plans.map((plan, i) =>
            i === index ? { ...plan, ...patch } : plan,
        );
        sync({ ...content, plans });
    };

    const updateFeaturesText = (index, text) => {
        updatePlan(index, { features: text.split('\n') });
    };

    const addPlan = () => sync({ ...content, plans: [...content.plans, emptyPlan()] });

    const removePlan = (index) => {
        if (content.plans.length <= 1) return;
        sync({
            ...content,
            plans: content.plans.filter((_, i) => i !== index),
        });
    };

    const setFeatured = (index) => {
        const plans = content.plans.map((plan, i) => ({
            ...plan,
            featured: i === index,
        }));
        sync({ ...content, plans });
    };

    return (
        <div className="space-y-5">
            <div>
                <h3 className="font-display text-base font-bold text-ink">
                    Paket Harga & Fitur Langganan
                </h3>
                <p className="mt-1 text-sm text-ink-soft">
                    Ubah isi paket lewat form di bawah. Setiap baris pada kolom fitur = 1 fitur.
                </p>
            </div>

            <label className="block text-sm font-medium text-ink">
                Catatan di bawah paket
                <textarea
                    rows={2}
                    value={content.note}
                    onChange={(e) => updateNote(e.target.value)}
                    className={fieldClass}
                    placeholder="Contoh: Harga dapat menyesuaikan kondisi lokasi..."
                />
            </label>

            <div className="space-y-4">
                {content.plans.map((plan, index) => (
                    <div key={index} className="border border-ink/10 bg-mist/30 p-4 sm:p-5">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <p className="font-display text-sm font-bold text-ink">
                                Paket {index + 1}
                                {plan.name ? ` — ${plan.name}` : ''}
                            </p>
                            <div className="flex items-center gap-3">
                                <label className="flex items-center gap-2 text-xs font-medium text-ink-soft">
                                    <input
                                        type="radio"
                                        name="featured_plan"
                                        checked={plan.featured}
                                        onChange={() => setFeatured(index)}
                                    />
                                    Jadikan unggulan
                                </label>
                                {content.plans.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => removePlan(index)}
                                        className="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:underline"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                        Hapus
                                    </button>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="block text-sm font-medium text-ink">
                                Nama paket
                                <input
                                    type="text"
                                    value={plan.name}
                                    onChange={(e) => updatePlan(index, { name: e.target.value })}
                                    className={fieldClass}
                                    placeholder="Family"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Badge (opsional)
                                <input
                                    type="text"
                                    value={plan.badge}
                                    onChange={(e) => updatePlan(index, { badge: e.target.value })}
                                    className={fieldClass}
                                    placeholder="Terlaris / Langganan"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink sm:col-span-2">
                                Deskripsi singkat
                                <input
                                    type="text"
                                    value={plan.description}
                                    onChange={(e) =>
                                        updatePlan(index, { description: e.target.value })
                                    }
                                    className={fieldClass}
                                    placeholder="Untuk keluarga dengan banyak perangkat"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Harga
                                <input
                                    type="text"
                                    value={plan.price}
                                    onChange={(e) => updatePlan(index, { price: e.target.value })}
                                    className={fieldClass}
                                    placeholder="Rp 1,5jt"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Periode / keterangan harga
                                <input
                                    type="text"
                                    value={plan.period}
                                    onChange={(e) => updatePlan(index, { period: e.target.value })}
                                    className={fieldClass}
                                    placeholder="sekali pasang /bulan"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Teks tombol
                                <input
                                    type="text"
                                    value={plan.cta_label}
                                    onChange={(e) =>
                                        updatePlan(index, { cta_label: e.target.value })
                                    }
                                    className={fieldClass}
                                    placeholder="Pilih Family"
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink">
                                Link tombol
                                <input
                                    type="text"
                                    value={plan.cta_url}
                                    onChange={(e) =>
                                        updatePlan(index, { cta_url: e.target.value })
                                    }
                                    className={fieldClass}
                                    placeholder="whatsapp atau https://..."
                                />
                            </label>
                            <label className="block text-sm font-medium text-ink sm:col-span-2">
                                Fitur paket
                                <span className="mt-0.5 block text-xs font-normal text-ink-soft">
                                    Satu fitur per baris
                                </span>
                                <textarea
                                    rows={Math.max(5, plan.features.length + 1)}
                                    value={plan.features.join('\n')}
                                    onChange={(e) => updateFeaturesText(index, e.target.value)}
                                    className={`${fieldClass} font-sans`}
                                    placeholder={'Survey & instalasi router\nSetup mesh\nSupport WhatsApp'}
                                />
                            </label>
                        </div>
                    </div>
                ))}
            </div>

            <button
                type="button"
                onClick={addPlan}
                className="inline-flex items-center gap-2 border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-mist"
            >
                <Plus className="h-4 w-4" />
                Tambah paket
            </button>

            {error && <span className="block text-xs text-red-600">{error}</span>}
        </div>
    );
}
