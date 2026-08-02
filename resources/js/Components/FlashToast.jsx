import { usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const DISPLAY_MS = 4200;
const EXIT_MS = 320;

const SUPPRESS_TOAST_KEY = 'update-suppress-toast';

function consumePullToastSuppression() {
    try {
        if (sessionStorage.getItem(SUPPRESS_TOAST_KEY) === '1') {
            sessionStorage.removeItem(SUPPRESS_TOAST_KEY);
            return true;
        }
    } catch {
        // ignore
    }
    return false;
}

export default function FlashToast() {
    const { flash } = usePage().props;
    const [toasts, setToasts] = useState([]);
    const seenRef = useRef('');
    const timersRef = useRef(new Map());

    const removeToast = useCallback((id) => {
        setToasts((current) => current.filter((toast) => toast.id !== id));
        const timer = timersRef.current.get(id);
        if (timer) {
            window.clearTimeout(timer);
            timersRef.current.delete(id);
        }
    }, []);

    const dismiss = useCallback(
        (id) => {
            setToasts((current) =>
                current.map((toast) =>
                    toast.id === id ? { ...toast, leaving: true } : toast,
                ),
            );

            const existing = timersRef.current.get(id);
            if (existing) window.clearTimeout(existing);

            const timer = window.setTimeout(() => removeToast(id), EXIT_MS);
            timersRef.current.set(id, timer);
        },
        [removeToast],
    );

    useEffect(() => {
        // Flash hasil Pull ditampilkan di terminal Ubuntu, bukan toast pojok.
        if (consumePullToastSuppression()) {
            return;
        }

        const next = [];
        if (flash?.success) {
            next.push({ type: 'success', message: String(flash.success) });
        }
        if (flash?.error) {
            next.push({ type: 'error', message: String(flash.error) });
        }
        if (next.length === 0) return;

        const signature = next.map((item) => `${item.type}:${item.message}`).join('|');
        if (signature === seenRef.current) return;
        seenRef.current = signature;

        const createdAt = Date.now();
        const created = next.map((item, index) => ({
            id: `${createdAt}-${index}`,
            ...item,
            leaving: false,
        }));

        setToasts((current) => [...current, ...created]);

        created.forEach((toast) => {
            const timer = window.setTimeout(() => dismiss(toast.id), DISPLAY_MS);
            timersRef.current.set(toast.id, timer);
        });
    }, [flash?.success, flash?.error, dismiss]);

    useEffect(
        () => () => {
            timersRef.current.forEach((timer) => window.clearTimeout(timer));
            timersRef.current.clear();
        },
        [],
    );

    if (toasts.length === 0) return null;

    return (
        <div className="pointer-events-none fixed top-4 right-4 z-[80] flex w-[min(100%-2rem,22rem)] flex-col gap-2 sm:top-6 sm:right-6">
            {toasts.map((toast) => {
                const isError = toast.type === 'error';
                const Icon = isError ? XCircle : CheckCircle2;

                return (
                    <div
                        key={toast.id}
                        className={`pointer-events-auto flex items-start gap-3 border px-4 py-3 shadow-lg ${
                            isError
                                ? 'border-red-200 bg-red-50 text-red-800'
                                : 'border-signal/30 bg-white text-signal-deep'
                        } ${toast.leaving ? 'toast-slide-out' : 'toast-slide-in'}`}
                        role="status"
                        aria-live="polite"
                    >
                        <Icon
                            className={`mt-0.5 h-4 w-4 shrink-0 ${
                                isError ? 'text-red-600' : 'text-signal'
                            }`}
                            strokeWidth={1.75}
                        />
                        <p className="min-w-0 flex-1 text-sm font-medium leading-snug">
                            {toast.message}
                        </p>
                        <button
                            type="button"
                            onClick={() => dismiss(toast.id)}
                            className="shrink-0 rounded p-0.5 text-current/50 transition hover:bg-black/5 hover:text-current"
                            aria-label="Tutup"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
