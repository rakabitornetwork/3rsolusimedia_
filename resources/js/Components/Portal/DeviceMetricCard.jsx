/**
 * Kartu metrik ONU untuk portal — layout vertikal agar label & nilai presisi di HP.
 */
export default function DeviceMetricCard({
    icon: Icon,
    label,
    shortLabel,
    value,
    unit,
    tone,
    title,
}) {
    return (
        <div
            title={title || label}
            className={`min-w-0 border px-2.5 py-2.5 sm:px-3 sm:py-3 ${tone?.card || 'border-ink/10 bg-mist/40'}`}
        >
            <div className="flex items-center gap-1.5">
                {Icon ? (
                    <Icon
                        className={`h-3.5 w-3.5 shrink-0 sm:h-4 sm:w-4 ${tone?.icon || 'text-ink-soft'}`}
                        strokeWidth={2.25}
                    />
                ) : null}
                <p className="min-w-0 truncate text-[10px] font-semibold tracking-[0.06em] text-ink-soft uppercase sm:text-[11px] sm:tracking-[0.08em]">
                    {shortLabel ? (
                        <>
                            <span className="sm:hidden">{shortLabel}</span>
                            <span className="hidden sm:inline">{label}</span>
                        </>
                    ) : (
                        label
                    )}
                </p>
            </div>
            <p
                className={`mt-1.5 truncate font-mono text-sm font-semibold leading-tight tabular-nums sm:text-base ${
                    tone?.text || 'text-ink'
                }`}
            >
                {value || '—'}
                {unit && value && value !== '—' ? (
                    <span className="ml-0.5 text-[10px] font-medium text-ink-soft sm:text-xs">
                        {unit}
                    </span>
                ) : null}
            </p>
        </div>
    );
}

/** Ambil angka & satuan dari label GenieACS seperti "-23.4 dBm" / "48 °C". */
export function splitMetricLabel(label) {
    if (label == null || label === '' || label === '—') {
        return { value: '—', unit: '' };
    }
    const text = String(label).trim();
    const match = text.match(/^(-?\d+(?:[.,]\d+)?)\s*(.*)$/);
    if (!match) {
        return { value: text, unit: '' };
    }
    return {
        value: match[1].replace(',', '.'),
        unit: (match[2] || '').trim(),
    };
}
