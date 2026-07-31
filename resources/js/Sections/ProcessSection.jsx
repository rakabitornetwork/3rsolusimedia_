import Reveal from '../Components/Reveal';

export default function ProcessSection({ section }) {
    if (!section) return null;
    const steps = section.content?.steps || [];

    return (
        <section id="proses" className="bg-paper py-24 lg:py-32">
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

                <div className="mt-14 grid gap-8 md:grid-cols-2 lg:grid-cols-4">
                    {steps.map((step, index) => (
                        <Reveal key={step.step} delay={index * 90}>
                            <div className="relative">
                                <p className="font-display text-5xl font-extrabold text-signal/20">
                                    {step.step}
                                </p>
                                <h3 className="font-display mt-3 text-lg font-bold text-ink">
                                    {step.title}
                                </h3>
                                <p className="mt-2 text-sm leading-relaxed text-ink-soft">
                                    {step.description}
                                </p>
                            </div>
                        </Reveal>
                    ))}
                </div>
            </div>
        </section>
    );
}
