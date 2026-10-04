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
    min,
    compact = false,
}) {
    const [open, setOpen] = useState(false);
    const [openUp, setOpenUp] = useState(false);
    const rootRef = useRef(null);
    const selected = value ? parseISO(value) : undefined;
    const minDate = min ? parseISO(min) : undefined;

    const toggle = () => {
        setOpen((current) => {
            const next = !current;
            if (next) {
                const rect = rootRef.current?.getBoundingClientRect();
                setOpenUp(Boolean(rect && rect.bottom > window.innerHeight * 0.62));
            }
            return next;
        });
    };

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
        <label className={`relative block font-medium text-ink ${compact ? 'text-xs' : 'text-sm'}`} ref={rootRef}>
            {label}
            <button
                type="button"
                onClick={toggle}
                className={`mt-1.5 flex w-full items-center justify-between border border-ink/15 bg-white px-3 text-left outline-none focus:border-signal ${
                    compact ? 'py-2 text-xs' : 'py-2.5 text-sm'
                }`}
            >
                <span className={selected ? 'text-ink' : 'text-ink/40'}>
                    {selected
                        ? format(selected, 'd MMMM yyyy', { locale: localeId })
                        : 'Pilih tanggal'}
                </span>
                <CalendarDays className="h-4 w-4 text-signal-deep" />
            </button>

            {open && (
                <div
                    className={`absolute z-30 rounded-md border border-ink/10 bg-white p-3 shadow-lg ${
                        openUp ? 'bottom-full mb-2' : 'mt-2'
                    }`}
                >
                    <DayPicker
                        mode="single"
                        selected={selected}
                        disabled={minDate ? { before: minDate } : undefined}
                        startMonth={minDate}
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
