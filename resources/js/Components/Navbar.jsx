import { useState } from 'react';
import { Menu, X } from 'lucide-react';
import Logo from '../Icons/Logo';

export default function Navbar({ settings, whatsappUrl }) {
    const [open, setOpen] = useState(false);
    const company = settings.company_name || '3R Solusi Media';

    const links = [
        { href: '#layanan', label: 'Layanan' },
        { href: '#harga', label: 'Harga' },
        { href: '#tentang', label: 'Tentang' },
        { href: '#keunggulan', label: 'Keunggulan' },
        { href: '#proses', label: 'Proses' },
        { href: '#kontak', label: 'Kontak' },
    ];

    return (
        <header className="absolute inset-x-0 top-0 z-40">
            <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-5 lg:px-8">
                <a
                    href="#top"
                    className="flex items-center gap-3 text-white drop-shadow-sm"
                    aria-label={company}
                >
                    <Logo className="h-9 w-9" markOnly />
                    <span className="font-display hidden text-sm font-bold tracking-tight sm:inline">
                        {company}
                    </span>
                </a>

                <nav className="hidden items-center gap-8 text-sm font-medium text-white/90 lg:flex">
                    {links.map((link) => (
                        <a key={link.href} href={link.href} className="transition hover:text-white">
                            {link.label}
                        </a>
                    ))}
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-md bg-signal-bright px-4 py-2.5 font-semibold text-ink transition hover:bg-white"
                    >
                        WhatsApp
                    </a>
                </nav>

                <button
                    type="button"
                    className="rounded-md bg-white/10 p-2 text-white backdrop-blur lg:hidden"
                    onClick={() => setOpen((v) => !v)}
                    aria-label="Menu"
                >
                    {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                </button>
            </div>

            {open && (
                <div className="mx-5 rounded-2xl border border-white/15 bg-ink/90 p-5 backdrop-blur-xl lg:hidden">
                    <div className="flex flex-col gap-4 text-sm font-medium text-white">
                        {links.map((link) => (
                            <a key={link.href} href={link.href} onClick={() => setOpen(false)}>
                                {link.label}
                            </a>
                        ))}
                        <a
                            href={whatsappUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-md bg-signal-bright px-4 py-3 text-center font-semibold text-ink"
                        >
                            WhatsApp
                        </a>
                    </div>
                </div>
            )}
        </header>
    );
}
