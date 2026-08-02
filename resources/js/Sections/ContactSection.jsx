import { useState } from 'react';
import { Mail, MapPin, Phone, Clock } from 'lucide-react';
import Reveal from '../Components/Reveal';

export default function ContactSection({ section, settings, whatsappUrl }) {
    if (!section) return null;

    const [name, setName] = useState('');
    const [message, setMessage] = useState('');

    const company = settings?.company_name || 'Perusahaan';

    const sendWhatsApp = (e) => {
        e.preventDefault();
        const text = encodeURIComponent(
            `Halo ${company},\nSaya ${name || 'calon pelanggan'}.\n${message || 'Saya ingin konsultasi pemasangan WiFi rumahan.'}`,
        );
        window.open(`${whatsappUrl}?text=${text}`, '_blank', 'noopener,noreferrer');
    };

    return (
        <section id="kontak" className="bg-paper py-24 lg:py-32">
            <div className="mx-auto grid max-w-7xl gap-12 px-5 lg:grid-cols-2 lg:gap-20 lg:px-8">
                <Reveal>
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-deep uppercase">
                        {section.subtitle}
                    </p>
                    <h2 className="font-display mt-3 text-3xl font-bold tracking-tight text-ink sm:text-4xl">
                        {section.title}
                    </h2>
                    <p className="mt-4 text-base leading-relaxed text-ink-soft">{section.body}</p>

                    <ul className="mt-10 space-y-5 text-sm text-ink-soft">
                        {settings.phone && (
                            <li className="flex items-start gap-3">
                                <Phone className="mt-0.5 h-4 w-4 text-signal-deep" />
                                <span>{settings.phone}</span>
                            </li>
                        )}
                        {settings.email && (
                            <li className="flex items-start gap-3">
                                <Mail className="mt-0.5 h-4 w-4 text-signal-deep" />
                                <span>{settings.email}</span>
                            </li>
                        )}
                        {settings.address && (
                            <li className="flex items-start gap-3">
                                <MapPin className="mt-0.5 h-4 w-4 text-signal-deep" />
                                <span>{settings.address}</span>
                            </li>
                        )}
                        {settings.operating_hours && (
                            <li className="flex items-start gap-3">
                                <Clock className="mt-0.5 h-4 w-4 text-signal-deep" />
                                <span>{settings.operating_hours}</span>
                            </li>
                        )}
                    </ul>
                </Reveal>

                <Reveal delay={120}>
                    <form
                        onSubmit={sendWhatsApp}
                        className="border border-ink/10 bg-white p-6 sm:p-8"
                    >
                        <p className="text-sm text-ink-soft">
                            {section.content?.form_note ||
                                'Kirim pesan singkat — kami balas di jam operasional.'}
                        </p>
                        <label className="mt-6 block text-sm font-medium text-ink">
                            Nama
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                className="mt-2 w-full border border-ink/15 bg-paper px-4 py-3 outline-none focus:border-signal"
                                placeholder="Nama Anda"
                            />
                        </label>
                        <label className="mt-4 block text-sm font-medium text-ink">
                            Pesan
                            <textarea
                                rows={4}
                                value={message}
                                onChange={(e) => setMessage(e.target.value)}
                                className="mt-2 w-full resize-y border border-ink/15 bg-paper px-4 py-3 outline-none focus:border-signal"
                                placeholder="Ceritakan kebutuhan WiFi rumah Anda..."
                            />
                        </label>
                        <button
                            type="submit"
                            className="mt-6 w-full rounded-md bg-signal-deep px-5 py-3.5 text-sm font-bold text-white transition hover:bg-ink"
                        >
                            {section.cta_label || 'Kirim via WhatsApp'}
                        </button>
                    </form>
                </Reveal>
            </div>
        </section>
    );
}
