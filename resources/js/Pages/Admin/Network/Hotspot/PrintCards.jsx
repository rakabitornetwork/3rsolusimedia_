import { Head } from '@inertiajs/react';
import { useEffect, useId, useMemo, useState } from 'react';
import QrMark from '../../../../Components/Admin/QrMark';

const CARDS_PER_PAGE = 56;
const COLS = 7;
const ROWS = 8;

const PRICE_THEMES = [
    {
        max: 0,
        key: 'free',
        label: 'Gratis',
        from: '#64748b',
        to: '#1e293b',
        accent: '#94a3b8',
        ink: '#0f172a',
        soft: '#334155',
        chip: '#e2e8f0',
    },
    {
        max: 2999,
        key: 'starter',
        label: 'Starter',
        from: '#34d399',
        to: '#047857',
        accent: '#a7f3d0',
        ink: '#064e3b',
        soft: '#065f46',
        chip: '#d1fae5',
    },
    {
        max: 4999,
        key: 'basic',
        label: 'Basic',
        from: '#22d3ee',
        to: '#0e7490',
        accent: '#a5f3fc',
        ink: '#164e63',
        soft: '#155e75',
        chip: '#cffafe',
    },
    {
        max: 9999,
        key: 'standard',
        label: 'Standard',
        from: '#60a5fa',
        to: '#1d4ed8',
        accent: '#bfdbfe',
        ink: '#1e3a8a',
        soft: '#1e40af',
        chip: '#dbeafe',
    },
    {
        max: 14999,
        key: 'plus',
        label: 'Plus',
        from: '#a78bfa',
        to: '#6d28d9',
        accent: '#ddd6fe',
        ink: '#4c1d95',
        soft: '#5b21b6',
        chip: '#ede9fe',
    },
    {
        max: 24999,
        key: 'gold',
        label: 'Gold',
        from: '#fbbf24',
        to: '#b45309',
        accent: '#fde68a',
        ink: '#78350f',
        soft: '#92400e',
        chip: '#fef3c7',
    },
    {
        max: Number.POSITIVE_INFINITY,
        key: 'premium',
        label: 'Premium',
        from: '#fb7185',
        to: '#9f1239',
        accent: '#fecdd3',
        ink: '#881337',
        soft: '#9f1239',
        chip: '#ffe4e6',
    },
];

function chunkCards(items, size) {
    const pages = [];
    for (let i = 0; i < items.length; i += size) {
        pages.push(items.slice(i, i + size));
    }
    return pages.length > 0 ? pages : [[]];
}

function themeForPrice(sellPrice) {
    const price = Math.max(0, Number(sellPrice) || 0);
    return PRICE_THEMES.find((theme) => price <= theme.max) || PRICE_THEMES[PRICE_THEMES.length - 1];
}

