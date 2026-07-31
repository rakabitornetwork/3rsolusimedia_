import Reveal from '../Components/Reveal';

export default function TestimonialsSection({ section }) {
    if (!section) return null;
    const items = section.content?.items || [];

    return (
        <section id="testimoni" className="bg-signal-deep py-24 text-white lg:py-32">
            <div className="mx-auto max-w-7xl px-5 lg:px-8">
                <Reveal className="max-w-2xl">
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-bright uppercase">
                        {section.subtitle}
                    </p>
                    <h2 className="font-display mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                        {section.title}
                    </h2>
                    <p className="mt-4 text-base leading-relaxed text-white/70">{section.body}</p>
                </Reveal>

                <div className="mt-14 grid gap-6 lg:grid-cols-3">
                    {items.map((item, index) => (
                        <Reveal key={item.name} delay={index * 90}>
                            <blockquote className="flex h-full flex-col border border-white/10 bg-white/5 p-7 backdrop-blur-sm">
                                <p className="flex-1 text-base leading-relaxed text-white/85">
                                    “{item.quote}”
                                </p>
                                <footer className="mt-6 border-t border-white/10 pt-5">
                                    <p className="font-display font-semibold">{item.name}</p>
                                    <p className="mt-1 text-sm text-white/55">{item.role}</p>
                                </footer>
                            </blockquote>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
