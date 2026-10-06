import { router } from '@inertiajs/react';
import { keepPage } from './keepPage';

export function sendBillingWhatsapp(invoiceId, template, label) {
    if (!invoiceId || !template) return;
    if (!window.confirm(`Kirim WhatsApp "${label}" ke pelanggan tagihan ini?`)) {
        return;
    }

    router.post(`/admin/billing/invoices/${invoiceId}/whatsapp`, { template }, keepPage);
}

export function bulkBillingWhatsapp(ids, template, extra = {}) {
    if (!ids?.length || !template) {
        return;
    }

    router.post(
        '/admin/billing/bulk-whatsapp',
        { ids, template },
        { ...keepPage, ...extra },
    );
}
