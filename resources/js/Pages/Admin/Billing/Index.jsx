import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Ban,
    CalendarRange,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Coins,
    Eye,
    FilePlus2,
    Hourglass,
    MoreHorizontal,
    Printer,
    Search,
    Send,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    WalletCards,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import LocalPagination from '../../../Components/Admin/LocalPagination';
import OverflowMenu from '../../../Components/Admin/OverflowMenu';
import QuickPayMenu from '../../../Components/Admin/QuickPayMenu';
import StatCard from '../../../Components/Admin/StatCard';
import AdminLayout from '../../../Layouts/AdminLayout';
import { keepPage } from '../../../lib/keepPage';
import { bulkBillingWhatsapp, sendBillingWhatsapp } from '../../../lib/billingWhatsapp';
import { matchesSearch, paginateItems } from '../../../lib/search';

const PER_PAGE_OPTIONS = [20, 50, 100, 200, 500];

function hideOldPaidQueryValue(value) {
    if (value === false || value === 0 || value === '0') {
        return 0;
    }

    return 1;
}

function SortableHeader({ label, column, sort, direction, onSort, className = '' }) {
    const active = sort === column;

    return (
        <th className={`px-4 py-3 font-semibold ${className}`}>
            <span className="inline-flex items-center gap-1.5">
                <span>{label}</span>
                <span className="inline-flex flex-col -space-y-1">
                    <button
                        type="button"
                        onClick={() => onSort(column, 'asc')}
                        title={`Urutkan ${label} A → Z`}
                        className={`leading-none ${
                            active && direction === 'asc'
                                ? 'text-signal-deep'
                                : 'text-ink-soft/40 hover:text-ink-soft'
                        }`}
                    >
                        <ChevronUp className="h-3 w-3" strokeWidth={2.5} />
                    </button>
                    <button
                        type="button"
                        onClick={() => onSort(column, 'desc')}
                        title={`Urutkan ${label} Z → A`}
                        className={`leading-none ${
                            active && direction === 'desc'
                                ? 'text-signal-deep'
                                : 'text-ink-soft/40 hover:text-ink-soft'
                        }`}
                    >
                        <ChevronDown className="h-3 w-3" strokeWidth={2.5} />
                    </button>
                </span>
            </span>
        </th>
    );
}

function StatusBadge({ status, overdue, graceUntil }) {
    if (graceUntil && status === 'unpaid') {
        return (
            <span className="bg-sky-50 px-2 py-1 text-xs font-semibold text-sky-700">
                Grace s/d {graceUntil}
            </span>
        );
    }

    if (overdue && status === 'unpaid') {
        return (
            <span className="bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                Jatuh tempo
            </span>
        );
    }

    const map = {
        unpaid: 'bg-ink/10 text-ink-soft',
        paid: 'bg-signal/15 text-signal-deep',
        void: 'bg-red-50 text-red-600',
    };

    const label = {
        unpaid: 'Belum bayar',
        paid: 'Lunas',
        void: 'Dibatalkan',
    };

    return (
        <span className={`px-2 py-1 text-xs font-semibold ${map[status] || map.unpaid}`}>
            {label[status] || status}
        </span>
    );
}

