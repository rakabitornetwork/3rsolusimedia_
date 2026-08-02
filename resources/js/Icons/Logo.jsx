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
            <rect width="40" height="40" rx="10" fill="#0A2D82" />
            {/* Left T */}
            <rect x="7.2" y="12" width="12" height="4.4" rx="0.6" fill="#F5F8FC" />
            <rect x="11" y="13.5" width="4" height="15.2" rx="0.6" fill="#F5F8FC" />
            {/* Right T */}
            <rect x="20.8" y="12" width="12" height="4.4" rx="0.6" fill="#F5F8FC" />
            <rect x="24.6" y="13.5" width="4" height="15.2" rx="0.6" fill="#F5F8FC" />
            {/* WiFi */}
            <circle cx="30.5" cy="10.5" r="1.7" fill="#00B7FF" />
            <path
                d="M27.6 13.4c1.5 1.35 4.3 1.35 5.8 0"
                stroke="#00B7FF"
                strokeWidth="1.5"
                strokeLinecap="round"
            />
            <path
                d="M26 15.6c2.4 2.1 6.6 2.1 9 0"
                stroke="#00B7FF"
                strokeWidth="1.5"
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
                    letterSpacing="-0.01em"
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
    const label = alt || branding.company_name || 'Teslatech';
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
