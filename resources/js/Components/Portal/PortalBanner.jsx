import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';

const ROTATE_MS = 5500;

/**
 * Tiga iklan header yang bergantian. Gambar bawaan sudah memuat teks promo.
 */
export default function PortalBanner({ banners }) {
    const slides = Array.isArray(banners) ? banners.filter((slide) => slide?.image) : [];
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const [touchX, setTouchX] = useState(null);

    useEffect(() => {
        if (index >= slides.length) {
            setIndex(0);
        }
    }, [index, slides.length]);

    useEffect(() => {
        if (slides.length < 2 || paused) return undefined;
        const timer = window.setInterval(() => {
            setIndex((current) => (current + 1) % slides.length);
        }, ROTATE_MS);
        return () => window.clearInterval(timer);
    }, [slides.length, paused, index]);

    if (slides.length === 0) {
        return null;
    }

    const go = (next) => {
        setIndex((next + slides.length) % slides.length);
    };

    return (
        <section
            className="group relative overflow-hidden rounded-2xl shadow-[0_18px_50px_-28px_rgba(10,45,130,0.7)] ring-1 ring-white/70"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
            onTouchStart={(event) => setTouchX(event.changedTouches[0]?.clientX ?? null)}
            onTouchEnd={(event) => {
                if (touchX == null) return;
                const dx = (event.changedTouches[0]?.clientX ?? touchX) - touchX;
                if (dx > 48) go(index - 1);
                if (dx < -48) go(index + 1);
                setTouchX(null);
            }}
        >
            <div
                className="flex transition-transform duration-700 ease-out"
                style={{ transform: `translateX(-${index * 100}%)` }}
            >
                {slides.map((slide, slideIndex) => (
                    <BannerSlide key={`${slide.image}-${slideIndex}`} slide={slide} />
                ))}
            </div>

            {slides.length > 1 && (
                <div className="absolute inset-x-0 bottom-2 flex items-center justify-center gap-2">
                    <button
                        type="button"
                        aria-label="Banner sebelumnya"
                        onClick={() => go(index - 1)}
                        className="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-ink/55 text-white backdrop-blur hover:bg-ink/75"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <div className="flex items-center gap-1.5 rounded-full bg-ink/45 px-2.5 py-1.5 backdrop-blur">
                        {slides.map((slide, dotIndex) => (
                            <button
                                key={`${slide.title}-${dotIndex}`}
                                type="button"
                                aria-label={`Banner ${dotIndex + 1}`}
                                onClick={() => go(dotIndex)}
                                className={`h-1.5 cursor-pointer rounded-full transition-all ${
                                    dotIndex === index ? 'w-5 bg-white' : 'w-1.5 bg-white/60'
                                }`}
                            />
                        ))}
                    </div>
                    <button
                        type="button"
                        aria-label="Banner berikutnya"
                        onClick={() => go(index + 1)}
                        className="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-ink/55 text-white backdrop-blur hover:bg-ink/75"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </div>
            )}
        </section>
    );
}

function BannerSlide({ slide }) {
    const title = (slide.title || '').trim();
    const subtitle = (slide.subtitle || '').trim();
    const link = (slide.link || '').trim();
    const designed = (slide.image || '').includes('/images/portal/');
    const showCopy = !designed && Boolean(title || subtitle);
    const external = /^https?:\/\//i.test(link);
    const Tag = link ? 'a' : 'div';
    const linkProps = link
        ? {
              href: link,
              ...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {}),
          }
        : {};

    return (
        <Tag {...linkProps} className="relative block w-full shrink-0">
            <img
                src={slide.image}
                alt={title || 'Promo'}
                className="aspect-[16/9] w-full object-cover"
            />
            {showCopy && (
                <>
                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/25 to-transparent" />
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
                </>
            )}
        </Tag>
    );
}
