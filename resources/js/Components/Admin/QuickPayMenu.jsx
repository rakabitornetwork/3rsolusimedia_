import { useForm } from '@inertiajs/react';
import { CheckCircle2, ChevronDown } from 'lucide-react';
import OverflowMenu from './OverflowMenu';
import { keepPage } from '../../lib/keepPage';

export default function QuickPayMenu({ invoice, methods = [] }) {
    const { data, setData, post, processing } = useForm({
        method: 'cash',
        reference: '',
        notes: '',
    });

    if (!invoice || invoice.status !== 'unpaid') return null;

    const methodLabel =
        methods.find((item) => item.value === data.method)?.label || data.method;

    const pay = () => {
        if (
            !window.confirm(
                `Bayar tagihan ${invoice.number} (${invoice.total_label}) via ${methodLabel}?`,
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
