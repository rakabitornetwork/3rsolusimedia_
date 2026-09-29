/**
 * Iklan header portal. Gambar, judul, dan tautan diatur dari Pengaturan Aplikasi.
 */
export default function PortalBanner({ banner }) {
    if (!banner?.enabled || !banner?.image) {
        return null;
    }

    const title = (banner.title || '').trim();
    const subtitle = (banner.subtitle || '').trim();
    const link = (banner.link || '').trim();
    const hasCopy = Boolean(title || subtitle);
    const external = /^https?:\/\//i.test(link);
    const Tag = link ? 'a' : 'div';
    const linkProps = link
        ? {
              href: link,
              ...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {}),
          }
        : {};

    return (
        <Tag
            {...linkProps}
            className="group relative block overflow-hidden rounded-2xl shadow-[0_18px_50px_-28px_rgba(10,45,130,0.7)] ring-1 ring-white/70"
        >
            <img
                src={banner.image}
                alt={title || 'Promo'}
                className="aspect-[2.15/1] w-full object-cover transition duration-700 ease-out group-hover:scale-[1.025] sm:aspect-[21/8]"
            />
            <div
                className={`pointer-events-none absolute inset-0 ${
                    hasCopy
                        ? 'bg-gradient-to-t from-ink/85 via-ink/30 to-ink/10'
                        : 'bg-gradient-to-t from-ink/30 via-transparent to-ink/10'
                }`}
            />
            {hasCopy && (
                <div className="absolute inset-x-0 bottom-0 p-4 sm:p-5">
                    {title && (
                        <p className="font-hero text-[1.65rem] leading-none tracking-tight text-white sm:text-3xl">
                            {title}
                        </p>
                    )}
                    {subtitle && (
                        <p className="mt-1.5 max-w-md text-sm leading-snug text-white/85">{subtitle}</p>
                    )}
                </div>
            )}
        </Tag>
    );
}
