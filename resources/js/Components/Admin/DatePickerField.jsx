import { format, parseISO } from 'date-fns';
import { id as localeId } from 'date-fns/locale';
import { CalendarDays } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { DayPicker } from 'react-day-picker';
import 'react-day-picker/style.css';

export default function DatePickerField({
    label,
    value,
    onChange,
    error,
    required = false,
}) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    const selected = value ? parseISO(value) : undefined;

    useEffect(() => {
        const onClickOutside = (event) => {
            if (!rootRef.current?.contains(event.target)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    return (
        <label className="relative block text-sm font-medium text-ink" ref={rootRef}>
            {label}
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="mt-1.5 flex w-full items-center justify-between border border-ink/15 bg-white px-3 py-2.5 text-left text-sm outline-none focus:border-signal"
            >
                <span className={selected ? 'text-ink' : 'text-ink/40'}>
                    {selected
                        ? format(selected, 'd MMMM yyyy', { locale: localeId })
                        : 'Pilih tanggal'}
                </span>
                <CalendarDays className="h-4 w-4 text-signal-deep" />
            </button>

            {open && (
                <div className="absolute z-30 mt-2 rounded-md border border-ink/10 bg-white p-3 shadow-lg">
                    <DayPicker
                        mode="single"
                        selected={selected}
                        onSelect={(date) => {
                            if (!date) return;
                            onChange(format(date, 'yyyy-MM-dd'));
                            setOpen(false);
                        }}
                        locale={localeId}
                    />
                </div>
            )}

            {required && <input type="hidden" value={value || ''} required />}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}
