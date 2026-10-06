export default function LocalPagination({
    page,
    lastPage,
    from,
    to,
    total,
    label = 'baris',
    onPage,
    perPage,
    onPerPage,
    perPageOptions = [],
}) {
    const showPerPage = typeof onPerPage === 'function' && perPageOptions.length > 0;

    if (!total || (lastPage <= 1 && !showPerPage)) {
        return null;
    }

    const pages = [];
    const windowSize = 2;
    for (let index = 1; index <= lastPage; index += 1) {
        const inWindow = index >= page - windowSize && index <= page + windowSize;
        if (index === 1 || index === lastPage || inWindow) {
            pages.push(index);
        } else if (pages[pages.length - 1] !== 'ellipsis') {
            pages.push('ellipsis');
        }
    }

    const buttonClass = (active, disabled = false) =>
        `px-3 py-1.5 text-xs font-semibold ${
            active
                ? 'bg-signal-deep text-white'
                : 'border border-ink/10 text-ink-soft hover:bg-mist'
        } ${disabled ? 'disabled:opacity-40' : ''}`;

    return (
        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-3">
                <p className="text-xs text-ink-soft">
                    Menampilkan {from}–{to} dari {total} {label}
                </p>
                {showPerPage ? (
                    <label className="inline-flex items-center gap-2 text-xs text-ink-soft">
                        <span className="sr-only">Baris per halaman</span>
                        <select
                            value={perPage}
                            onChange={(e) => onPerPage(Number(e.target.value))}
                            className="border border-ink/15 bg-white px-2 py-1.5 text-xs font-semibold text-ink outline-none focus:border-signal"
                            title="Baris per halaman"
                        >
                            {perPageOptions.map((n) => (
                                <option key={n} value={n}>
                                    {n} / halaman
                                </option>
                            ))}
                        </select>
                    </label>
                ) : null}
            </div>
            {lastPage > 1 ? (
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        disabled={page <= 1}
                        onClick={() => onPage(page - 1)}
                        className={buttonClass(false, page <= 1)}
                    >
                        Sebelumnya
                    </button>
                    {pages.map((item, index) =>
                        item === 'ellipsis' ? (
                            <span
                                key={`ellipsis-${index}`}
                                className="px-2 py-1.5 text-xs text-ink-soft"
                            >
                                …
                            </span>
                        ) : (
                            <button
                                key={item}
                                type="button"
                                onClick={() => onPage(item)}
                                className={buttonClass(item === page)}
                            >
                                {item}
                            </button>
                        ),
                    )}
                    <button
                        type="button"
                        disabled={page >= lastPage}
                        onClick={() => onPage(page + 1)}
                        className={buttonClass(false, page >= lastPage)}
                    >
                        Berikutnya
                    </button>
                </div>
            ) : null}
        </div>
    );
}
