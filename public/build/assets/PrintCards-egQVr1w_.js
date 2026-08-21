import{r as e}from"./rolldown-runtime-hePW80VL.js";import{Jt as t}from"./vendor-ui-Ch7OzOS7.js";import{d as n,m as r}from"./vendor-react-Dyo4ernH.js";var i=e(r(),1),a=n(),o=56,s=7,c=8,l=[{max:0,key:`free`,label:`Gratis`,from:`#64748b`,to:`#1e293b`,accent:`#94a3b8`,ink:`#0f172a`,soft:`#334155`,chip:`#e2e8f0`},{max:2999,key:`starter`,label:`Starter`,from:`#34d399`,to:`#047857`,accent:`#a7f3d0`,ink:`#064e3b`,soft:`#065f46`,chip:`#d1fae5`},{max:4999,key:`basic`,label:`Basic`,from:`#22d3ee`,to:`#0e7490`,accent:`#a5f3fc`,ink:`#164e63`,soft:`#155e75`,chip:`#cffafe`},{max:9999,key:`standard`,label:`Standard`,from:`#60a5fa`,to:`#1d4ed8`,accent:`#bfdbfe`,ink:`#1e3a8a`,soft:`#1e40af`,chip:`#dbeafe`},{max:14999,key:`plus`,label:`Plus`,from:`#a78bfa`,to:`#6d28d9`,accent:`#ddd6fe`,ink:`#4c1d95`,soft:`#5b21b6`,chip:`#ede9fe`},{max:24999,key:`gold`,label:`Gold`,from:`#fbbf24`,to:`#b45309`,accent:`#fde68a`,ink:`#78350f`,soft:`#92400e`,chip:`#fef3c7`},{max:1/0,key:`premium`,label:`Premium`,from:`#fb7185`,to:`#9f1239`,accent:`#fecdd3`,ink:`#881337`,soft:`#9f1239`,chip:`#ffe4e6`}];function u(e,t){let n=[];for(let r=0;r<e.length;r+=t)n.push(e.slice(r,r+t));return n.length>0?n:[[]]}function d(e){let t=Math.max(0,Number(e)||0);return l.find(e=>t<=e.max)||l[l.length-1]}function f({theme:e,uid:t}){let n=`${t}-grad`,r=`${t}-glow`,i=`${t}-pat`;return e.key===`gold`||e.key===`premium`?(0,a.jsxs)(`svg`,{className:`voucher-card__art`,viewBox:`0 0 160 100`,preserveAspectRatio:`none`,"aria-hidden":!0,children:[(0,a.jsxs)(`defs`,{children:[(0,a.jsxs)(`linearGradient`,{id:n,x1:`0%`,y1:`0%`,x2:`100%`,y2:`100%`,children:[(0,a.jsx)(`stop`,{offset:`0%`,stopColor:e.from}),(0,a.jsx)(`stop`,{offset:`55%`,stopColor:e.to}),(0,a.jsx)(`stop`,{offset:`100%`,stopColor:e.from,stopOpacity:`0.85`})]}),(0,a.jsxs)(`radialGradient`,{id:r,cx:`85%`,cy:`15%`,r:`55%`,children:[(0,a.jsx)(`stop`,{offset:`0%`,stopColor:`#fff`,stopOpacity:`0.55`}),(0,a.jsx)(`stop`,{offset:`100%`,stopColor:`#fff`,stopOpacity:`0`})]})]}),(0,a.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${n})`}),(0,a.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${r})`}),(0,a.jsx)(`path`,{d:`M0 72c28-16 52-8 78 2s48 18 72 4 10-18 10-18v40H0V72Z`,fill:e.accent,opacity:`0.22`}),(0,a.jsx)(`path`,{d:`M118 8l14 14-14 14-14-14 14-14Z`,fill:e.accent,opacity:`0.35`}),(0,a.jsx)(`circle`,{cx:`138`,cy:`78`,r:`18`,fill:e.accent,opacity:`0.18`}),(0,a.jsx)(`path`,{d:`M12 18h28M12 24h18`,stroke:`#fff`,strokeWidth:`1.4`,strokeLinecap:`round`,opacity:`0.35`})]}):e.key===`plus`||e.key===`standard`?(0,a.jsxs)(`svg`,{className:`voucher-card__art`,viewBox:`0 0 160 100`,preserveAspectRatio:`none`,"aria-hidden":!0,children:[(0,a.jsxs)(`defs`,{children:[(0,a.jsxs)(`linearGradient`,{id:n,x1:`0%`,y1:`0%`,x2:`100%`,y2:`100%`,children:[(0,a.jsx)(`stop`,{offset:`0%`,stopColor:e.from}),(0,a.jsx)(`stop`,{offset:`100%`,stopColor:e.to})]}),(0,a.jsx)(`pattern`,{id:i,width:`12`,height:`12`,patternUnits:`userSpaceOnUse`,children:(0,a.jsx)(`circle`,{cx:`1.5`,cy:`1.5`,r:`1.1`,fill:e.accent,opacity:`0.35`})})]}),(0,a.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${n})`}),(0,a.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${i})`}),(0,a.jsx)(`circle`,{cx:`142`,cy:`16`,r:`28`,fill:e.accent,opacity:`0.2`}),(0,a.jsx)(`circle`,{cx:`142`,cy:`16`,r:`16`,fill:`none`,stroke:`#fff`,strokeWidth:`1.2`,opacity:`0.35`}),(0,a.jsx)(`path`,{d:`M0 78c36-20 70-12 104 4s40 20 56 8v20H0V78Z`,fill:`#fff`,opacity:`0.12`}),(0,a.jsx)(`path`,{d:`M18 22c10 8 26 8 36 0`,fill:`none`,stroke:`#fff`,strokeWidth:`1.6`,strokeLinecap:`round`,opacity:`0.4`})]}):(0,a.jsxs)(`svg`,{className:`voucher-card__art`,viewBox:`0 0 160 100`,preserveAspectRatio:`none`,"aria-hidden":!0,children:[(0,a.jsxs)(`defs`,{children:[(0,a.jsxs)(`linearGradient`,{id:n,x1:`0%`,y1:`0%`,x2:`100%`,y2:`100%`,children:[(0,a.jsx)(`stop`,{offset:`0%`,stopColor:e.from}),(0,a.jsx)(`stop`,{offset:`100%`,stopColor:e.to})]}),(0,a.jsxs)(`radialGradient`,{id:r,cx:`90%`,cy:`10%`,r:`50%`,children:[(0,a.jsx)(`stop`,{offset:`0%`,stopColor:`#fff`,stopOpacity:`0.45`}),(0,a.jsx)(`stop`,{offset:`100%`,stopColor:`#fff`,stopOpacity:`0`})]})]}),(0,a.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${n})`}),(0,a.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${r})`}),(0,a.jsx)(`circle`,{cx:`148`,cy:`10`,r:`34`,fill:e.accent,opacity:`0.18`}),(0,a.jsx)(`circle`,{cx:`148`,cy:`10`,r:`20`,fill:`none`,stroke:`#fff`,strokeWidth:`1.3`,opacity:`0.3`}),(0,a.jsx)(`path`,{d:`M0 70c34-22 72-26 108-10s42 34 52 28v22H0V70Z`,fill:`#fff`,opacity:`0.14`}),(0,a.jsx)(`path`,{d:`M14 26c12 10 32 10 44 0M10 36c16 14 44 14 60 0`,fill:`none`,stroke:`#fff`,strokeWidth:`1.5`,strokeLinecap:`round`,opacity:`0.35`})]})}function p({item:e,number:t}){let n=(0,i.useId)().replace(/:/g,``),r=d(e.sell_price),o=e.username||e.password||``,s=String(t).padStart(2,`0`);return(0,a.jsxs)(`article`,{className:`voucher-card voucher-card--${r.key}`,style:{"--vc-from":r.from,"--vc-to":r.to,"--vc-accent":r.accent,"--vc-ink":r.ink,"--vc-soft":r.soft,"--vc-chip":r.chip},children:[(0,a.jsxs)(`div`,{className:`voucher-card__hero`,children:[(0,a.jsx)(f,{theme:r,uid:n}),(0,a.jsxs)(`div`,{className:`voucher-card__hero-content`,children:[(0,a.jsxs)(`div`,{className:`voucher-card__hero-top`,children:[(0,a.jsxs)(`div`,{className:`voucher-card__brand-block`,children:[e.agent_name&&(0,a.jsx)(`span`,{className:`voucher-card__agent`,children:e.agent_name}),(0,a.jsx)(`span`,{className:`voucher-card__brand`,children:`Hotspot`})]}),(0,a.jsx)(`span`,{className:`voucher-card__tier`,children:r.label})]}),(0,a.jsx)(`p`,{className:`voucher-card__price`,children:e.sell_price_label||`Rp 0`})]})]}),(0,a.jsxs)(`div`,{className:`voucher-card__body`,children:[(0,a.jsxs)(`div`,{className:`voucher-card__creds`,children:[(0,a.jsx)(`span`,{className:`voucher-card__label`,children:`Voucher`}),(0,a.jsx)(`span`,{className:`voucher-card__value`,children:o})]}),(0,a.jsxs)(`div`,{className:`voucher-card__footer`,children:[(0,a.jsxs)(`div`,{className:`voucher-card__footer-main`,children:[e.login_url||e.dns_name?(0,a.jsxs)(`span`,{className:`voucher-card__hint`,children:[(0,a.jsx)(`span`,{children:`Portal tidak muncul?`}),(0,a.jsxs)(`span`,{children:[`Buka `,e.login_url||e.dns_name]})]}):(0,a.jsxs)(`span`,{className:`voucher-card__hint`,children:[(0,a.jsx)(`span`,{children:`Portal tidak muncul?`}),(0,a.jsx)(`span`,{children:`Ketik DNS hotspot di browser.`})]}),e.profile&&(0,a.jsx)(`span`,{children:e.profile})]}),(0,a.jsxs)(`span`,{className:`voucher-card__seq`,children:[`#`,s]})]})]})]})}function m({vouchers:e=[]}){let n=(0,i.useMemo)(()=>u(e,o),[e]);return(0,i.useEffect)(()=>{let e=window.setTimeout(()=>window.print(),400);return()=>window.clearTimeout(e)},[]),(0,a.jsxs)(`div`,{className:`voucher-print`,children:[(0,a.jsx)(t,{title:`Cetak Kartu Voucher`}),(0,a.jsx)(`style`,{children:`
                @page {
                    size: A4 portrait;
                    margin: 0;
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
                    grid-template-columns: repeat(${s}, 1fr);
                    grid-template-rows: repeat(${c}, 1fr);
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

                .voucher-card__creds {
                    display: flex;
                    flex-direction: column;
                    align-items: stretch;
                    gap: 0.4mm;
                    min-width: 0;
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

                    .voucher-sheet {
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
            `}),(0,a.jsxs)(`div`,{className:`voucher-print__toolbar`,children:[(0,a.jsxs)(`div`,{children:[(0,a.jsx)(`h1`,{children:`Cetak Kartu Voucher`}),(0,a.jsxs)(`p`,{children:[e.length,` kartu · `,n.length,` lembar A4 · layout `,s,`×`,c,` `,`(56/lembar) · warna otomatis dari harga`]}),(0,a.jsx)(`div`,{className:`voucher-print__legend`,children:l.map(e=>(0,a.jsxs)(`span`,{children:[(0,a.jsx)(`i`,{style:{background:`linear-gradient(135deg, ${e.from}, ${e.to})`}}),e.label,e.max===1/0?` ≥25rb`:e.max===0?` Rp0`:` ≤${Math.round(e.max/1e3)}rb`]},e.key))})]}),(0,a.jsx)(`button`,{type:`button`,onClick:()=>window.print(),className:`btn-action btn-action-sm btn-primary`,children:`Print ulang`})]}),(0,a.jsx)(`div`,{className:`voucher-print__sheets`,children:n.map((e,t)=>(0,a.jsx)(`section`,{className:`voucher-sheet`,"aria-label":`Lembar ${t+1}`,children:e.map((e,n)=>(0,a.jsx)(p,{item:e,number:t*o+n+1},e.id||e.username))},`page-${t}`))})]})}export{m as default};