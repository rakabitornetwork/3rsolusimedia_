import {
    Activity,
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
                exclude: ['/admin/network/hotspot/profiles'],
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
                soon: true,
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