function MoreActions({ invoice, onRemove, whatsapp }) {
    const customer = invoice.customer;
    const canCombine =
        Boolean(customer?.id) &&
        !(
            invoice.status === 'unpaid' &&
            (invoice.billing_months > 1 || invoice.type === 'multi_month')
        );

    const grantGrace = ({ days, months } = {}) => {
        const label = months ? `+${months} bulan` : `+${days} hari`;
        const note = window.prompt(
            `Tempo isolir ${label} (tagihan tetap belum lunas, jatuh tempo tidak digeser).\nProfil paket akan dipulihkan.\nCatatan opsional:`,
            customer.grace_note || '',
        );
        if (note === null) return;
        router.post(
            `/admin/billing/customers/${customer.id}/grace`,
            { days, months, note: note || undefined },
            keepPage,
        );
    };

    const clearGrace = () => {
        if (!window.confirm('Cabut toleransi isolir untuk pelanggan ini?')) return;
        router.delete(`/admin/billing/customers/${customer.id}/grace`, keepPage);
    };

    const combine = () => {
        const price = Number(customer.package_price || invoice?.package_price || 0);
        const totalLabel = `Rp ${Number(price * 2).toLocaleString('id-ID')}`;
        if (
            !window.confirm(
                `Buat tagihan gabungan 2 bulan untuk ${customer.name}?\nTotal: ${totalLabel}\n\nJika pelanggan sedang isolir, isolir dicabut (tempo 2 bulan) tanpa perlu lunas dulu.\nInvoice unpaid bulanan/prorata yang ada akan diganti.`,
            )
        ) {
            return;
        }
        router.post(`/admin/billing/customers/${customer.id}/combine-billing`, { months: 2 }, keepPage);
    };

    return (
        <OverflowMenu
            trigger={<MoreHorizontal className="h-4 w-4" />}
            triggerClassName="admin-icon-btn"
            triggerTitle="Aksi lainnya"
            menuClassName="py-1"
        >
            {(close) => (
                <>
                    <Link
                        href={`/admin/billing/invoices/${invoice.id}`}
                        className="admin-row-menu-item"
                        onClick={close}
                    >
                        <Eye className="h-3.5 w-3.5 text-ink-soft" />
                        Detail tagihan
                    </Link>

                    {customer?.id ? (
                        <>
                            <p className="admin-row-menu-label">Kirim WhatsApp</p>
                            {whatsapp?.enabled
                                ? (whatsapp.templates || []).map((item) => (
                                      <button
                                          key={item.value}
                                          type="button"
                                          onClick={() => {
                                              close();
                                              sendBillingWhatsapp(
                                                  invoice.id,
                                                  item.value,
                                                  item.label,
                                              );
                                          }}
                                          className="admin-row-menu-item"
                                      >
                                          <Send className="h-3.5 w-3.5 text-ink-soft" />
                                          {item.label}
                                      </button>
                                  ))
                                : (
                                      <p className="px-3 py-2 text-xs text-ink-soft">
                                          Aktifkan WhatsApp di Notifikasi & Bot.
                                      </p>
                                  )}
                        </>
                    ) : null}

                    {customer?.id ? (
                        <>
                            <p className="admin-row-menu-label">Toleransi isolir</p>
                            {[3, 7, 14].map((days) => (
                                <button
                                    key={days}
                                    type="button"
                                    onClick={() => {
                                        close();
                                        grantGrace({ days });
                                    }}
                                    className="admin-row-menu-item"
                                >
                                    <Clock className="h-3.5 w-3.5 text-ink-soft" />
                                    +{days} hari
                                </button>
                            ))}
                            <button
                                type="button"
                                onClick={() => {
                                    close();
                                    grantGrace({ months: 1 });
                                }}
                                className="admin-row-menu-item"
                            >
                                <Clock className="h-3.5 w-3.5 text-ink-soft" />
                                +1 bulan
                            </button>
                            {customer.has_active_grace ? (
                                <button
                                    type="button"
                                    onClick={() => {
                                        close();
                                        clearGrace();
                                    }}
                                    className="admin-row-menu-item is-danger"
                                >
                                    <Ban className="h-3.5 w-3.5" />
                                    Cabut toleransi
                                </button>
                            ) : null}
                        </>
                    ) : null}

                    {canCombine ? (
                        <button
                            type="button"
                            onClick={() => {
                                close();
                                combine();
                            }}
                            className="admin-row-menu-item"
                        >
                            <CalendarRange className="h-3.5 w-3.5 text-ink-soft" />
                            Gabung 2 bulan
                        </button>
                    ) : null}

                    <div className="my-1 border-t border-ink/5" />
                    <button
                        type="button"
                        onClick={() => {
                            close();
                            onRemove(invoice);
                        }}
                        className="admin-row-menu-item is-danger"
                    >
                        <Trash2 className="h-3.5 w-3.5" />
                        {invoice.status === 'paid' ? 'Batalkan (void)' : 'Hapus tagihan'}
                    </button>
                </>
            )}
        </OverflowMenu>
    );
}