function CardArt({ theme, uid }) {
    const gradId = `${uid}-grad`;
    const glowId = `${uid}-glow`;
    const patternId = `${uid}-pat`;

    if (theme.key === 'gold' || theme.key === 'premium') {
        return (
            <svg className="voucher-card__art" viewBox="0 0 160 100" preserveAspectRatio="none" aria-hidden>
                <defs>
                    <linearGradient id={gradId} x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor={theme.from} />
                        <stop offset="55%" stopColor={theme.to} />
                        <stop offset="100%" stopColor={theme.from} stopOpacity="0.85" />
                    </linearGradient>
                    <radialGradient id={glowId} cx="85%" cy="15%" r="55%">
                        <stop offset="0%" stopColor="#fff" stopOpacity="0.55" />
                        <stop offset="100%" stopColor="#fff" stopOpacity="0" />
                    </radialGradient>
                </defs>
                <rect width="160" height="100" fill={`url(#${gradId})`} />
                <rect width="160" height="100" fill={`url(#${glowId})`} />
                <path
                    d="M0 72c28-16 52-8 78 2s48 18 72 4 10-18 10-18v40H0V72Z"
                    fill={theme.accent}
                    opacity="0.22"
                />
                <path
                    d="M118 8l14 14-14 14-14-14 14-14Z"
                    fill={theme.accent}
                    opacity="0.35"
                />
                <circle cx="138" cy="78" r="18" fill={theme.accent} opacity="0.18" />
                <path
                    d="M12 18h28M12 24h18"
                    stroke="#fff"
                    strokeWidth="1.4"
                    strokeLinecap="round"
                    opacity="0.35"
                />
            </svg>
        );
    }

    if (theme.key === 'plus' || theme.key === 'standard') {
        return (
            <svg className="voucher-card__art" viewBox="0 0 160 100" preserveAspectRatio="none" aria-hidden>
                <defs>
                    <linearGradient id={gradId} x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor={theme.from} />
                        <stop offset="100%" stopColor={theme.to} />
                    </linearGradient>
                    <pattern id={patternId} width="12" height="12" patternUnits="userSpaceOnUse">
                        <circle cx="1.5" cy="1.5" r="1.1" fill={theme.accent} opacity="0.35" />
                    </pattern>
                </defs>
                <rect width="160" height="100" fill={`url(#${gradId})`} />
                <rect width="160" height="100" fill={`url(#${patternId})`} />
                <circle cx="142" cy="16" r="28" fill={theme.accent} opacity="0.2" />
                <circle cx="142" cy="16" r="16" fill="none" stroke="#fff" strokeWidth="1.2" opacity="0.35" />
                <path
                    d="M0 78c36-20 70-12 104 4s40 20 56 8v20H0V78Z"
                    fill="#fff"
                    opacity="0.12"
                />
                <path
                    d="M18 22c10 8 26 8 36 0"
                    fill="none"
                    stroke="#fff"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    opacity="0.4"
                />
            </svg>
        );
    }

    return (
        <svg className="voucher-card__art" viewBox="0 0 160 100" preserveAspectRatio="none" aria-hidden>
            <defs>
                <linearGradient id={gradId} x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stopColor={theme.from} />
                    <stop offset="100%" stopColor={theme.to} />
                </linearGradient>
                <radialGradient id={glowId} cx="90%" cy="10%" r="50%">
                    <stop offset="0%" stopColor="#fff" stopOpacity="0.45" />
                    <stop offset="100%" stopColor="#fff" stopOpacity="0" />
                </radialGradient>
            </defs>
            <rect width="160" height="100" fill={`url(#${gradId})`} />
            <rect width="160" height="100" fill={`url(#${glowId})`} />
            <circle cx="148" cy="10" r="34" fill={theme.accent} opacity="0.18" />
            <circle cx="148" cy="10" r="20" fill="none" stroke="#fff" strokeWidth="1.3" opacity="0.3" />
            <path
                d="M0 70c34-22 72-26 108-10s42 34 52 28v22H0V70Z"
                fill="#fff"
                opacity="0.14"
            />
            <path
                d="M14 26c12 10 32 10 44 0M10 36c16 14 44 14 60 0"
                fill="none"
                stroke="#fff"
                strokeWidth="1.5"
                strokeLinecap="round"
                opacity="0.35"
            />
        </svg>
    );
}

