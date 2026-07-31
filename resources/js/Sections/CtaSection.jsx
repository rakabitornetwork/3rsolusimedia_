import Reveal from '../Components/Reveal';

export default function CtaSection({ section, whatsappUrl }) {
    if (!section) return null;

    const href = section.cta_url === 'whatsapp' ? whatsappUrl : section.cta_url || whatsappUrl;

    return (
        <section className="relative overflow-hidden py-24 lg:py-28">
            <div className="absolute inset-0">
                <img
                    src={section.image || '/images/hero/wifi-living.jpg'}
                    alt=""
                    className="h-full w-full object-cover"
                    loading="lazy"
                />
                <div className="absolute inset-0 bg-ink/80" />
            </div>

            <div className="relative mx-auto max-w-4xl px-5 text-center lg:px-8">
                <Reveal>
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-bright uppercase">
                        {section.subtitle}
                    </p>
                    <h2 className="font-display mt-4 text-3xl font-bold tracking-tight text-white sm:text-5xl">
                        {section.title}
                    </h2>
                    <p className="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-white/70">
                        {section.body}
                    </p>
                    <a
                        href={href}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-9 inline-flex rounded-md bg-signal-bright px-7 py-3.5 text-sm font-bold text-ink transition hover:bg-white"
                    >
                        {section.cta_label || 'Chat WhatsApp'}
                    </a>
                </Reveal>
            </div>
        </section>
    );
}
