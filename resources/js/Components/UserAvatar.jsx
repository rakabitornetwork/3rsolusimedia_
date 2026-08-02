const roleGradients = {
    superadmin: 'from-indigo-500 to-indigo-900',
    admin: 'from-emerald-500 to-teal-800',
    teknisi: 'from-amber-400 to-orange-700',
};

const sizeMap = {
    sm: 'h-9 w-9 text-xs',
    md: 'h-11 w-11 text-sm',
    lg: 'h-16 w-16 text-lg',
    xl: 'h-20 w-20 text-xl',
};

export function getInitials(name = '') {
    const parts = String(name)
        .trim()
        .split(/\s+/)
        .filter(Boolean);

    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();

    return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
}

export default function UserAvatar({
    name,
    role,
    src,
    initials,
    size = 'sm',
    className = '',
}) {
    const sizeClass = sizeMap[size] || sizeMap.sm;
    const gradient = roleGradients[role] || 'from-slate-500 to-slate-800';
    const label = initials || getInitials(name);

    if (src) {
        return (
            <img
                src={src}
                alt={name || 'Avatar'}
                className={`${sizeClass} shrink-0 object-cover ${className}`}
            />
        );
    }

    return (
        <div
            className={`inline-flex shrink-0 items-center justify-center bg-gradient-to-br font-semibold text-white ${gradient} ${sizeClass} ${className}`}
            aria-hidden={!name}
            title={name || undefined}
        >
            {label}
        </div>
    );
}