function Card({ item, number, showQr }) {
    const uid = useId().replace(/:/g, '');
    const theme = themeForPrice(item.sell_price);
    const voucherCode = item.username || item.password || '';
    const seq = String(number).padStart(2, '0');
    const sameCode = item.same_code ?? item.username === item.password;
    const qrValue = item.login_url || '';

    return (
        <article
            className={`voucher-card voucher-card--${theme.key}`}
            style={{
                '--vc-from': theme.from,
                '--vc-to': theme.to,
                '--vc-accent': theme.accent,
                '--vc-ink': theme.ink,
                '--vc-soft': theme.soft,
                '--vc-chip': theme.chip,
            }}
        >
            <div className="voucher-card__hero">
                <CardArt theme={theme} uid={uid} />
                <div className="voucher-card__hero-content">
                    <div className="voucher-card__hero-top">
                        <div className="voucher-card__brand-block">
                            {item.agent_name && (
                                <span className="voucher-card__agent">{item.agent_name}</span>
                            )}
                            <span className="voucher-card__brand">Hotspot</span>
                        </div>
                        <span className="voucher-card__tier">{theme.label}</span>
                    </div>
                    <p className="voucher-card__price">{item.sell_price_label || 'Rp 0'}</p>
                </div>
            </div>

            <div className="voucher-card__body">
                <div className={`voucher-card__creds-row${showQr && qrValue ? ' has-qr' : ''}`}>
                    <div className="voucher-card__creds">
                        <span className="voucher-card__label">
                            {sameCode ? 'Voucher' : 'User / Pass'}
                        </span>
                        <span className="voucher-card__value">{voucherCode}</span>
                        {!sameCode && item.password && (
                            <span className="voucher-card__value voucher-card__value--pass">
                                {item.password}
                            </span>
                        )}
                    </div>
                    {showQr && qrValue && <QrMark value={qrValue} className="voucher-qr voucher-qr--a4" />}
                </div>

                <div className="voucher-card__footer">
                    <div className="voucher-card__footer-main">
                        {item.login_url || item.dns_name ? (
                            <span className="voucher-card__hint">
                                <span>Scan QR atau buka</span>
                                <span>{item.dns_name || item.login_url}</span>
                            </span>
                        ) : (
                            <span className="voucher-card__hint">
                                <span>Portal tidak muncul?</span>
                                <span>Ketik DNS hotspot di browser.</span>
                            </span>
                        )}
                        {item.profile && <span>{item.profile}</span>}
                    </div>
                    <span className="voucher-card__seq">#{seq}</span>
                </div>
            </div>
        </article>
    );
}

