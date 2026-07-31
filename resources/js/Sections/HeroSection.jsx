export default function HeroSection({ section, settings, whatsappUrl }) {
    if (!section) return null;

    const badge = section.content?.badge;
    const secondaryLabel = section.content?.secondary_cta_label;
    const secondaryUrl = section.content?.secondary_cta_url || '#layanan';
    const resolveUrl = (url) => {
        if (!url || url === 'whatsapp') return whatsappUrl;
        return url;
    };
    const primaryUrl = resolveUrl(section.cta_url);

    return (
        <section id="top" className="relative min-h-[100svh] overflow-hidden">
            <div className="absolute inset-0">
                <img
                    src={section.image || '/images/hero/wifi-living.jpg'}
                    alt=""
                    className="h-full w-full object-cover"
                    fetchPriority="high"
                />
                <div className="absolute inset-0 bg-gradient-to-r from-ink/92 via-ink/72 to-ink/40" />
                <div className="absolute inset-0 bg-gradient-to-t from-ink/75 via-transparent to-ink/25" />
                <div className="animate-signal absolute -right-24 top-1/4 h-72 w-72 rounded-full bg-signal/30 blur-3xl" />
            </div>

            <div className="relative mx-auto flex min-h-[100svh] max-w-7xl items-center px-5 pt-28 pb-16 lg:px-8 lg:pt-24">
                <div className="max-w-2xl">
                    <p className="animate-rise font-display text-sm font-semibold tracking-[0.22em] text-signal-bright uppercase">
                        {badge || section.subtitle || 'Instalasi WiFi Rumahan'}
                    </p>
                    <h1
                        className="animate-rise font-hero mt-5 text-5xl leading-[1.05] font-normal tracking-[-0.02em] text-white sm:text-6xl lg:text-7xl"
                        style={{ animationDelay: '100ms' }}
                    >
                        {settings.company_name || '3R Solusi Media'}
                    </h1>
                    <p
                        className="animate-rise mt-6 max-w-xl text-xl font-medium text-white/90 sm:text-2xl"
                        style={{ animationDelay: '180ms' }}
                    >
                        {section.title}
                    </p>
                    <p
                        className="animate-rise mt-4 max-w-lg text-base leading-relaxed text-white/70"
                        style={{ animationDelay: '260ms' }}
                    >
                        {section.body}
                    </p>
                    <div
                        className="animate-rise mt-9 flex flex-wrap items-center gap-3"
                        style={{ animationDelay: '340ms' }}
                    >
                        <a
                            href={primaryUrl}
                            target={primaryUrl?.startsWith('http') ? '_blank' : undefined}
                            rel={primaryUrl?.startsWith('http') ? 'noreferrer' : undefined}
                            className="rounded-md bg-signal-bright px-6 py-3.5 text-sm font-bold text-ink transition hover:bg-white"
                        >
                            {section.cta_label || 'Konsultasi Gratis'}
                        </a>
                        {secondaryLabel && (
                            <a
                                href={secondaryUrl}
                                className="rounded-md border border-white/30 bg-white/5 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/15"
                            >
                                {secondaryLabel}
                            </a>
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
