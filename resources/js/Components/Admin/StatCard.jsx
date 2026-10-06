import { Link } from '@inertiajs/react';

const TONES = {
    slate: {
        bg: 'linear-gradient(135deg, #64748b 0%, #334155 55%, #1e293b 100%)',
        accent: '#94a3b8',
    },
    teal: {
        bg: 'linear-gradient(135deg, #2dd4bf 0%, #0d9488 50%, #0f766e 100%)',
        accent: '#99f6e4',
    },
    rose: {
        bg: 'linear-gradient(135deg, #fb7185 0%, #e11d48 52%, #be123c 100%)',
        accent: '#fecdd3',
    },
    sky: {
        bg: 'linear-gradient(135deg, #38bdf8 0%, #0284c7 52%, #0369a1 100%)',
        accent: '#bae6fd',
    },
    amber: {
        bg: 'linear-gradient(135deg, #fbbf24 0%, #f59e0b 45%, #d97706 100%)',
        accent: '#fde68a',
    },
    indigo: {
        bg: 'linear-gradient(135deg, #818cf8 0%, #4f46e5 52%, #3730a3 100%)',
        accent: '#c7d2fe',
    },
    emerald: {
        bg: 'linear-gradient(135deg, #34d399 0%, #059669 52%, #047857 100%)',
        accent: '#a7f3d0',
    },
    cyan: {
        bg: 'linear-gradient(135deg, #22d3ee 0%, #0891b2 52%, #0e7490 100%)',
        accent: '#a5f3fc',
    },
    violet: {
        bg: 'linear-gradient(135deg, #a78bfa 0%, #7c3aed 52%, #6d28d9 100%)',
        accent: '#ddd6fe',
    },
    blue: {
        bg: 'linear-gradient(135deg, #60a5fa 0%, #1a6eff 48%, #0a2d82 100%)',
        accent: '#bfdbfe',
    },
};

function patternId(tone) {
    return `stat-pat-${tone}`;
}

function CardBackdrop({ tone, accent }) {
    const id = patternId(tone);

    if (tone === 'slate' || tone === 'indigo' || tone === 'violet') {
        return (
            <svg
                className="pointer-events-none absolute inset-0 h-full w-full"
                viewBox="0 0 320 128"
                preserveAspectRatio="xMidYMid slice"
                aria-hidden
            >
                <defs>
                    <pattern id={id} width="24" height="24" patternUnits="userSpaceOnUse">
                        <circle cx="2" cy="2" r="1.4" fill={accent} opacity="0.28" />
                    </pattern>
                </defs>
                <rect width="320" height="128" fill={`url(#${id})`} />
                <path
                    d="M210 -10c40 30 70 55 110 70v78H210V-10Z"
                    fill={accent}
                    opacity="0.12"
                />
                <circle cx="278" cy="28" r="42" fill={accent} opacity="0.14" />
                <circle cx="300" cy="96" r="28" fill={accent} opacity="0.1" />
            </svg>
        );
    }

    if (tone === 'amber' || tone === 'rose') {
        return (
            <svg
                className="pointer-events-none absolute inset-0 h-full w-full"
                viewBox="0 0 320 128"
                preserveAspectRatio="xMidYMid slice"
                aria-hidden
            >
                <path
                    d="M0 88c48-22 86-8 128 6s78 28 120 8 52-40 72-52v78H0V88Z"
                    fill={accent}
                    opacity="0.16"
                />
                <path
                    d="M0 108c56-18 92 2 136 12s84 10 120-8 48-30 64-38v46H0v-12Z"
                    fill={accent}
                    opacity="0.12"
                />
                <circle cx="268" cy="24" r="36" fill={accent} opacity="0.18" />
                <path
                    d="M240 8l18 18-18 18-18-18 18-18Z"
                    fill={accent}
                    opacity="0.2"
                />
            </svg>
        );
    }

    // teal, emerald, cyan, sky, blue — signal arcs / soft rings
    return (
        <svg
            className="pointer-events-none absolute inset-0 h-full w-full"
            viewBox="0 0 320 128"
            preserveAspectRatio="xMidYMid slice"
            aria-hidden
        >
            <circle cx="286" cy="18" r="54" fill={accent} opacity="0.12" />
            <circle cx="286" cy="18" r="34" fill="none" stroke={accent} strokeWidth="1.5" opacity="0.35" />
            <circle cx="286" cy="18" r="22" fill="none" stroke={accent} strokeWidth="1.5" opacity="0.28" />
            <path
                d="M0 96c40-28 88-34 132-18s90 40 140 28 48-34 48-34v56H0V96Z"
                fill={accent}
                opacity="0.14"
            />
            <path
                d="M198 48c14 12 38 12 52 0"
                fill="none"
                stroke={accent}
                strokeWidth="2"
                strokeLinecap="round"
                opacity="0.35"
            />
            <path
                d="M188 60c20 18 54 18 74 0"
                fill="none"
                stroke={accent}
                strokeWidth="2"
                strokeLinecap="round"
                opacity="0.22"
            />
        </svg>
    );
}

/**
 * Premium admin stat card — fixed height, SVG backdrop, consistent layout.
 *
 * @param {'slate'|'teal'|'rose'|'sky'|'amber'|'indigo'|'emerald'|'cyan'|'violet'|'blue'} tone
 */
export default function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'blue',
    href,
    children,
    className = '',
}) {
    const palette = TONES[tone] || TONES.blue;

    const content = (
        <div
            className={`relative flex h-[128px] flex-col overflow-hidden p-4 text-white shadow-sm ${className}`}
            style={{ background: palette.bg }}
        >
            <CardBackdrop tone={tone in TONES ? tone : 'blue'} accent={palette.accent} />

            <div className="relative z-10 flex items-start justify-between gap-3">
                <p className="font-display text-[11px] font-semibold tracking-[0.14em] text-white/80 uppercase">
                    {label}
                </p>
                {Icon && (
                    <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center bg-white/15 backdrop-blur-[2px]">
                        <Icon className="h-[18px] w-[18px]" strokeWidth={1.75} aria-hidden />
                    </span>
                )}
            </div>

            <div className="relative z-10 mt-auto min-w-0">
                {children ?? (
                    <p className="font-display text-2xl font-bold tracking-tight break-words text-white">
                        {value ?? '—'}
                    </p>
                )}
                {hint != null && hint !== '' && (
                    <p className="mt-1.5 truncate text-xs text-white/70">{hint}</p>
                )}
            </div>
        </div>
    );

    if (!href) {
        return content;
    }

    return (
        <Link href={href} className="block h-[128px] transition hover:brightness-105">
            {content}
        </Link>
    );
}

export { TONES as STAT_CARD_TONES };