function SmallCard({ item, number, showQr }) {
    const sameCode = item.same_code ?? item.username === item.password;
    const qrValue = item.login_url || '';

    return (
        <article className="voucher-small">
            <header className="voucher-small__head">
                <strong>Hotspot</strong>
                <span>#{String(number).padStart(2, '0')}</span>
            </header>
            <div className={`voucher-small__body${showQr && qrValue ? ' has-qr' : ''}`}>
                <div>
                    <p className="voucher-small__label">{sameCode ? 'Kode voucher' : 'Username'}</p>
                    <p className="voucher-small__code">{item.username}</p>
                    {!sameCode && (
                        <>
                            <p className="voucher-small__label">Password</p>
                            <p className="voucher-small__code">{item.password}</p>
                        </>
                    )}
                    <p className="voucher-small__meta">
                        {[item.limit_uptime, item.profile, item.sell_price_label]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                </div>
                {showQr && qrValue && <QrMark value={qrValue} className="voucher-qr voucher-qr--small" />}
            </div>
        </article>
    );
}

function ThermalCard({ item, number, showQr }) {
    const sameCode = item.same_code ?? item.username === item.password;
    const qrValue = item.login_url || '';
    const now = new Date();
    const stamped = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

    return (
        <article className="voucher-thermal">
            <h2>Hotspot Voucher</h2>
            <p className="voucher-thermal__sub">
                #{String(number).padStart(2, '0')} · {stamped}
            </p>
            {showQr && qrValue && <QrMark value={qrValue} className="voucher-qr voucher-qr--thermal" />}
            <p className="voucher-thermal__label">{sameCode ? 'Kode voucher' : 'Username'}</p>
            <p className="voucher-thermal__code">{item.username}</p>
            {!sameCode && (
                <>
                    <p className="voucher-thermal__label">Password</p>
                    <p className="voucher-thermal__code">{item.password}</p>
                </>
            )}
            <p className="voucher-thermal__meta">
                {[item.profile, item.limit_uptime, item.sell_price_label].filter(Boolean).join(' · ')}
            </p>
            {(item.dns_name || item.login_url) && (
                <p className="voucher-thermal__login">
                    Login: {item.dns_name || item.login_url}
                </p>
            )}
        </article>
    );
}

function persistPrintQuery(layout, showQr) {
    const url = new URL(window.location.href);
    url.searchParams.set('layout', layout);
    url.searchParams.set('qr', showQr ? '1' : '0');
    window.history.replaceState({}, '', url);
}

export default function PrintCards({
    vouchers = [],
    layout: initialLayout = 'a4',
    show_qr: initialShowQr = true,
}) {
    const pages = useMemo(() => chunkCards(vouchers, CARDS_PER_PAGE), [vouchers]);
    const smallPages = useMemo(() => chunkCards(vouchers, 36), [vouchers]);
    const [layout, setLayout] = useState(
        ['a4', 'small', 'thermal'].includes(initialLayout) ? initialLayout : 'a4',
    );
    const [showQr, setShowQr] = useState(Boolean(initialShowQr));

    useEffect(() => {
        persistPrintQuery(layout, showQr);
    }, [layout, showQr]);

    useEffect(() => {
        const timer = window.setTimeout(() => window.print(), 500);
        return () => window.clearTimeout(timer);
    }, []);

    return (
        <div className={`voucher-print voucher-print--${layout}`}>
            <Head title="Cetak Kartu Voucher" />

            <style>{`
                @page {
                    size: ${layout === 'thermal' ? '80mm auto' : 'A4 portrait'};
                    margin: ${layout === 'thermal' ? '4mm' : '0'};
                }

                .voucher-print {
                    min-height: 100vh;
                    background: #e8eef2;
                    color: #101820;
                    font-family: 'Manrope Variable', Manrope, ui-sans-serif, system-ui, sans-serif;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                .voucher-print__toolbar {
                    position: sticky;
                    top: 0;
                    z-index: 10;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 12px;
                    padding: 12px 16px;
                    background: #fff;
                    border-bottom: 1px solid rgba(16, 24, 32, 0.12);
                }

                .voucher-print__toolbar h1 {
                    margin: 0;
                    font-size: 16px;
                    font-weight: 700;
                }

                .voucher-print__toolbar p {
                    margin: 2px 0 0;
                    font-size: 13px;
                    color: #2a3540;
                }

                .voucher-print__legend {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    margin-top: 8px;
                }

                .voucher-print__legend span {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    font-size: 11px;
                    color: #2a3540;
                    background: #f5f8fa;
                    border: 1px solid rgba(16, 24, 32, 0.08);
                    padding: 2px 8px;
                }

                .voucher-print__legend button,
                .voucher-print__qr-toggle {
                    font: inherit;
                    cursor: pointer;
                }

                .voucher-print__qr-toggle {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    font-size: 11px;
                    color: #2a3540;
                    background: #f5f8fa;
                    border: 1px solid rgba(16, 24, 32, 0.08);
                    padding: 2px 8px;
                }

                .voucher-print__legend i {
                    width: 8px;
                    height: 8px;
                    border-radius: 99px;
                    display: inline-block;
                }

                .voucher-print__sheets {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 20px;
                    padding: 20px 12px 32px;
                }

                .voucher-sheet {
                    width: 210mm;
                    height: 297mm;
                    box-sizing: border-box;
                    padding: 2.6mm;
                    background: #fff;
                    box-shadow: 0 8px 28px rgba(16, 24, 32, 0.12);
                    display: grid;
                    grid-template-columns: repeat(${COLS}, 1fr);
                    grid-template-rows: repeat(${ROWS}, 1fr);
                    gap: 0;
                    page-break-after: always;
                    break-after: page;
                }

                .voucher-sheet:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }

                .voucher-card {
                    box-sizing: border-box;
                    height: 100%;
                    min-height: 0;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                    background: #fff;
                    border: 0.3pt solid color-mix(in srgb, var(--vc-to) 45%, #101820);
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                .voucher-card__hero {
                    position: relative;
                    height: 40%;
                    min-height: 11.5mm;
                    overflow: hidden;
                    color: #fff;
                }

                .voucher-card__art {
                    position: absolute;
                    inset: 0;
                    width: 100%;
                    height: 100%;
                    display: block;
                }

                .voucher-card__hero-content {
                    position: relative;
                    z-index: 1;
                    height: 100%;
                    box-sizing: border-box;
                    padding: 1.2mm 1.5mm;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }

                .voucher-card__hero-top {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1mm;
                }

                .voucher-card__brand-block {
                    display: flex;
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.25mm;
                    min-width: 0;
                    max-width: 70%;
                }

                .voucher-card__agent {
                    font-size: 4.8pt;
                    font-weight: 700;
                    letter-spacing: 0.02em;
                    line-height: 1.1;
                    max-width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    opacity: 0.92;
                    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.18);
                }

                .voucher-card__brand {
                    font-size: 5.8pt;
                    font-weight: 800;
                    letter-spacing: 0.12em;
                    text-transform: uppercase;
                    line-height: 1;
                    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.18);
                }

                .voucher-card__tier {
                    font-size: 5pt;
                    font-weight: 700;
                    letter-spacing: 0.04em;
                    text-transform: uppercase;
                    line-height: 1;
                    padding: 0.5mm 1mm;
                    background: rgba(255, 255, 255, 0.22);
                    border: 0.25pt solid rgba(255, 255, 255, 0.35);
                }

                .voucher-card__price {
                    margin: 0;
                    font-size: 9pt;
                    font-weight: 800;
                    letter-spacing: -0.02em;
                    line-height: 1;
                    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
                    white-space: nowrap;
                }

                .voucher-card__body {
                    flex: 1;
                    min-height: 0;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    gap: 0.5mm;
                    padding: 1.2mm 1.5mm 1.3mm;
                    background:
                        linear-gradient(180deg, color-mix(in srgb, var(--vc-chip) 70%, white) 0%, #fff 55%);
                    border-top: 0.35pt solid color-mix(in srgb, var(--vc-accent) 55%, white);
                }

                .voucher-card__creds-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 0.8mm;
                    min-width: 0;
                }

                .voucher-card__creds-row.has-qr .voucher-card__value {
                    font-size: 6.4pt;
                }

                .voucher-card__creds {
                    display: flex;
                    flex-direction: column;
                    align-items: stretch;
                    gap: 0.4mm;
                    min-width: 0;
                    flex: 1;
                    max-width: 100%;
                }

                .voucher-card__label {
                    font-size: 5pt;
                    font-weight: 700;
                    letter-spacing: 0.06em;
                    text-transform: uppercase;
                    color: var(--vc-soft);
                    line-height: 1;
                    text-align: center;
                }

                .voucher-card__value {
                    box-sizing: border-box;
                    width: 100%;
                    max-width: 100%;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                    font-size: 7.2pt;
                    font-weight: 800;
                    letter-spacing: 0.03em;
                    color: var(--vc-ink);
                    line-height: 1.15;
                    text-align: center;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    background: color-mix(in srgb, var(--vc-chip) 55%, white);
                    border: 0.25pt solid color-mix(in srgb, var(--vc-accent) 40%, white);
                    padding: 0.6mm 0.6mm;
                }

                .voucher-card__footer {
                    display: flex;
                    align-items: flex-end;
                    justify-content: space-between;
                    gap: 0.8mm;
                    font-size: 4.6pt;
                    color: var(--vc-soft);
                    line-height: 1.2;
                    overflow: hidden;
                }

                .voucher-card__footer-main {
                    display: flex;
                    flex-direction: column;
                    gap: 0.2mm;
                    min-width: 0;
                    flex: 1;
                }

                .voucher-card__seq {
                    flex-shrink: 0;
                    font-size: 5.2pt;
                    font-weight: 800;
                    letter-spacing: 0.03em;
                    line-height: 1;
                    color: var(--vc-ink);
                    padding: 0.35mm 0.7mm;
                    background: color-mix(in srgb, var(--vc-chip) 70%, white);
                    border: 0.25pt solid color-mix(in srgb, var(--vc-accent) 45%, white);
                }

                .voucher-card__hint {
                    display: flex;
                    flex-direction: column;
                    gap: 0.1mm;
                    color: var(--vc-ink);
                    font-weight: 600;
                    line-height: 1.15;
                    white-space: normal;
                    overflow: visible;
                    text-overflow: unset;
                }

                .voucher-card__hint span {
                    display: block;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .voucher-card__footer-main > span:not(.voucher-card__hint) {
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .voucher-qr {
                    display: block;
                    flex-shrink: 0;
                    background: #fff;
                    image-rendering: pixelated;
                }

                .voucher-qr--a4 {
                    width: 8.5mm;
                    height: 8.5mm;
                }

                .voucher-qr--small {
                    width: 14mm;
                    height: 14mm;
                }

                .voucher-qr--thermal {
                    width: 32mm;
                    height: 32mm;
                    margin: 2mm auto;
                }

                .voucher-print--small .voucher-print__sheets {
                    align-items: center;
                }

                .voucher-small-sheet {
                    width: 210mm;
                    min-height: 297mm;
                    box-sizing: border-box;
                    padding: 8mm 6mm;
                    background: #fff;
                    box-shadow: 0 8px 28px rgba(16, 24, 32, 0.12);
                    display: flex;
                    flex-wrap: wrap;
                    align-content: flex-start;
                    gap: 2mm;
                    page-break-after: always;
                    break-after: page;
                }

                .voucher-small-sheet:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }

                .voucher-small {
                    box-sizing: border-box;
                    width: 48mm;
                    min-height: 28mm;
                    padding: 1.6mm 2mm;
                    border: 0.4pt solid #111;
                    background: #fff;
                    display: flex;
                    flex-direction: column;
                    gap: 1mm;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                .voucher-small__head {
                    display: flex;
                    justify-content: space-between;
                    font-size: 7.5pt;
                    font-weight: 800;
                    border-bottom: 0.4pt solid #111;
                    padding-bottom: 0.6mm;
                }

                .voucher-small__body {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 1.5mm;
                }

                .voucher-small__label {
                    margin: 0;
                    font-size: 5.5pt;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    color: #334155;
                }

                .voucher-small__code {
                    margin: 0.3mm 0 0.8mm;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                    font-size: 10pt;
                    font-weight: 800;
                    line-height: 1.1;
                }

                .voucher-small__meta {
                    margin: 0;
                    font-size: 6pt;
                    color: #334155;
                }

                .voucher-print--thermal .voucher-print__sheets {
                    gap: 8px;
                    padding: 12px;
                }

                .voucher-thermal {
                    width: 72mm;
                    box-sizing: border-box;
                    padding: 4mm 3mm 5mm;
                    background: #fff;
                    border: 0.4pt dashed #111;
                    text-align: center;
                    page-break-after: always;
                    break-after: page;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                .voucher-thermal:last-child {
                    page-break-after: auto;
                    break-after: auto;
                }

                .voucher-thermal h2 {
                    margin: 0;
                    font-size: 13pt;
                }

                .voucher-thermal__sub,
                .voucher-thermal__meta,
                .voucher-thermal__login {
                    margin: 1mm 0 0;
                    font-size: 8pt;
                    color: #334155;
                }

                .voucher-thermal__label {
                    margin: 2.5mm 0 0;
                    font-size: 7pt;
                    text-transform: uppercase;
                    letter-spacing: 0.06em;
                }

                .voucher-thermal__code {
                    margin: 0.6mm 0 0;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                    font-size: 16pt;
                    font-weight: 800;
                    line-height: 1.1;
                }

                @media print {
                    .voucher-print {
                        background: #fff;
                    }

                    .voucher-print__toolbar {
                        display: none !important;
                    }

                    .voucher-print__sheets {
                        gap: 0;
                        padding: 0;
                    }

                    .voucher-sheet,
                    .voucher-small-sheet,
                    .voucher-thermal {
                        box-shadow: none;
                        margin: 0;
                    }
                }

                @media screen and (max-width: 900px) {
                    .voucher-print__sheets {
                        overflow-x: auto;
                        align-items: flex-start;
                    }
                }
            `}</style>

            <div className="voucher-print__toolbar">
                <div>
                    <h1>Cetak Kartu Voucher</h1>
                    <p>
                        {vouchers.length} kartu
                        {layout === 'a4' && ` · ${pages.length} lembar A4 · ${COLS}×${ROWS}`}
                        {layout === 'small' && ' · kartu kecil (seperti Print Small Mikhmon)'}
                        {layout === 'thermal' && ' · struk 80mm (thermal)'}
                        {showQr ? ' · QR login' : ' · tanpa QR'}
                    </p>
                    <div className="voucher-print__legend">
                        {['a4', 'small', 'thermal'].map((item) => (
                            <button
                                key={item}
                                type="button"
                                onClick={() => setLayout(item)}
                                className={`btn-action btn-action-xs ${
                                    layout === item ? 'btn-primary' : 'btn-secondary'
                                }`}
                            >
                                {item === 'a4' ? 'A4' : item === 'small' ? 'Kecil' : 'Thermal'}
                            </button>
                        ))}
                        <label className="voucher-print__qr-toggle">
                            <input
                                type="checkbox"
                                checked={showQr}
                                onChange={(e) => setShowQr(e.target.checked)}
                            />
                            QR login
                        </label>
                        {layout === 'a4' &&
                            PRICE_THEMES.map((theme) => (
                                <span key={theme.key}>
                                    <i style={{ background: `linear-gradient(135deg, ${theme.from}, ${theme.to})` }} />
                                    {theme.label}
                                    {theme.max === Number.POSITIVE_INFINITY
                                        ? ' ≥25rb'
                                        : theme.max === 0
                                          ? ' Rp0'
                                          : ` ≤${Math.round(theme.max / 1000)}rb`}
                                </span>
                            ))}
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => window.print()}
                    className="btn-action btn-action-sm btn-primary"
                >
                    Print ulang
                </button>
            </div>

            <div className="voucher-print__sheets">
                {layout === 'a4' &&
                    pages.map((pageCards, pageIndex) => (
                        <section
                            key={`page-${pageIndex}`}
                            className="voucher-sheet"
                            aria-label={`Lembar ${pageIndex + 1}`}
                        >
                            {pageCards.map((item, cardIndex) => (
                                <Card
                                    key={item.id || item.username}
                                    item={item}
                                    number={pageIndex * CARDS_PER_PAGE + cardIndex + 1}
                                    showQr={showQr}
                                />
                            ))}
                        </section>
                    ))}

                {layout === 'small' &&
                    smallPages.map((pageCards, pageIndex) => (
                        <section
                            key={`small-${pageIndex}`}
                            className="voucher-small-sheet"
                            aria-label={`Lembar kecil ${pageIndex + 1}`}
                        >
                            {pageCards.map((item, cardIndex) => (
                                <SmallCard
                                    key={item.id || item.username}
                                    item={item}
                                    number={pageIndex * 36 + cardIndex + 1}
                                    showQr={showQr}
                                />
                            ))}
                        </section>
                    ))}

                {layout === 'thermal' &&
                    vouchers.map((item, index) => (
                        <ThermalCard
                            key={item.id || item.username}
                            item={item}
                            number={index + 1}
                            showQr={showQr}
                        />
                    ))}
            </div>
        </div>
    );
}
