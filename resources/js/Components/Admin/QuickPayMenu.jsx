import { useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, ChevronDown } from 'lucide-react';
import OverflowMenu from './OverflowMenu';
import { keepPage } from '../../lib/keepPage';

export default function QuickPayMenu({ invoice, methods = [] }) {
    const canPay = usePage().props.auth?.user?.can_record_payment !== false;
    const serviceBill = invoice?.type !== 'vpn_router';
    const billedMonths = Math.max(1, Number(invoice?.billing_months) || 1);
    const { data, setData, post, processing } = useForm({
        method: 'cash',
        reference: '',
        notes: '',
        months: serviceBill ? billedMonths : 1,
    });

    if (!canPay || !invoice || invoice.status !== 'unpaid') return null;

    const methodLabel =
        methods.find((item) => item.value === data.method)?.label || data.method;
    const months = Number(data.months) || 1;

    const pay = () => {
        const span = months > 1 ? ` untuk ${months} bulan sekaligus` : '';
        const replace =
            months > 1 && billedMonths === 1
                ? ' Tagihan layanan yang belum lunas diganti, lalu ditandai lunas.'
                : '';
        if (
            !window.confirm(
                `Bayar tagihan ${invoice.number}${span} via ${methodLabel}?${replace}`,
            )
        ) {
            return;
        }
        post(`/admin/billing/invoices/${invoice.id}/pay`, keepPage);
    };

    return (
        <OverflowMenu
            trigger={
                <>
                    <CheckCircle2 className="h-3.5 w-3.5" />
                    Bayar
                    <ChevronDown className="h-3 w-3 opacity-80" />
                </>
            }
            triggerClassName="btn-action btn-action-xs btn-success-solid"
            menuClassName="admin-pay-menu"
            align="start"
        >
            {serviceBill ? (
                <>
                    <p className="admin-row-menu-label">Jumlah bulan</p>
                    <select
                        value={months}
                        aria-label="Jumlah bulan"
                        onChange={(e) => setData('months', Number(e.target.value))}
                    >
                        {billedMonths <= 1 ? <option value={1}>1 bulan</option> : null}
                        {[2, 3, 4, 5, 6].map((count) => (
                            <option key={count} value={count}>
                                {count} bulan sekaligus
                            </option>
                        ))}
                    </select>
                </>
            ) : null}
            <p className="admin-row-menu-label">Metode pembayaran</p>
            <select
                value={data.method}
                onChange={(e) => setData('method', e.target.value)}
            >
                {methods.map((item) => (
                    <option key={item.value} value={item.value}>
                        {item.label}
                    </option>
                ))}
            </select>
            <button
                type="button"
                onClick={pay}
                disabled={processing}
                className="btn-action btn-action-xs btn-success-solid"
            >
                {processing ? 'Memproses...' : 'Konfirmasi bayar'}
            </button>
        </OverflowMenu>
    );
}
