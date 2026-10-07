import { Link } from '@inertiajs/react';
import Reveal from '../Components/Reveal';

export default function VpnSection({ section }) {
    if (!section) return null;

    const steps = section.content?.steps || [];
    const image = section.image || '/images/vpn/office.jpg';
    const detail = section.image_secondary || '/images/vpn/router.jpg';

    return (
        <section id="vpn" className="relative overflow-hidden bg-ink py-24 text-white lg:py-32">
            <div className="pointer-events-none absolute -left-24 top-10 h-72 w-72 rounded-full bg-signal/20 blur-3xl" />
            <div className="relative mx-auto grid max-w-7xl items-center gap-12 px-5 lg:grid-cols-2 lg:px-8">
                <Reveal>
                    <div className="relative">
                        <img
                            src={image}
                            alt="Router di meja kerja untuk layanan VPN"
                            className="aspect-[16/10] w-full rounded-3xl object-cover"
                        />
                        <img
                            src={detail}
                            alt="Router jaringan untuk sambungan VPN"
                            className="absolute -bottom-8 -right-2 hidden w-40 rounded-2xl border-4 border-ink object-cover shadow-2xl sm:block sm:w-52"
                        />
                    </div>
                </Reveal>

                <Reveal delay={80}>
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-bright uppercase">
                        {section.subtitle}
                    </p>
                    <h2 className="font-display mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        {section.title}
                    </h2>
                    <p className="mt-4 text-base leading-relaxed text-white/75">{section.body}</p>

                    <ol className="mt-8 space-y-4">
                        {steps.map((step, index) => (
                            <li key={step.title} className="flex gap-4">
                                <span className="font-display flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-signal text-sm font-bold text-white">
                                    {index + 1}
                                </span>
                                <div>
                                    <h3 className="font-display text-base font-bold">{step.title}</h3>
                                    <p className="mt-1 text-sm leading-relaxed text-white/70">{step.description}</p>
                                </div>
                            </li>
                        ))}
                    </ol>

                    <Link
                        href={section.cta_url || '/vpn/daftar'}
                        className="mt-8 inline-flex rounded-xl bg-signal-bright px-5 py-3 text-sm font-semibold text-ink transition hover:bg-white"
                    >
                        {section.cta_label || 'Daftar VPN'}
                    </Link>
                </Reveal>
            </div>
        </section>
    );
}
