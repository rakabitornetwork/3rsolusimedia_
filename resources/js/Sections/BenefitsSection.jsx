import Icon from '../Components/Icon';
import Reveal from '../Components/Reveal';

export default function BenefitsSection({ section }) {
    if (!section) return null;
    const items = section.content?.items || [];

    return (
        <section id="keunggulan" className="bg-mist py-24 lg:py-32">
            <div className="mx-auto max-w-7xl px-5 lg:px-8">
                <Reveal className="max-w-2xl">
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-deep uppercase">
                        {section.subtitle}
                    </p>
                    <h2 className="font-display mt-3 text-3xl font-bold tracking-tight text-ink sm:text-4xl">
                        {section.title}
                    </h2>
                    <p className="mt-4 text-base leading-relaxed text-ink-soft">{section.body}</p>
                </Reveal>

                <div className="mt-14 grid gap-6 md:grid-cols-2">
                    {items.map((item, index) => (
                        <Reveal key={item.title} delay={index * 70}>
                            <div className="flex gap-5 rounded-sm bg-paper/80 p-6 transition hover:bg-white">
                                <div className="mt-1 shrink-0 text-signal-deep">
                                    <Icon name={item.icon} className="h-6 w-6" />
                                </div>
                                <div>
                                    <h3 className="font-display text-lg font-bold text-ink">
                                        {item.title}
                                    </h3>
                                    <p className="mt-2 text-sm leading-relaxed text-ink-soft">
                                        {item.description}
                                    </p>
                                </div>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
