import {
    Activity,
    BarChart3,
    Cable,
    CreditCard,
    FileText,
    Globe,
    LayoutDashboard,
    Layers,
    Map,
    Radio,
    RefreshCw,
    Router,
    Settings,
    SlidersHorizontal,
    Ticket,
    Users,
    Wallet,
    Wifi,
} from 'lucide-react';

/**
 * Sidebar navigation — dikelompokkan menurut fungsi operasional.
 */
export const adminNav = [
    {
        title: 'Utama',
        items: [
            {
                label: 'Dashboard',
                href: '/admin',
                icon: LayoutDashboard,
                match: ['/admin'],
            },
        ],
    },
    {
        title: 'Pelanggan',
        items: [
            {
                label: 'Pelanggan PPPoE',
                href: '/admin/customers/pppoe',
                icon: Cable,
                match: ['/admin/customers/pppoe'],
                exclude: [
                    '/admin/customers/pppoe/service-profiles',
                    '/admin/customers/pppoe/mikrotik-profiles',
                    '/admin/customers/pppoe/sessions',
                ],
            },
            {
                label: 'Sesi Aktif',
                href: '/admin/customers/pppoe/sessions',
                icon: Activity,
                match: ['/admin/customers/pppoe/sessions'],
            },
            {
                label: 'Paket Layanan',
                href: '/admin/customers/pppoe/service-profiles',
                icon: Layers,
                match: ['/admin/customers/pppoe/service-profiles'],
            },
            {
                label: 'Profile PPPoE',
                href: '/admin/customers/pppoe/mikrotik-profiles',
                icon: SlidersHorizontal,
                match: ['/admin/customers/pppoe/mikrotik-profiles'],
            },
        ],
    },
    {
        title: 'Hotspot',
        items: [
            {
                label: 'Voucher Hotspot',
                href: '/admin/network/hotspot',
                icon: Ticket,
                match: ['/admin/network/hotspot'],
                exclude: [
                    '/admin/network/hotspot/profiles',
                    '/admin/network/hotspot/sessions',
                    '/admin/network/hotspot/reports',
                ],
            },
            {
                label: 'Laporan Voucher',
                href: '/admin/network/hotspot/reports',
                icon: BarChart3,
                match: ['/admin/network/hotspot/reports'],
            },
            {
                label: 'Sesi Aktif',
                href: '/admin/network/hotspot/sessions',
                icon: Activity,
                match: ['/admin/network/hotspot/sessions'],
            },
            {
                label: 'Profile Hotspot',
                href: '/admin/network/hotspot/profiles',
                icon: Wifi,
                match: ['/admin/network/hotspot/profiles'],
            },
        ],
    },
    {
        title: 'Billing',
        items: [
            {
                label: 'Tagihan & Pembayaran',
                href: '/admin/billing',
                icon: CreditCard,
                match: ['/admin/billing'],
                exclude: ['/admin/billing/reports', '/admin/billing/payment-gateway'],
            },
            {
                label: 'Laporan Keuangan',
                href: '/admin/billing/reports',
                icon: BarChart3,
                match: ['/admin/billing/reports'],
            },
            {
                label: 'Payment Gateway',
                href: '/admin/billing/payment-gateway',
                icon: Wallet,
                match: ['/admin/billing/payment-gateway'],
            },
        ],
    },
    {
        title: 'Jaringan',
        items: [
            {
                label: 'Router MikroTik',
                href: '/admin/network/routeros',
                icon: Router,
                match: ['/admin/network/routeros'],
            },
            {
                label: 'GenieACS',
                href: '/admin/network/genieacs',
                icon: Radio,
                match: ['/admin/network/genieacs'],
            },
            {
                label: 'Peta Jaringan',
                href: '/admin/network/map',
                icon: Map,
                match: ['/admin/network/map'],
            },
        ],
    },
    {
        title: 'Website',
        items: [
            {
                label: 'Konten Landing',
                href: '/admin/website/sections',
                icon: FileText,
                match: ['/admin/website/sections', '/admin/sections'],
            },
            {
                label: 'Pengaturan Situs',
                href: '/admin/settings',
                icon: Globe,
                match: ['/admin/settings'],
            },
        ],
    },
    {
        title: 'Sistem',
        items: [
            {
                label: 'Manajemen Pengguna',
                href: '/admin/users',
                icon: Users,
                match: ['/admin/users'],
            },
            {
                label: 'Pengaturan Aplikasi',
                href: '/admin/system',
                icon: Settings,
                match: ['/admin/system'],
                exclude: ['/admin/system/update'],
            },
            {
                label: 'Update',
                href: '/admin/system/update',
                icon: RefreshCw,
                match: ['/admin/system/update'],
            },
        ],
    },
];

export function isNavActive(pathname, item) {
    const excluded = item.exclude?.some(
        (path) => pathname === path || pathname.startsWith(`${path}/`),
    );
    if (excluded) return false;

    if (item.children?.length) {
        return item.children.some((child) => isNavActive(pathname, child));
    }

    if (item.match?.length) {
        return item.match.some((path) => {
            if (path === '/admin') {
                return pathname === '/admin' || pathname === '/admin/';
            }

            return pathname === path || pathname.startsWith(`${path}/`);
        });
    }

    if (!item.href) return false;

    return pathname === item.href || pathname.startsWith(`${item.href}/`);
}

export function filterNavForUser(nav, user) {
    if (user?.role !== 'agen') {
        return nav;
    }

    const allowedHrefs = [
        '/admin',
        '/admin/customers/pppoe',
        '/admin/customers/pppoe/sessions',
        '/admin/billing',
    ];

    return nav
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => allowedHrefs.includes(item.href)),
        }))
        .filter((group) => group.items.length > 0);
}
