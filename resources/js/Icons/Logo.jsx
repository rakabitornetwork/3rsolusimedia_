import { usePage } from '@inertiajs/react';

function FallbackLogo({ className, markOnly, label }) {
    return (
        <svg
            className={className}
            viewBox={markOnly ? '0 0 40 40' : '0 0 220 40'}
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-label={label}
            role="img"
        >
            <rect width="40" height="40" rx="10" fill="#0F5C5A" />
            <path
                d="M10 26V14h7.2c2.4 0 4 1.35 4 3.35 0 1.35-.8 2.45-2.1 2.95L21.5 26h-3.15l-2-5.05H13.1V26H10Zm3.1-7.55h3.85c.95 0 1.5-.5 1.5-1.2s-.55-1.15-1.5-1.15H13.1v2.35Z"
                fill="#F5F8FA"
            />
            <circle cx="28.5" cy="14.5" r="2.2" fill="#14B8A6" />
            <path
                d="M25.2 18.2c1.7 1.55 4.9 1.55 6.6 0"
                stroke="#14B8A6"
                strokeWidth="1.6"
                strokeLinecap="round"
            />
            <path
                d="M23.4 20.8c2.7 2.4 7.5 2.4 10.2 0"
                stroke="#14B8A6"
                strokeWidth="1.6"
                strokeLinecap="round"
                opacity="0.7"
            />
            {!markOnly && (
                <text
                    x="52"
                    y="26"
                    fill="currentColor"
                    fontFamily="Syne, sans-serif"
                    fontSize="16"
                    fontWeight="700"
                    letterSpacing="0.02em"
                >
                    {label}
                </text>
            )}
        </svg>
    );
}

export default function Logo({
    className = 'h-9 w-auto',
    markOnly = false,
    src,
    alt,
}) {
    const page = usePage();
    const branding = page.props.app || {};
    const label = alt || branding.company_name || 'Perusahaan';
    const imageSrc =
        src || (markOnly ? branding.logo_mark : branding.logo_full) || branding.logo_mark;

    if (imageSrc) {
        return (
            <img
                src={imageSrc}
                alt={label}
                className={`${className} bg-transparent object-contain`}
            />
        );
    }

    return <FallbackLogo className={className} markOnly={markOnly} label={label} />;
}
