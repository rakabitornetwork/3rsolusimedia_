import { useState } from 'react';
import { Menu, X } from 'lucide-react';
import Logo from '../Icons/Logo';

export default function Navbar({ settings, whatsappUrl }) {
    const [open, setOpen] = useState(false);
    const company = settings.company_name || 'Perusahaan';

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
                    className="flex items-center gap-3 text-white drop-shadow-sm transition-transform duration-200 hover:scale-105"
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
                    className="relative overflow-hidden rounded-xl border border-white/20 bg-white/10 p-2.5 text-white backdrop-blur-md transition-all duration-300 hover:bg-white/20 active:scale-95 lg:hidden"
                    onClick={() => setOpen((v) => !v)}
                    aria-label="Menu"
                >
                    <div
                        className={`transition-all duration-300 ${
                            open ? 'rotate-90 scale-110' : 'rotate-0 scale-100'
                        }`}
                    >
                        {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                    </div>
                </button>
            </div>

            {/* Popup Mobile Menu dengan animasi smooth & staggered links */}
            <div
                className={`mx-5 overflow-hidden rounded-2xl border border-white/20 bg-ink/95 shadow-2xl backdrop-blur-2xl transition-all duration-300 lg:hidden ${
                    open
                        ? 'max-h-[420px] translate-y-0 p-6 opacity-100 pointer-events-auto border-white/20'
                        : 'max-h-0 -translate-y-3 p-0 opacity-0 pointer-events-none border-transparent'
                }`}
            >
                <div className="flex flex-col gap-4 text-sm font-medium text-white">
                    {links.map((link, idx) => (
                        <a
                            key={link.href}
                            href={link.href}
                            onClick={() => setOpen(false)}
                            className={`transform transition-all duration-300 hover:translate-x-2 hover:text-signal-bright ${
                                open ? 'translate-y-0 opacity-100' : '-translate-y-2 opacity-0'
                            }`}
                            style={{ transitionDelay: open ? `${(idx + 1) * 35}ms` : '0ms' }}
                        >
                            {link.label}
                        </a>
                    ))}
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        className={`mt-2 block rounded-xl bg-gradient-to-r from-signal-bright to-signal px-4 py-3 text-center font-bold text-ink shadow-lg transition-all duration-300 hover:opacity-95 active:scale-[0.98] ${
                            open ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'
                        }`}
                        style={{ transitionDelay: open ? `${(links.length + 1) * 35}ms` : '0ms' }}
                    >
                        WhatsApp
                    </a>
                </div>
            </div>
        </header>
    );
}
