/**
 * Pola B helpers — keep in sync with App\Services\BillingCycleService.
 */

function parseDate(value) {
    if (!value) return null;
    const [y, m, d] = value.split('-').map(Number);
    if (!y || !m || !d) return null;
    return new Date(y, m - 1, d);
}

function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function daysInMonth(year, monthIndex) {
    return new Date(year, monthIndex + 1, 0).getDate();
}

function normalizeBillingDay(day) {
    const n = Number(day) || 1;
    return Math.max(1, Math.min(28, n));
}

function dateOnBillingDay(anchor, billingDay) {
    const day = Math.min(billingDay, daysInMonth(anchor.getFullYear(), anchor.getMonth()));
    return new Date(anchor.getFullYear(), anchor.getMonth(), day);
}

function addMonthNoOverflow(date) {
    const day = date.getDate();
    const next = new Date(date.getFullYear(), date.getMonth() + 1, 1);
    const dim = daysInMonth(next.getFullYear(), next.getMonth());
    next.setDate(Math.min(day, dim));
    return next;
}

function diffInDays(from, to) {
    const ms = Date.UTC(to.getFullYear(), to.getMonth(), to.getDate()) -
        Date.UTC(from.getFullYear(), from.getMonth(), from.getDate());
    return Math.round(ms / 86400000);
}

export function nextDueDate(startDateValue, billingDay) {
    const start = parseDate(startDateValue);
    if (!start) return null;

    const day = normalizeBillingDay(billingDay);
    const candidate = dateOnBillingDay(start, day);

    if (candidate > start) {
        return formatDate(candidate);
    }

    return formatDate(dateOnBillingDay(addMonthNoOverflow(start), day));
}

export function advanceDueDate(currentDueValue, billingDay) {
    const due = parseDate(currentDueValue);
    if (!due) return null;

    const day = normalizeBillingDay(billingDay);
    return formatDate(dateOnBillingDay(addMonthNoOverflow(due), day));
}

/** Bulatkan ke atas ke kelipatan Rp 1.000 (contoh: 15484 → 16000). */
export function roundUpToThousand(amount) {
    const value = Number(amount) || 0;
    if (value <= 0) return 0;
    return Math.ceil(value / 1000) * 1000;
}

export function calculateProrata(startDateValue, billingDay, packagePrice) {
    const start = parseDate(startDateValue);
    if (!start) return null;

    const day = normalizeBillingDay(billingDay);
    const dueValue = nextDueDate(startDateValue, day);
    const due = parseDate(dueValue);
    if (!due) return null;

    // previous billing = due minus one month on billing day
    const prevMonth = new Date(due.getFullYear(), due.getMonth() - 1, 1);
    const previousBilling = dateOnBillingDay(prevMonth, day);

    const cycleDays = Math.max(1, diffInDays(previousBilling, due));
    const usedDays = Math.max(0, diffInDays(start, due));
    const price = Number(packagePrice) || 0;
    const rawAmount = usedDays === 0 ? 0 : Math.round((price * usedDays) / cycleDays);
    const amount = roundUpToThousand(rawAmount);

    const formatId = (value) =>
        new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(
            value,
        );

    const endExclusive = new Date(due);
    endExclusive.setDate(endExclusive.getDate() - 1);

    const roundedNote =
        rawAmount !== amount ? ` (dibulatkan dari ${formatId(rawAmount)})` : '';

    return {
        start_date: formatDate(start),
        due_date: dueValue,
        billing_day: day,
        days: usedDays,
        cycle_days: cycleDays,
        package_price: price,
        raw_amount: rawAmount,
        amount,
        amount_label: formatId(amount),
        summary: `Prorata ${formatDate(start)} s/d ${formatDate(endExclusive)} (${usedDays}/${cycleDays} hari siklus) = ${formatId(amount)}${roundedNote}`,
    };
}

export const billingDayOptions = Array.from({ length: 28 }, (_, i) => i + 1);
