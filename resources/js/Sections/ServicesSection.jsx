import Icon from '../Components/Icon';
import Reveal from '../Components/Reveal';

export default function ServicesSection({ section }) {
    if (!section) return null;
    const items = section.content?.items || [];

    return (
        <section id="layanan" className="relative bg-paper py-24 lg:py-32">
            <div className="pointer-events-none absolute inset-x-0 top-0 h-40 bg-gradient-to-b from-mist to-transparent" />
            <div className="relative mx-auto max-w-7xl px-5 lg:px-8">
                <Reveal>
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-deep uppercase">
                        {section.subtitle}
                    </p>
                    <h2 className="font-display mt-3 max-w-2xl text-3xl font-bold tracking-tight text-ink sm:text-4xl">
                        {section.title}
                    </h2>
                    <p className="mt-4 max-w-2xl text-base leading-relaxed text-ink-soft">
                        {section.body}
                    </p>
                </Reveal>

                <div className="mt-14 grid gap-x-10 gap-y-12 sm:grid-cols-2 lg:grid-cols-4">
                    {items.map((item, index) => (
                        <Reveal key={item.title} delay={index * 80}>
                            <div className="group border-t border-ink/10 pt-6">
                                <div className="mb-5 inline-flex rounded-full bg-signal/10 p-3 text-signal-deep transition group-hover:bg-signal group-hover:text-white">
                                    <Icon name={item.icon} className="h-5 w-5" />
                                </div>
                                <h3 className="font-display text-lg font-bold text-ink">
                                    {item.title}
                                </h3>
                                <p className="mt-2 text-sm leading-relaxed text-ink-soft">
                                    {item.description}
                                </p>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
