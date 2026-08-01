import { Head, Link } from '@inertiajs/react';
import Logo from '../../Icons/Logo';

export default function Terms({ section, footer, settings }) {
    const paragraphs = section.content?.paragraphs || [];
    const updatedAt = section.content?.updated_at || null;
    const year = new Date().getFullYear();
    const company = settings?.company_name || 'Perusahaan';
    const copyright = (footer?.content?.copyright || `© ${year} ${company}`).replace(
        '{year}',
        String(year),
    );

    return (
        <>
            <Head title={section.title || 'Terms of Service'} />

            <div className="min-h-screen bg-paper">
                <header className="border-b border-ink/10 bg-ink text-white">
                    <div className="mx-auto flex max-w-4xl items-center justify-between px-5 py-5 lg:px-8">
                        <Link href="/" className="text-white">
                            <Logo className="h-8 w-auto" />
                        </Link>
                        <Link
                            href="/"
                            className="text-sm font-medium text-white/70 transition hover:text-white"
                        >
                            Kembali ke Beranda
                        </Link>
                    </div>
                </header>

                <main className="mx-auto max-w-4xl px-5 py-14 lg:px-8 lg:py-20">
                    <p className="font-display text-sm font-semibold tracking-[0.2em] text-signal-deep uppercase">
                        {section.subtitle || 'Legal'}
                    </p>
                    <h1 className="font-hero mt-4 text-4xl leading-tight text-ink sm:text-5xl">
                        {section.title || 'Terms of Service'}
                    </h1>
                    {updatedAt && (
                        <p className="mt-4 text-sm text-ink-soft">Terakhir diperbarui: {updatedAt}</p>
                    )}
                    {section.body && (
                        <p className="mt-6 text-base leading-relaxed text-ink-soft">{section.body}</p>
                    )}

                    <div className="mt-10 space-y-8">
                        {paragraphs.map((item) => (
                            <section key={item.heading}>
                                <h2 className="font-display text-xl font-bold text-ink">
                                    {item.heading}
                                </h2>
                                <p className="mt-3 text-base leading-relaxed text-ink-soft whitespace-pre-line">
                                    {item.body}
                                </p>
                            </section>
                        ))}
                    </div>
                </main>

                <footer className="border-t border-ink/10 bg-mist">
                    <div className="mx-auto flex max-w-4xl flex-col gap-3 px-5 py-6 text-xs text-ink-soft sm:flex-row sm:items-center sm:justify-between lg:px-8">
                        <p>{copyright}</p>
                        <Link href="/terms-of-service" className="font-medium text-signal-deep hover:underline">
                            Terms of Service
                        </Link>
                    </div>
                </footer>
            </div>
        </>
    );
}
