import { ArrowUpRight } from 'lucide-react';
import Reveal from '../Components/Reveal';

export default function PricingSection({ section, whatsappUrl }) {
    if (!section) return null;

    const plans = section.content?.plans || [];
    const note = section.content?.note || null;

    const planUrl = (plan) => {
        if (plan.cta_url === 'whatsapp' || !plan.cta_url) {
            const text = encodeURIComponent(
                `Halo 3R Solusi Media, saya tertarik dengan paket ${plan.name}.`,
            );
            return `${whatsappUrl}?text=${text}`;
        }
        return plan.cta_url;
    };

    return (
        <section id="harga" className="relative overflow-hidden bg-paper py-20 lg:py-24">
            <div className="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-ink/15 to-transparent" />
            <div className="pointer-events-none absolute -left-32 top-24 h-72 w-72 rounded-full bg-signal/5 blur-3xl" />
            <div className="pointer-events-none absolute -right-24 bottom-16 h-80 w-80 rounded-full bg-ink/[0.04] blur-3xl" />

            <div className="relative mx-auto max-w-6xl px-5 lg:px-8">
                <Reveal className="max-w-2xl">
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-deep uppercase">
                        {section.subtitle}
                    </p>
                    <h2 className="font-display mt-2 text-3xl font-bold tracking-tight text-ink sm:text-4xl">
                        {section.title}
                    </h2>
                    <p className="mt-3 text-sm leading-relaxed text-ink-soft sm:text-base">
                        {section.body}
                    </p>
                </Reveal>

                <div className="mt-10 grid gap-3 lg:grid-cols-3 lg:gap-0 lg:border lg:border-ink/10">
                    {plans.map((plan, index) => {
                        const featured = Boolean(plan.featured);
                        const order = String(index + 1).padStart(2, '0');

                        return (
                            <Reveal key={plan.name} delay={index * 80}>
                                <article
                                    className={`group relative flex h-full flex-col border border-ink/10 p-5 transition duration-500 sm:p-6 lg:border-0 lg:border-r lg:border-ink/10 lg:last:border-r-0 ${
                                        featured
                                            ? 'bg-ink text-white lg:z-10 lg:shadow-[0_28px_56px_-36px_rgba(16,24,32,0.65)]'
                                            : 'bg-white text-ink hover:bg-mist/60'
                                    }`}
                                >
                                    {featured && (
                                        <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-signal-deep via-signal-bright to-amber-line" />
                                    )}

                                    <div className="flex items-center justify-between gap-3">
                                        <span
                                            className={`font-display text-[11px] font-semibold tracking-[0.2em] ${
                                                featured ? 'text-white/35' : 'text-ink/25'
                                            }`}
                                        >
                                            {order}
                                        </span>
                                        {plan.badge && (
                                            <span
                                                className={`text-[10px] font-semibold tracking-[0.16em] uppercase ${
                                                    featured
                                                        ? 'text-signal-bright'
                                                        : 'text-signal-deep'
                                                }`}
                                            >
                                                {plan.badge}
                                            </span>
                                        )}
                                    </div>

                                    <h3
                                        className={`font-display mt-4 text-lg font-bold tracking-tight ${
                                            featured ? 'text-white' : 'text-ink'
                                        }`}
                                    >
                                        {plan.name}
                                    </h3>
                                    <p
                                        className={`mt-1.5 text-xs leading-relaxed ${
                                            featured ? 'text-white/60' : 'text-ink-soft'
                                        }`}
                                    >
                                        {plan.description}
                                    </p>

                                    <div
                                        className={`mt-4 border-y py-3.5 ${
                                            featured ? 'border-white/10' : 'border-ink/10'
                                        }`}
                                    >
                                        <p className="font-hero text-3xl leading-none tracking-[-0.03em]">
                                            {plan.price}
                                        </p>
                                        {plan.period && (
                                            <p
                                                className={`mt-1.5 text-[10px] font-medium tracking-[0.12em] uppercase ${
                                                    featured ? 'text-white/45' : 'text-ink/45'
                                                }`}
                                            >
                                                {plan.period}
                                            </p>
                                        )}
                                    </div>

                                    <ul className="mt-3 flex-1">
                                        {(plan.features || []).map((feature) => (
                                            <li
                                                key={feature}
                                                className={`flex items-start gap-2.5 border-b py-2 text-xs leading-snug last:border-b-0 ${
                                                    featured
                                                        ? 'border-white/8 text-white/75'
                                                        : 'border-ink/8 text-ink-soft'
                                                }`}
                                            >
                                                <span
                                                    className={`mt-1.5 h-1 w-1 shrink-0 rounded-full ${
                                                        featured
                                                            ? 'bg-signal-bright'
                                                            : 'bg-signal-deep'
                                                    }`}
                                                    aria-hidden
                                                />
                                                <span>{feature}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    <a
                                        href={planUrl(plan)}
                                        target="_blank"
                                        rel="noreferrer"
                                        className={`mt-5 inline-flex items-center justify-between gap-2 px-3.5 py-2.5 text-xs font-semibold tracking-wide transition duration-300 ${
                                            featured
                                                ? 'bg-signal-bright text-ink hover:bg-white'
                                                : 'border border-ink/15 bg-transparent text-ink hover:border-signal-deep hover:bg-signal-deep hover:text-white'
                                        }`}
                                    >
                                        <span>{plan.cta_label || 'Pilih Paket'}</span>
                                        <ArrowUpRight
                                            className="h-3.5 w-3.5 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                            strokeWidth={1.75}
                                            aria-hidden
                                        />
                                    </a>
                                </article>
                            </Reveal>
                        );
                    })}
                </div>

                {note && (
                    <Reveal delay={180}>
                        <p className="mx-auto mt-8 max-w-3xl text-center text-xs leading-relaxed text-ink/50 sm:text-sm">
                            {note}
                        </p>
                    </Reveal>
                )}
            </div>
        </section>
    );
}
