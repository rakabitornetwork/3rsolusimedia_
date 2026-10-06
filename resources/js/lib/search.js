/** Pencarian instan di data yang sudah ada di halaman, tanpa request ulang. */
export function matchesSearch(query, ...values) {
    const needle = String(query || '')
        .trim()
        .toLowerCase();
    if (!needle) return true;

    return values.some((value) => String(value ?? '').toLowerCase().includes(needle));
}

export function paginateItems(items, page, perPage) {
    const list = Array.isArray(items) ? items : [];
    const size = Math.max(1, Number(perPage) || 25);
    const total = list.length;
    const lastPage = Math.max(1, Math.ceil(total / size) || 1);
    const current = Math.min(Math.max(1, Number(page) || 1), lastPage);
    const start = (current - 1) * size;

    return {
        data: list.slice(start, start + size),
        total,
        from: total === 0 ? 0 : start + 1,
        to: Math.min(start + size, total),
        current_page: current,
        last_page: lastPage,
        per_page: size,
    };
}
