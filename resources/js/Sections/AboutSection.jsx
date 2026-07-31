import Reveal from '../Components/Reveal';

export default function AboutSection({ section }) {
    if (!section) return null;
    const stats = section.content?.stats || [];

    return (
        <section id="tentang" className="bg-ink py-24 text-white lg:py-32">
            <div className="mx-auto grid max-w-7xl items-center gap-12 px-5 lg:grid-cols-2 lg:gap-16 lg:px-8">
                <Reveal>
                    <div className="relative overflow-hidden rounded-sm">
                        <img
                            src={section.image || '/images/hero/install-tech.jpg'}
                            alt=""
                            className="aspect-[4/5] w-full object-cover sm:aspect-[5/4] lg:aspect-[4/5]"
                            loading="lazy"
                        />
                        <div className="absolute inset-0 bg-gradient-to-t from-ink/50 to-transparent" />
                    </div>
                </Reveal>

                <Reveal delay={120}>
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-bright uppercase">
                        {section.subtitle}
                    </p>
                    <h2 className="font-display mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        {section.title}
                    </h2>
                    <p className="mt-5 text-base leading-relaxed text-white/70">
                        {section.body}
                    </p>

                    <div className="mt-10 grid grid-cols-3 gap-4 border-t border-white/15 pt-8">
                        {stats.map((stat) => (
                            <div key={stat.label}>
                                <p className="font-display text-2xl font-bold text-signal-bright sm:text-3xl">
                                    {stat.value}
                                </p>
                                <p className="mt-1 text-xs tracking-wide text-white/55 sm:text-sm">
                                    {stat.label}
                                </p>
                            </div>
                        ))}
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