export default function Index({
    invoices = [],
    filters,
    stats,
    payment_methods,
    routers = [],
    whatsapp = { enabled: false, templates: [] },
}) {
    const { auth } = usePage().props;
    const canPay = auth?.user?.can_record_payment !== false;
    const [query, setQuery] = useState(filters.q || '');
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(Number(filters.per_page) || 20);
    const [selected, setSelected] = useState([]);
    const [bulkMethod, setBulkMethod] = useState('cash');
    const [bulkWaTemplate, setBulkWaTemplate] = useState(
        whatsapp.templates?.[0]?.value || 'reminder',
    );
    const [bulkProcessing, setBulkProcessing] = useState(false);
    const [bulkWaProcessing, setBulkWaProcessing] = useState(false);
    const [showPrint, setShowPrint] = useState(false);

    const allInvoices = Array.isArray(invoices) ? invoices : invoices?.data || [];
    const filtered = useMemo(
        () =>
            allInvoices.filter((item) =>
                matchesSearch(
                    query,
                    item.number,
                    item.package_name,
                    item.customer?.name,
                    item.customer?.username,
                    item.customer?.phone,
                ),
            ),
        [allInvoices, query],
    );
    const paged = useMemo(() => paginateItems(filtered, page, perPage), [filtered, page, perPage]);
    const rows = paged.data;
    const pageIds = useMemo(() => rows.map((item) => item.id), [rows]);
    const allPageSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));
    const selectedUnpaidCount = useMemo(() => {
        const unpaidIds = new Set(
            allInvoices.filter((item) => item.status === 'unpaid').map((item) => item.id),
        );
        return selected.filter((id) => unpaidIds.has(id)).length;
    }, [allInvoices, selected]);

    const applyFilters = (key, value) => {
        setSelected([]);
        setPage(1);
        router.get(
            '/admin/billing',
            {
                ...filters,
                [key]: value,
                overdue: key === 'overdue' ? value : filters.overdue || false,
                hide_old_paid:
                    key === 'hide_old_paid'
                        ? hideOldPaidQueryValue(value)
                        : hideOldPaidQueryValue(filters.hide_old_paid),
                grace: key === 'grace' ? value : filters.grace || '',
                customer_status:
                    key === 'customer_status' ? value : filters.customer_status || '',
            },
            { preserveState: true, replace: true },
        );
    };

    const changePerPage = (value) => {
        setPerPage(value);
        applyFilters('per_page', value);
    };

    const applySort = (column, direction) => {
        setSelected([]);
        setPage(1);
        router.get(
            '/admin/billing',
            {
                ...filters,
                sort: column,
                direction,
                overdue: filters.overdue || false,
                hide_old_paid: hideOldPaidQueryValue(filters.hide_old_paid),
                grace: filters.grace || '',
                customer_status: filters.customer_status || '',
            },
            { preserveState: true, replace: true },
        );
    };

    const toggleOne = (id) => {
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id],
        );
    };

    const togglePage = () => {
        if (allPageSelected) {
            setSelected((prev) => prev.filter((id) => !pageIds.includes(id)));
            return;
        }
        setSelected((prev) => [...new Set([...prev, ...pageIds])]);
    };

    const bulkPay = () => {
        if (selectedUnpaidCount === 0) {
            window.alert('Pilih minimal satu tagihan berstatus belum bayar.');
            return;
        }

        const methodLabel =
            payment_methods.find((item) => item.value === bulkMethod)?.label || bulkMethod;

        if (
            !window.confirm(
                `Tandai lunas ${selectedUnpaidCount} tagihan terpilih?\nMetode: ${methodLabel}`,
            )
        ) {
            return;
        }

        setBulkProcessing(true);
        router.post(
            '/admin/billing/bulk-pay',
            {
                ids: selected,
                method: bulkMethod,
            },
            {
                ...keepPage,
                onFinish: () => {
                    setBulkProcessing(false);
                    setSelected([]);
                },
            },
        );
    };

    const bulkWhatsapp = () => {
        if (selected.length === 0) {
            window.alert('Pilih minimal satu tagihan.');
            return;
        }
        if (!whatsapp?.enabled) {
            window.alert('WhatsApp belum aktif. Aktifkan di Notifikasi & Bot.');
            return;
        }

        const choice =
            (whatsapp.templates || []).find((item) => item.value === bulkWaTemplate) || {
                value: bulkWaTemplate,
                label: bulkWaTemplate,
            };

        if (
            !window.confirm(
                `Kirim WhatsApp "${choice.label}" ke ${selected.length} tagihan terpilih?`,
            )
        ) {
            return;
        }

        setBulkWaProcessing(true);
        bulkBillingWhatsapp(selected, choice.value, {
            onFinish: () => setBulkWaProcessing(false),
        });
    };

    const generate = () => {
        if (
            !window.confirm(
                'Buat tagihan untuk pelanggan aktif yang jatuh tempo dalam 7 hari (atau sudah lewat) dan belum punya tagihan?',
            )
        ) {
            return;
        }
        router.post('/admin/billing/generate', {}, keepPage);
    };

    const openPrint = () => {
        const params = new URLSearchParams({ autoprint: '1' });

        if (query.trim()) {
            params.set('q', query.trim());
        }
        if (filters.router_id) {
            params.set('router_id', String(filters.router_id));
        }
        if (filters.status) {
            params.set('status', String(filters.status));
        }
        if (filters.overdue) {
            params.set('overdue', '1');
        }
        if (filters.grace) {
            params.set('grace', String(filters.grace));
        }
        if (filters.customer_status) {
            params.set('customer_status', String(filters.customer_status));
        }
        params.set('hide_old_paid', String(hideOldPaidQueryValue(filters.hide_old_paid)));

        window.open(
            `/admin/billing/print?${params.toString()}`,
            '_blank',
            'noopener,noreferrer',
        );
    };

    const printFilterSummary = [
        filters.router_id
            ? routers.find((item) => String(item.id) === String(filters.router_id))?.name ||
              'Router terpilih'
            : null,
        filters.status === 'unpaid'
            ? 'Belum bayar'
            : filters.status === 'paid'
              ? 'Lunas'
              : filters.status === 'void'
                ? 'Dibatalkan'
                : null,
        filters.overdue ? 'Jatuh tempo saja' : null,
        filters.grace === 'active' ? 'Grace aktif' : filters.grace === 'none' ? 'Tanpa grace' : null,
        filters.customer_status === 'isolated' ? 'Isolir saja' : null,
        filters.hide_old_paid !== false && filters.hide_old_paid !== 0 && filters.hide_old_paid !== '0'
            ? 'Sembunyikan lunas bulan lalu'
            : null,
        query.trim() ? `Cari “${query.trim()}”` : null,
    ]
        .filter(Boolean)
        .join(' · ') || 'Semua tagihan';

    const remove = (invoice) => {
        if (invoice.status === 'paid') {
            if (
                !window.confirm(
                    `Tagihan ${invoice.number} sudah lunas. Batalkan (void)? Jatuh tempo dikembalikan, tagihan baru dibuat, dan isolir dijalankan jika sudah lewat tempo.`,
                )
            ) {
                return;
            }
            router.post(`/admin/billing/invoices/${invoice.id}/void`);
            return;
        }

        if (
            !window.confirm(
                `Hapus tagihan ${invoice.number} (${invoice.total_label})? Tindakan ini tidak bisa dibatalkan.`,
            )
        ) {
            return;
        }
        router.delete(`/admin/billing/invoices/${invoice.id}`, keepPage);
    };

    return (
        <AdminLayout
            title="Tagihan & Pembayaran"
            subtitle="Tagihan bulanan muncul otomatis 7 hari sebelum jatuh tempo"
        >
            <Head title="Tagihan & Pembayaran" />

            <div className="mb-5 grid items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    label="Belum bayar"
                    value={stats.unpaid}
                    tone="rose"
                    icon={WalletCards}
                />
                <StatCard
                    label="Jatuh tempo"
                    value={stats.overdue}
                    tone="amber"
                    icon={Hourglass}
                />
                <StatCard
                    label="Isolir"
                    value={stats.isolated ?? 0}
                    hint={
                        filters.customer_status === 'isolated'
                            ? 'Filter aktif — klik untuk lepas'
                            : 'Klik untuk filter tagihan pelanggan isolir'
                    }
                    tone="violet"
                    icon={ShieldAlert}
                    href={`/admin/billing?customer_status=${
                        filters.customer_status === 'isolated' ? '' : 'isolated'
                    }`}
                />
                <StatCard
                    label="Lunas bulan ini"
                    value={stats.paid_this_month}
                    tone="emerald"
                    icon={ShieldCheck}
                />
                <StatCard
                    label="Omzet bulan ini"
                    value={stats.collected_this_month_label}
                    tone="indigo"
                    icon={Coins}
                />
            </div>

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center">
                    <div className="relative w-full sm:w-auto">
                        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-ink-soft" />
                        <input
                            type="search"
                            value={query}
                            placeholder="Cari invoice / pelanggan..."
                            onChange={(e) => {
                                setQuery(e.currentTarget.value);
                                setPage(1);
                            }}
                            className="w-full border border-ink/15 py-2 pr-3 pl-9 text-sm outline-none focus:border-signal sm:w-64"
                        />
                    </div>
                    <select
                        value={filters.router_id || ''}
                        onChange={(e) => applyFilters('router_id', e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                    >
                        <option value="">Semua router</option>
                        {routers.map((routerItem) => (
                            <option key={routerItem.id} value={routerItem.id}>
                                {routerItem.name}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.status || ''}
                        onChange={(e) => applyFilters('status', e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                    >
                        <option value="">Semua status</option>
                        <option value="unpaid">Belum bayar</option>
                        <option value="paid">Lunas</option>
                        <option value="void">Dibatalkan</option>
                    </select>
                    <select
                        value={filters.grace || ''}
                        onChange={(e) => applyFilters('grace', e.target.value)}
                        className="w-full border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                    >
                        <option value="">Semua grace</option>
                        <option value="active">Grace aktif</option>
                        <option value="none">Tanpa grace</option>
                    </select>
                    <label className="inline-flex items-center gap-2 border border-ink/15 px-3 py-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            checked={Boolean(filters.overdue)}
                            onChange={(e) => applyFilters('overdue', e.target.checked)}
                        />
                        Jatuh tempo saja
                    </label>
                    <label className="inline-flex items-center gap-2 border border-ink/15 px-3 py-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            checked={filters.customer_status === 'isolated'}
                            onChange={(e) =>
                                applyFilters(
                                    'customer_status',
                                    e.target.checked ? 'isolated' : '',
                                )
                            }
                        />
                        Isolir saja
                    </label>
                    <label className="inline-flex items-center gap-2 border border-ink/15 px-3 py-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            checked={filters.hide_old_paid !== false && filters.hide_old_paid !== 0 && filters.hide_old_paid !== '0'}
                            onChange={(e) => applyFilters('hide_old_paid', e.target.checked)}
                        />
                        Sembunyikan lunas bulan lalu
                    </label>
                    <label className="inline-flex items-center gap-2 text-sm text-ink">
                        <span className="sr-only">Baris per halaman</span>
                        <select
                            value={perPage}
                            onChange={(e) => changePerPage(Number(e.target.value))}
                            className="w-full border border-ink/15 bg-white px-3 py-2 text-sm outline-none focus:border-signal sm:w-auto"
                            title="Baris per halaman"
                        >
                            {PER_PAGE_OPTIONS.map((n) => (
                                <option key={n} value={n}>
                                    {n} / halaman
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <div className="admin-toolbar-actions">
                    <button
                        type="button"
                        onClick={() => setShowPrint((v) => !v)}
                        className="btn-action btn-action-sm btn-secondary"
                        title="Cetak daftar pelanggan dari filter tagihan saat ini, diurutkan A → Z"
                    >
                        <Printer className="mr-1.5 h-4 w-4" />
                        Cetak
                    </button>
                    <button
                        type="button"
                        onClick={generate}
                        className="btn-action btn-action-sm btn-primary"
                    >
                        <FilePlus2 className="mr-1.5 h-4 w-4" />
                        Generate Tagihan
                    </button>
                </div>
            </div>

            {showPrint && (
                <div className="mb-4 space-y-3 border border-ink/10 bg-white p-4 sm:p-5">
                    <div>
                        <h2 className="text-sm font-semibold text-ink">
                            Cetak pelanggan sesuai filter
                        </h2>
                        <p className="mt-0.5 text-xs text-ink-soft">
                            Daftar dicetak sesuai abjad nama (A → Z), satu baris per pelanggan dari
                            tagihan yang sedang difilter di halaman ini.
                        </p>
                        <p className="mt-2 text-xs text-ink">
                            Filter aktif: <strong>{printFilterSummary}</strong>
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={openPrint}
                            className="btn-action btn-action-sm btn-primary"
                        >
                            <Printer className="mr-1.5 h-4 w-4" />
                            Buka &amp; cetak
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowPrint(false)}
                            className="btn-action btn-action-sm btn-secondary"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            )}

            {selected.length > 0 && (
                <div className="mb-4 flex flex-col gap-3 border border-signal/20 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-sm font-semibold text-ink">
                            {selectedUnpaidCount} tagihan belum bayar terpilih
                        </h2>
                        <p className="mt-0.5 text-xs text-ink-soft">
                            {canPay
                                ? 'Tandai lunas massal untuk pelanggan yang sudah membayar.'
                                : 'Kirim WhatsApp ke tagihan terpilih.'}
                            {selected.length > selectedUnpaidCount
                                ? ` ${selected.length - selectedUnpaidCount} tagihan lain dilewati.`
                                : null}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {canPay ? (
                            <>
                                <select
                                    value={bulkMethod}
                                    onChange={(e) => setBulkMethod(e.target.value)}
                                    disabled={bulkProcessing}
                                    className="border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal"
                                >
                                    {payment_methods.map((item) => (
                                        <option key={item.value} value={item.value}>
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={bulkPay}
                                    disabled={bulkProcessing || selectedUnpaidCount === 0}
                                    className="btn-action btn-action-sm btn-success"
                                >
                                    <CheckCircle2 className="mr-1.5 h-4 w-4" />
                                    {bulkProcessing
                                        ? 'Memproses...'
                                        : `Tandai Lunas (${selectedUnpaidCount})`}
                                </button>
                            </>
                        ) : null}
                        {whatsapp?.templates?.length ? (
                            <>
                                <select
                                    value={bulkWaTemplate}
                                    onChange={(e) => setBulkWaTemplate(e.target.value)}
                                    disabled={bulkWaProcessing || !whatsapp.enabled}
                                    className="border border-ink/15 px-3 py-2 text-sm outline-none focus:border-signal"
                                >
                                    {whatsapp.templates.map((item) => (
                                        <option key={item.value} value={item.value}>
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                                <button
                                    type="button"
                                    onClick={bulkWhatsapp}
                                    disabled={
                                        bulkWaProcessing ||
                                        selected.length === 0 ||
                                        !whatsapp.enabled
                                    }
                                    className="btn-action btn-action-sm btn-secondary"
                                >
                                    <Send className="mr-1.5 h-4 w-4" />
                                    {bulkWaProcessing ? 'Mengirim...' : 'Kirim WA'}
                                </button>
                            </>
                        ) : null}
                        <button
                            type="button"
                            onClick={() => setSelected([])}
                            disabled={bulkProcessing}
                            className="btn-action btn-action-sm btn-secondary"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            )}

            <div className="admin-data-scroll border border-ink/10 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-ink/10 bg-mist/50 text-xs tracking-wide text-ink-soft uppercase">
                        <tr>
                            <th className="px-3 py-3 font-semibold">
                                <input
                                    type="checkbox"
                                    checked={allPageSelected}
                                    onChange={togglePage}
                                    disabled={pageIds.length === 0}
                                    className="accent-signal-deep"
                                    title="Pilih semua tagihan di halaman ini"
                                />
                            </th>
                            <SortableHeader
                                label="Invoice"
                                column="number"
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={applySort}
                            />
                            <SortableHeader
                                label="Pelanggan"
                                column="customer"
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={applySort}
                            />
                            <SortableHeader
                                label="Tipe"
                                column="type"
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={applySort}
                                className="hidden md:table-cell"
                            />
                            <SortableHeader
                                label="Jatuh tempo"
                                column="due_date"
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={applySort}
                            />
                            <SortableHeader
                                label="Total"
                                column="total"
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={applySort}
                            />
                            <SortableHeader
                                label="Status"
                                column="status"
                                sort={filters.sort}
                                direction={filters.direction}
                                onSort={applySort}
                            />
                            <th className="px-4 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((item) => (
                            <tr
                                key={item.id}
                                className={`border-b border-ink/5 last:border-0 ${
                                    selected.includes(item.id) ? 'bg-signal/5' : ''
                                }`}
                            >
                                <td className="px-3 py-3">
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(item.id)}
                                        onChange={() => toggleOne(item.id)}
                                        className="accent-signal-deep"
                                        title="Pilih untuk lunas massal atau kirim WhatsApp"
                                    />
                                </td>
                                <td className="px-4 py-3">
                                    <Link
                                        href={`/admin/billing/invoices/${item.id}`}
                                        className="font-medium text-signal-deep hover:underline"
                                    >
                                        {item.number}
                                    </Link>
                                    <p className="text-xs text-ink-soft">{item.package_name || '—'}</p>
                                </td>
                                <td className="px-4 py-3">
                                    <p className="font-medium text-ink">{item.customer?.name || '—'}</p>
                                    <p className="text-xs text-ink-soft">{item.customer?.username}</p>
                                    {item.customer?.status === 'isolated' ? (
                                        <span className="mt-1 inline-block bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-red-700 uppercase">
                                            Isolir
                                        </span>
                                    ) : null}
                                    {item.customer?.router?.name ? (
                                        <p className="text-xs text-ink-soft">{item.customer.router.name}</p>
                                    ) : null}
                                    {item.customer?.phone ? (
                                        <p className="text-xs text-ink-soft">{item.customer.phone}</p>
                                    ) : null}
                                </td>
                                <td className="hidden px-4 py-3 text-ink-soft md:table-cell">
                                    {item.type_label}
                                </td>
                                <td className="px-4 py-3 text-ink-soft">{item.due_date}</td>
                                <td className="px-4 py-3 font-medium text-ink">{item.total_label}</td>
                                <td className="px-4 py-3">
                                    <StatusBadge
                                        status={item.status}
                                        overdue={item.is_overdue}
                                        graceUntil={
                                            item.customer?.has_active_grace
                                                ? item.customer.grace_until
                                                : null
                                        }
                                    />
                                </td>
                                <td className="px-4 py-3">
                                    <div className="admin-actions">
                                        <QuickPayMenu invoice={item} methods={payment_methods} />
                                        <a
                                            href={`/admin/billing/invoices/${item.id}/print`}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="admin-icon-btn"
                                            title="Cetak invoice"
                                        >
                                            <Printer className="h-3.5 w-3.5" />
                                        </a>
                                        <MoreActions
                                            invoice={item}
                                            onRemove={remove}
                                            whatsapp={whatsapp}
                                        />
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-10 text-center text-ink-soft">
                                    {query.trim()
                                        ? 'Tidak ada tagihan yang cocok dengan pencarian.'
                                        : filters.hide_old_paid !== false &&
                                            filters.hide_old_paid !== 0 &&
                                            filters.hide_old_paid !== '0'
                                          ? 'Tidak ada tagihan untuk ditampilkan. Lunas bulan lalu disembunyikan.'
                                          : 'Belum ada tagihan. Gunakan Generate Tagihan atau tambah pelanggan baru.'}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <LocalPagination
                page={paged.current_page}
                lastPage={paged.last_page}
                from={paged.from}
                to={paged.to}
                total={paged.total}
                label="tagihan"
                onPage={setPage}
                perPage={perPage}
                onPerPage={changePerPage}
                perPageOptions={PER_PAGE_OPTIONS}
            />
        </AdminLayout>
    );
}
