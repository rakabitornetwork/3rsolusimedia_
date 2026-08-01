import { Link } from '@inertiajs/react';
import Logo from '../Icons/Logo';

export default function FooterSection({ section, settings }) {
    if (!section) return null;

    const links = section.content?.links || [];
    const legalLinks = section.content?.legal_links || [
        { label: 'Terms of Service', url: '/terms-of-service' },
    ];
    const year = new Date().getFullYear();
    const company = settings?.company_name || 'Perusahaan';
    const copyright = (section.content?.copyright || `© ${year} ${company}`).replace(
        '{year}',
        String(year),
    );

    return (
        <footer className="border-t border-white/10 bg-ink text-white">
            <div className="mx-auto flex max-w-7xl flex-col gap-10 px-5 py-14 lg:flex-row lg:items-start lg:justify-between lg:px-8">
                <div className="max-w-sm">
                    <Logo className="h-9 w-auto text-white" />
                    <p className="mt-4 text-sm leading-relaxed text-white/60">
                        {section.body || settings.tagline}
                    </p>
                </div>

                <div>
                    <p className="font-display text-sm font-semibold tracking-wide uppercase">
                        Navigasi
                    </p>
                    <ul className="mt-4 space-y-2 text-sm text-white/65">
                        {links.map((link) => (
                            <li key={link.label}>
                                <a href={link.url} className="transition hover:text-white">
                                    {link.label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>

                <div>
                    <p className="font-display text-sm font-semibold tracking-wide uppercase">
                        Kontak
                    </p>
                    <ul className="mt-4 space-y-2 text-sm text-white/65">
                        {settings.phone && <li>{settings.phone}</li>}
                        {settings.email && <li>{settings.email}</li>}
                        {settings.address && <li>{settings.address}</li>}
                    </ul>
                </div>
            </div>
            <div className="border-t border-white/10">
                <div className="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-5 text-xs text-white/45 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                    <p>{copyright}</p>
                    <ul className="flex flex-wrap gap-4">
                        {legalLinks.map((link) => (
                            <li key={link.label}>
                                {link.url.startsWith('/') ? (
                                    <Link
                                        href={link.url}
                                        className="transition hover:text-white"
                                    >
                                        {link.label}
                                    </Link>
                                ) : (
                                    <a href={link.url} className="transition hover:text-white">
                                        {link.label}
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </footer>
    );
}
