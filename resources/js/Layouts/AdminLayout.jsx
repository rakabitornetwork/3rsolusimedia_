import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, PanelLeftClose, PanelLeftOpen, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import FlashToast from '../Components/FlashToast';
import UserAvatar from '../Components/UserAvatar';
import { adminNav, isNavActive } from '../Config/adminNav';
import Logo from '../Icons/Logo';

function NavItem({ item, pathname, showLabels, onNavigate }) {
    const Icon = item.icon;
    const hasChildren = Boolean(item.children?.length);
    const childActive = hasChildren && item.children.some((child) => isNavActive(pathname, child));
    const active = hasChildren ? childActive : isNavActive(pathname, item);
    const [open, setOpen] = useState(childActive);

    useEffect(() => {
        if (childActive) setOpen(true);
    }, [childActive]);

    if (hasChildren) {
        return (
            <li>
                <button
                    type="button"
                    title={item.label}
                    onClick={() => {
                        if (!showLabels) {
                            router.visit(item.children[0]?.href || item.href);
                            return;
                        }
                        setOpen((value) => !value);
                    }}
                    className={`group flex w-full items-center gap-3 rounded-md px-2.5 py-2 text-sm transition ${
                        active
                            ? 'bg-signal-bright/15 text-signal-bright'
                            : 'text-white/70 hover:bg-white/5 hover:text-white'
                    } ${!showLabels ? 'justify-center' : ''}`}
                >
                    <Icon className="h-4 w-4 shrink-0" strokeWidth={1.75} />
                    {showLabels && (
                        <>
                            <span className="min-w-0 flex-1 truncate text-left">{item.label}</span>
                            <ChevronDown
                                className={`h-3.5 w-3.5 shrink-0 transition ${open ? 'rotate-180' : ''}`}
                            />
                        </>
                    )}
                </button>

                {showLabels && open && (
                    <ul className="mt-1 ml-4 space-y-1 border-l border-white/10 pl-2">
                        {item.children.map((child) => {
                            const childIsActive = isNavActive(pathname, child);
                            return (
                                <li key={child.label}>
                                    <Link
                                        href={child.href}
                                        onClick={onNavigate}
                                        className={`block rounded-md px-2.5 py-1.5 text-sm transition ${
                                            childIsActive
                                                ? 'bg-white/10 text-signal-bright'
                                                : 'text-white/60 hover:bg-white/5 hover:text-white'
                                        }`}
                                    >
                                        {child.label}
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </li>
        );
    }

    return (
        <li>
            <Link
                href={item.href}
                onClick={onNavigate}
                title={item.label}
                className={`group flex items-center gap-3 rounded-md px-2.5 py-2 text-sm transition ${
                    active
                        ? 'bg-signal-bright/15 text-signal-bright'
                        : 'text-white/70 hover:bg-white/5 hover:text-white'
                } ${!showLabels ? 'justify-center' : ''}`}
            >
                <Icon className="h-4 w-4 shrink-0" strokeWidth={1.75} />
                {showLabels && (
                    <span className="flex min-w-0 flex-1 items-center justify-between gap-2">
                        <span className="truncate">{item.label}</span>
                        {item.soon && (
                            <span className="rounded bg-white/10 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-white/50 uppercase">
                                Soon
                            </span>
                        )}
                    </span>
                )}
            </Link>
        </li>
    );
}

function SidebarNavBody({ companyName, user, pathname, showLabels, onNavigate }) {
    const [navScrolling, setNavScrolling] = useState(false);
    const hideScrollTimer = useRef(null);

    const revealScrollbar = () => {
        setNavScrolling(true);
        if (hideScrollTimer.current) {
            window.clearTimeout(hideScrollTimer.current);
        }
        hideScrollTimer.current = window.setTimeout(() => {
            setNavScrolling(false);
        }, 1000);
    };

    useEffect(
        () => () => {
            if (hideScrollTimer.current) {
                window.clearTimeout(hideScrollTimer.current);
            }
        },
        [],
    );

    return (
        <>
            <div className="border-b border-white/10 px-4 py-4">
                <Link
                    href="/admin"
                    className="flex items-center gap-3 text-white"
                    onClick={onNavigate}
                >
                    <Logo className="h-8 w-8 shrink-0" markOnly />
                    {showLabels && (
                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold tracking-tight">{companyName}</p>
                            <p className="truncate text-[11px] text-white/45">Panel Admin</p>
                        </div>
                    )}
                </Link>
            </div>

            <nav
                className={`admin-sidebar-scroll flex-1 space-y-5 overflow-y-auto px-3 py-4 ${
                    navScrolling ? 'is-scrolling' : ''
                }`}
                onScroll={revealScrollbar}
                onTouchMove={revealScrollbar}
            >
                {adminNav.map((group) => (
                    <div key={group.title}>
                        {showLabels && (
                            <p className="mb-2 px-2 text-[10px] font-semibold tracking-[0.16em] text-white/35 uppercase">
                                {group.title}
                            </p>
                        )}
                        <ul className="space-y-1">
                            {group.items.map((item) => (
                                <NavItem
                                    key={item.label}
                                    item={item}
                                    pathname={pathname}
                                    showLabels={showLabels}
                                    onNavigate={onNavigate}
                                />
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="border-t border-white/10 p-3">
                {showLabels && user && (
                    <div className="mb-3 flex items-center gap-3 px-1">
                        <UserAvatar
                            name={user.name}
                            role={user.role}
                            src={user.avatar_url}
                            initials={user.initials}
                            size="md"
                        />
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-white">{user.name}</p>
                            <p className="truncate text-xs text-white/45">{user.email}</p>
                            {user.role_label && (
                                <p className="mt-1 text-[10px] font-semibold tracking-wide text-signal-bright uppercase">
                                    {user.role_label}
                                </p>
                            )}
                        </div>
                    </div>
                )}
                {!showLabels && user && (
                    <div className="mb-3 flex justify-center">
                        <UserAvatar
                            name={user.name}
                            role={user.role}
                            src={user.avatar_url}
                            initials={user.initials}
                            size="sm"
                        />
                    </div>
                )}
                <div className={`flex gap-2 ${!showLabels ? 'flex-col' : ''}`}>
                    <a
                        href="/"
                        target="_blank"
                        rel="noreferrer"
                        className="flex-1 rounded-md border border-white/10 px-3 py-2 text-center text-xs font-semibold text-white/70 hover:bg-white/5 hover:text-white"
                    >
                        {showLabels ? 'Lihat Website' : 'Web'}
                    </a>
                    <Link
                        href="/admin/logout"
                        method="post"
                        as="button"
                        className="flex-1 rounded-md bg-gradient-to-br from-red-500 to-red-900 px-3 py-2 text-center text-xs font-semibold text-white hover:brightness-110"
                    >
                        Logout
                    </Link>
                </div>
            </div>
        </>
    );
}

export default function AdminLayout({ children, title, subtitle }) {
    const page = usePage();
    const pathname = String(page.url || '').split('?')[0];
    const user = page.props.auth?.user;
    const companyName = page.props.app?.company_name || 'Perusahaan';
    const [mobileOpen, setMobileOpen] = useState(false);
    const [mobileVisible, setMobileVisible] = useState(false);
    const [collapsed, setCollapsed] = useState(false);
    const showLabels = !collapsed || mobileVisible;

    const openMobile = () => {
        setMobileVisible(true);
        // Double rAF agar transition dari state tertutup benar-benar terpicu.
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => setMobileOpen(true));
        });
    };

    const closeMobile = () => {
        setMobileOpen(false);
        window.setTimeout(() => setMobileVisible(false), 300);
    };

    useEffect(() => {
        if (!mobileVisible) return undefined;

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [mobileVisible]);

    return (
        <div className="min-h-screen bg-mist">
            <FlashToast />
            <aside
                className={`admin-sidebar fixed inset-y-0 left-0 z-40 hidden flex-col bg-ink text-white transition-all duration-300 lg:flex ${
                    collapsed ? 'w-[72px]' : 'w-64'
                }`}
            >
                <SidebarNavBody
                    companyName={companyName}
                    user={user}
                    pathname={pathname}
                    showLabels={showLabels}
                    onNavigate={closeMobile}
                />
                <button
                    type="button"
                    onClick={() => setCollapsed((v) => !v)}
                    className="absolute top-20 -right-3 flex h-6 w-6 items-center justify-center rounded-full border border-ink/10 bg-white text-ink shadow-sm"
                    aria-label={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
                >
                    {collapsed ? (
                        <PanelLeftOpen className="h-3.5 w-3.5" />
                    ) : (
                        <PanelLeftClose className="h-3.5 w-3.5" />
                    )}
                </button>
            </aside>

            {mobileVisible && (
                <div className="fixed inset-0 z-50 lg:hidden" aria-modal="true" role="dialog">
                    <button
                        type="button"
                        className={`absolute inset-0 bg-ink/50 transition-opacity duration-300 ease-out ${
                            mobileOpen ? 'opacity-100' : 'opacity-0'
                        }`}
                        aria-label="Tutup menu"
                        onClick={closeMobile}
                    />
                    <aside
                        className={`admin-sidebar absolute inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-ink text-white shadow-xl transition-transform duration-300 ease-out ${
                            mobileOpen ? 'translate-x-0' : '-translate-x-full'
                        }`}
                    >
                        <button
                            type="button"
                            className="absolute top-3 right-3 rounded-md p-1.5 text-white/70 hover:bg-white/10 hover:text-white"
                            onClick={closeMobile}
                            aria-label="Tutup"
                        >
                            <X className="h-4 w-4" />
                        </button>
                        <SidebarNavBody
                            companyName={companyName}
                            user={user}
                            pathname={pathname}
                            showLabels={showLabels}
                            onNavigate={closeMobile}
                        />
                    </aside>
                </div>
            )}

            <div
                className={`flex min-h-screen flex-col transition-all duration-300 ${
                    collapsed ? 'lg:pl-[72px]' : 'lg:pl-64'
                }`}
            >
                <header className="sticky top-0 z-30 border-b border-ink/10 bg-white/90 backdrop-blur">
                    <div className="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                className="rounded-md border border-ink/10 p-2 text-ink lg:hidden"
                                onClick={openMobile}
                                aria-label="Buka menu"
                                aria-expanded={mobileOpen}
                            >
                                <Menu className="h-4 w-4" />
                            </button>
                            <div className="min-w-0">
                                {title && (
                                    <h1 className="font-display truncate text-lg font-bold tracking-tight text-ink sm:text-xl">
                                        {title}
                                    </h1>
                                )}
                                {subtitle && (
                                    <p className="truncate text-xs text-ink-soft sm:text-sm">
                                        {subtitle}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="hidden items-center gap-3 sm:flex">
                            {user?.role_label && (
                                <span className="border border-ink/10 bg-mist px-2.5 py-1 text-[11px] font-semibold tracking-wide text-ink-soft uppercase">
                                    {user.role_label}
                                </span>
                            )}
                            <p className="text-xs text-ink-soft">Panel Admin {companyName}</p>
                        </div>
                    </div>
                </header>

                {user && user.can_write === false && (
                    <div className="border-b border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 sm:px-6 lg:px-8">
                        Mode baca saja — akun Teknisi hanya dapat melihat data, tidak dapat mengubah.
                    </div>
                )}

                <main className="flex-1 px-4 py-6 sm:px-6 lg:px-8">{children}</main>

                <footer className="mt-auto border-t border-ink/10 bg-white">
                    <div className="px-4 py-4 text-center text-xs text-ink-soft sm:px-6 lg:px-8">
                        <p>
                            © {new Date().getFullYear()}{' '}
                            <span className="font-semibold text-ink">{companyName}</span>
                            <span className="text-ink-soft/70"> · Panel Admin</span>
                        </p>
                    </div>
                </footer>
            </div>
        </div>
    );
}
