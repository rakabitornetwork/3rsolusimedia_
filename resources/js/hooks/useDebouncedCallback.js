import { useEffect, useRef } from 'react';

/**
 * Returns a stable debounced wrapper around the latest callback.
 * Useful for live search while typing without flooding Inertia requests.
 */
export default function useDebouncedCallback(callback, delay = 350) {
    const callbackRef = useRef(callback);
    const timerRef = useRef(null);

    useEffect(() => {
        callbackRef.current = callback;
    });

    useEffect(
        () => () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
        },
        [],
    );

    const debounced = (...args) => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }
        timerRef.current = setTimeout(() => {
            callbackRef.current(...args);
        }, delay);
    };

    debounced.cancel = () => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }
    };

    return debounced;
}
