import{r as e}from"./rolldown-runtime-hePW80VL.js";import{Ht as t}from"./vendor-ui-DpqPrKLc.js";import{d as n,m as r}from"./vendor-react-Dyo4ernH.js";var i=e(r(),1),a=n(),o=56,s=7,c=8;function l(e,t){let n=[];for(let r=0;r<e.length;r+=t)n.push(e.slice(r,r+t));return n.length>0?n:[[]]}function u({item:e}){let t=[e.profile,e.limit_uptime].filter(Boolean).join(` · `);return(0,a.jsxs)(`article`,{className:`voucher-card`,children:[(0,a.jsxs)(`div`,{className:`voucher-card__top`,children:[(0,a.jsx)(`span`,{className:`voucher-card__brand`,children:`Hotspot`}),(0,a.jsx)(`span`,{className:`voucher-card__price`,children:e.sell_price_label||`Rp 0`})]}),(0,a.jsxs)(`div`,{className:`voucher-card__creds`,children:[(0,a.jsxs)(`div`,{className:`voucher-card__row`,children:[(0,a.jsx)(`span`,{className:`voucher-card__label`,children:`User`}),(0,a.jsx)(`span`,{className:`voucher-card__value`,children:e.username})]}),(0,a.jsxs)(`div`,{className:`voucher-card__row`,children:[(0,a.jsx)(`span`,{className:`voucher-card__label`,children:`Pass`}),(0,a.jsx)(`span`,{className:`voucher-card__value`,children:e.password})]})]}),(t||e.agent_name)&&(0,a.jsxs)(`div`,{className:`voucher-card__footer`,children:[t&&(0,a.jsx)(`span`,{children:t}),e.agent_name&&(0,a.jsxs)(`span`,{children:[`Agen: `,e.agent_name]})]})]})}function d({vouchers:e=[]}){let n=(0,i.useMemo)(()=>l(e,o),[e]);return(0,i.useEffect)(()=>{let e=window.setTimeout(()=>window.print(),400);return()=>window.clearTimeout(e)},[]),(0,a.jsxs)(`div`,{className:`voucher-print`,children:[(0,a.jsx)(t,{title:`Cetak Kartu Voucher`}),(0,a.jsx)(`style`,{children:`
                @page {
                    size: A4 portrait;
                    margin: 0;
                }

                .voucher-print {
                    min-height: 100vh;
                    background: #e8eef2;
                    color: #101820;
                    font-family: 'Manrope Variable', Manrope, ui-sans-serif, system-ui, sans-serif;
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
                    padding: 3mm;
                    background: #fff;
                    box-shadow: 0 8px 28px rgba(16, 24, 32, 0.12);
                    display: grid;
                    grid-template-columns: repeat(${s}, 1fr);
                    grid-template-rows: repeat(${c}, 1fr);
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
                    padding: 1.4mm 1.6mm;
                    border: 0.35pt dashed rgba(16, 24, 32, 0.45);
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                    gap: 0.6mm;
                    overflow: hidden;
                    background: #fff;
                }

                .voucher-card__top {
                    display: flex;
                    align-items: baseline;
                    justify-content: space-between;
                    gap: 1mm;
                    border-bottom: 0.35pt solid rgba(16, 24, 32, 0.18);
                    padding-bottom: 0.5mm;
                }

                .voucher-card__brand {
                    font-size: 6.5pt;
                    font-weight: 700;
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                    color: #2a3540;
                    line-height: 1.1;
                }

                .voucher-card__price {
                    font-size: 8pt;
                    font-weight: 800;
                    line-height: 1.1;
                    white-space: nowrap;
                }

                .voucher-card__creds {
                    display: flex;
                    flex-direction: column;
                    gap: 0.35mm;
                    flex: 1;
                    justify-content: center;
                    min-height: 0;
                }

                .voucher-card__row {
                    display: grid;
                    grid-template-columns: 9mm 1fr;
                    align-items: center;
                    gap: 0.8mm;
                    min-width: 0;
                }

                .voucher-card__label {
                    font-size: 6pt;
                    color: #2a3540;
                    line-height: 1;
                }

                .voucher-card__value {
                    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
                    font-size: 8.2pt;
                    font-weight: 700;
                    letter-spacing: 0.03em;
                    line-height: 1.15;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                .voucher-card__footer {
                    display: flex;
                    flex-direction: column;
                    gap: 0.2mm;
                    font-size: 5.5pt;
                    color: #2a3540;
                    line-height: 1.15;
                    overflow: hidden;
                }

                .voucher-card__footer span {
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
            `}),(0,a.jsxs)(`div`,{className:`voucher-print__toolbar`,children:[(0,a.jsxs)(`div`,{children:[(0,a.jsx)(`h1`,{children:`Cetak Kartu Voucher`}),(0,a.jsxs)(`p`,{children:[e.length,` kartu · `,n.length,` lembar A4 · layout `,s,`×`,c,` `,`(56/lembar)`]})]}),(0,a.jsx)(`button`,{type:`button`,onClick:()=>window.print(),className:`btn-action btn-action-sm btn-primary`,children:`Print ulang`})]}),(0,a.jsx)(`div`,{className:`voucher-print__sheets`,children:n.map((e,t)=>(0,a.jsx)(`section`,{className:`voucher-sheet`,"aria-label":`Lembar ${t+1}`,children:e.map(e=>(0,a.jsx)(u,{item:e},e.id||e.username))},`page-${t}`))})]})}export{d as default};