import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

const MENU_GAP = 4;
const VIEW_PAD = 8;

export default function OverflowMenu({
    trigger,
    triggerClassName,
    triggerTitle,
    menuClassName = '',
    align = 'end',
    children,
}) {
    const [open, setOpen] = useState(false);
    const triggerRef = useRef(null);
    const menuRef = useRef(null);
    const [coords, setCoords] = useState({ top: 0, left: 0, ready: false });

    const close = () => setOpen(false);

    useLayoutEffect(() => {
        if (!open) {
            setCoords({ top: 0, left: 0, ready: false });
            return undefined;
        }

        const place = () => {
            const triggerEl = triggerRef.current;
            const menuEl = menuRef.current;
            if (!triggerEl || !menuEl) return;

            const rect = triggerEl.getBoundingClientRect();
            const menuWidth = menuEl.offsetWidth;
            const menuHeight = menuEl.offsetHeight;

            let top = rect.bottom + MENU_GAP;
            if (top + menuHeight > window.innerHeight - VIEW_PAD) {
                const above = rect.top - MENU_GAP - menuHeight;
                top = above >= VIEW_PAD ? above : Math.max(VIEW_PAD, window.innerHeight - menuHeight - VIEW_PAD);
            }

            let left = align === 'start' ? rect.left : rect.right - menuWidth;
            left = Math.min(left, window.innerWidth - menuWidth - VIEW_PAD);
            left = Math.max(VIEW_PAD, left);

            setCoords({ top, left, ready: true });
        };

        place();
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
        return () => {
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
        };
    }, [open, align]);

    useEffect(() => {
        if (!open) return undefined;

        const onPointerDown = (event) => {
            const target = event.target;
            if (triggerRef.current?.contains(target) || menuRef.current?.contains(target)) {
                return;
            }
            close();
        };
        const onKey = (event) => {
            if (event.key === 'Escape') close();
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <>
            <button
                ref={triggerRef}
                type="button"
                className={triggerClassName}
                title={triggerTitle}
                aria-expanded={open}
                aria-haspopup="menu"
                onClick={() => setOpen((value) => !value)}
            >
                {trigger}
            </button>
            {open
                ? createPortal(
                      <div
                          ref={menuRef}
                          role="menu"
                          className={`admin-row-menu ${menuClassName}`.trim()}
                          style={{
                              position: 'fixed',
                              top: coords.top,
                              left: coords.left,
                              right: 'auto',
                              visibility: coords.ready ? 'visible' : 'hidden',
                          }}
                      >
                          {typeof children === 'function' ? children(close) : children}
                      </div>,
                      document.body,
                  )
                : null}
        </>
    );
}
