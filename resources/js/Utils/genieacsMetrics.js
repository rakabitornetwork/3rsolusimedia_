/**
 * Warna status metrik ONU/GenieACS agar konsisten di daftar & detail.
 */

export function temperatureTone(value) {
    if (value == null || Number.isNaN(Number(value))) {
        return {
            key: 'empty',
            text: 'text-ink-soft',
            bg: 'bg-ink/5',
            icon: 'text-ink-soft',
            badge: 'bg-ink/5 text-ink-soft',
            card: 'border-ink/10 bg-mist/40',
            accent: 'from-slate-300 to-slate-500',
        };
    }

    const n = Number(value);
    if (n <= 50) {
        return {
            key: 'good',
            text: 'text-emerald-700',
            bg: 'bg-emerald-50',
            icon: 'text-emerald-600',
            badge: 'bg-emerald-50 text-emerald-700',
            card: 'border-emerald-200 bg-emerald-50/70',
            accent: 'from-emerald-400 to-teal-600',
        };
    }
    if (n <= 65) {
        return {
            key: 'warn',
            text: 'text-amber-700',
            bg: 'bg-amber-50',
            icon: 'text-amber-600',
            badge: 'bg-amber-50 text-amber-700',
            card: 'border-amber-200 bg-amber-50/70',
            accent: 'from-amber-300 to-orange-500',
        };
    }

    return {
        key: 'bad',
        text: 'text-rose-700',
        bg: 'bg-rose-50',
        icon: 'text-rose-600',
        badge: 'bg-rose-50 text-rose-700',
        card: 'border-rose-200 bg-rose-50/70',
        accent: 'from-rose-400 to-pink-600',
    };
}

/** RX optical power (dBm). Ideal umum ONU: sekitar -8 s/d -25. */
export function rxPowerTone(value) {
    if (value == null || Number.isNaN(Number(value))) {
        return {
            key: 'empty',
            text: 'text-ink-soft',
            bg: 'bg-ink/5',
            icon: 'text-ink-soft',
            badge: 'bg-ink/5 text-ink-soft',
            card: 'border-ink/10 bg-mist/40',
            accent: 'from-slate-300 to-slate-500',
        };
    }

    const n = Number(value);
    if (n > -8 || n < -28) {
        return {
            key: 'bad',
            text: 'text-rose-700',
            bg: 'bg-rose-50',
            icon: 'text-rose-600',
            badge: 'bg-rose-50 text-rose-700',
            card: 'border-rose-200 bg-rose-50/70',
            accent: 'from-rose-400 to-pink-600',
        };
    }
    if (n < -25) {
        return {
            key: 'warn',
            text: 'text-amber-700',
            bg: 'bg-amber-50',
            icon: 'text-amber-600',
            badge: 'bg-amber-50 text-amber-700',
            card: 'border-amber-200 bg-amber-50/70',
            accent: 'from-amber-300 to-orange-500',
        };
    }

    return {
        key: 'good',
        text: 'text-emerald-700',
        bg: 'bg-emerald-50',
        icon: 'text-emerald-600',
        badge: 'bg-emerald-50 text-emerald-700',
        card: 'border-emerald-200 bg-emerald-50/70',
        accent: 'from-emerald-400 to-teal-600',
    };
}

export function onlineTone(online) {
    if (online) {
        return {
            key: 'online',
            text: 'text-emerald-700',
            icon: 'text-emerald-600',
            badge: 'bg-emerald-50 text-emerald-700',
            card: 'border-emerald-200 bg-emerald-50/70',
            accent: 'from-emerald-400 to-teal-600',
        };
    }

    return {
        key: 'offline',
        text: 'text-slate-600',
        icon: 'text-slate-500',
        badge: 'bg-slate-100 text-slate-600',
        card: 'border-slate-200 bg-slate-50',
        accent: 'from-slate-400 to-slate-600',
    };
}

export function faultsTone(count) {
    const n = Number(count) || 0;
    if (n <= 0) {
        return {
            key: 'good',
            accent: 'from-emerald-400 to-teal-600',
        };
    }
    if (n < 10) {
        return {
            key: 'warn',
            accent: 'from-amber-300 to-orange-500',
        };
    }

    return {
        key: 'bad',
        accent: 'from-rose-400 to-pink-600',
    };
}
