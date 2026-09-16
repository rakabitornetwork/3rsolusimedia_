const __vite__mapDeps=(i,m=__vite__mapDeps,d=(m.f||(m.f=["assets/leaflet-src-KhVH0Xxi.js","assets/rolldown-runtime-hePW80VL.js"])))=>i.map(i=>d[i]);
import{r as e}from"./rolldown-runtime-hePW80VL.js";import{$ as t,$t as n,Dt as r,H as i,I as a,It as o,Q as s,Qt as c,Yt as l,_ as u,a as d,an as f,en as p,gt as m,in as h,j as g,n as _,nt as v,on as y,rt as b,x}from"./vendor-ui-D8VWxW0G.js";import{d as S,m as C}from"./vendor-react-Dyo4ernH.js";import{t as w}from"./AdminLayout-BKBRRyLv.js";import{t as T}from"./QuickPayMenu-DYinxB1V.js";import{t as E}from"./search-C70jlkfG.js";import{n as D,r as O,t as k}from"./leaflet-CEWkvHtl.js";import{i as A,n as j,r as M}from"./genieacsMetrics-Ba_bFl71.js";var N=e(C(),1),P=S(),F=[-2.5489,118.0149],I=5,L=3,R=`network-map-marker-style-v4`;function z(){if(typeof document>`u`||document.getElementById(R))return;let e=document.createElement(`style`);e.id=R,e.textContent=`
      @keyframes network-map-pulse {
        0% { transform: translate(-50%, -50%) scale(0.55); opacity: 0.7; }
        70% { transform: translate(-50%, -50%) scale(1.85); opacity: 0; }
        100% { transform: translate(-50%, -50%) scale(1.85); opacity: 0; }
      }
      @keyframes network-map-dollar-pulse {
        0% { transform: translate(-50%, -50%) scale(1); opacity: 0.65; }
        100% { transform: translate(-50%, -50%) scale(1.42); opacity: 0; }
      }
      @keyframes network-map-bounce {
        0%, 100% { transform: translate(-50%, -50%) translateY(0); }
        50% { transform: translate(-50%, -50%) translateY(-3px); }
      }
      .network-map-marker {
        position: relative;
        display: block;
        width: 0;
        height: 0;
        overflow: visible;
        pointer-events: auto;
      }
      .network-map-marker-wrap {
        background: none !important;
        border: none !important;
        overflow: visible !important;
      }
      .network-map-marker__pulse {
        position: absolute;
        left: 0;
        top: 0;
        width: 34px;
        height: 34px;
        border-radius: 9999px;
        border: 2px solid currentColor;
        background: currentColor;
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.55);
        pointer-events: none;
      }
      .network-map-marker.is-hit .network-map-marker__pulse {
        opacity: 0.35;
        animation: network-map-pulse 1.6s ease-out infinite;
      }
      .network-map-marker.is-selected .network-map-marker__pulse {
        width: 42px;
        height: 42px;
        opacity: 0.45;
        animation: network-map-pulse 1.15s ease-out infinite;
      }
      .network-map-marker.is-unpaid .network-map-marker__pulse {
        z-index: 0;
        width: 28px;
        height: 28px;
        border-width: 2px;
        background: currentColor;
        opacity: 0.55;
        animation: network-map-dollar-pulse 1.35s ease-out infinite;
      }
      .network-map-marker.is-unpaid.is-hit .network-map-marker__pulse,
      .network-map-marker.is-unpaid.is-selected .network-map-marker__pulse {
        width: 30px;
        height: 30px;
        opacity: 0.6;
        animation: network-map-dollar-pulse 1.15s ease-out infinite;
      }
      .network-map-marker__dot {
        position: absolute;
        left: 0;
        top: 0;
        width: 14px;
        height: 14px;
        border-radius: 9999px;
        border: 2px solid #fff;
        box-shadow: 0 1px 5px rgba(0,0,0,.4);
        transform: translate(-50%, -50%);
      }
      .network-map-marker.is-hit .network-map-marker__dot {
        width: 18px;
        height: 18px;
        box-shadow: 0 0 0 3px rgba(255,255,255,.9), 0 2px 10px rgba(0,0,0,.45);
        animation: network-map-bounce 1.4s ease-in-out infinite;
      }
      .network-map-marker.is-selected .network-map-marker__dot {
        width: 20px;
        height: 20px;
        box-shadow: 0 0 0 4px rgba(255,255,255,.95), 0 3px 12px rgba(0,0,0,.5);
        animation: network-map-bounce 1.1s ease-in-out infinite;
      }
      .network-map-marker__ring {
        position: absolute;
        left: 0;
        top: 0;
        width: 26px;
        height: 26px;
        border-radius: 9999px;
        border: 2px dashed currentColor;
        opacity: 0;
        transform: translate(-50%, -50%);
        pointer-events: none;
      }
      .network-map-marker.is-hit .network-map-marker__ring,
      .network-map-marker.is-selected .network-map-marker__ring {
        opacity: 0.55;
      }
      .network-map-marker.is-unpaid .network-map-marker__ring {
        opacity: 0;
      }
      .network-map-marker__dollar {
        position: absolute;
        left: 0;
        top: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 9999px;
        border: 2px solid #fff;
        box-shadow: 0 1px 5px rgba(0,0,0,.4);
        transform: translate(-50%, -50%);
        color: #fff;
      }
      .network-map-marker__dollar svg {
        display: block;
        width: 13px;
        height: 13px;
      }
      .network-map-marker.is-unpaid .network-map-marker__dollar {
        animation: network-map-bounce 1.4s ease-in-out infinite;
      }
      .network-map-marker.is-hit .network-map-marker__dollar,
      .network-map-marker.is-selected .network-map-marker__dollar {
        width: 26px;
        height: 26px;
        box-shadow: 0 0 0 3px rgba(255,255,255,.9), 0 2px 10px rgba(0,0,0,.45);
      }
      .network-map-marker.is-selected .network-map-marker__dollar {
        width: 28px;
        height: 28px;
        box-shadow: 0 0 0 4px rgba(255,255,255,.95), 0 3px 12px rgba(0,0,0,.5);
        animation: network-map-bounce 1.1s ease-in-out infinite;
      }
      .network-map-marker.is-hit .network-map-marker__dollar svg,
      .network-map-marker.is-selected .network-map-marker__dollar svg {
        width: 15px;
        height: 15px;
      }
    `,document.head.appendChild(e)}var B=`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>`;function V(e,{selected:t=!1,highlighted:n=!1,unpaidToday:r=!1}={}){return`<span class="${[`network-map-marker`,n?`is-hit`:``,t?`is-selected`:``,r?`is-unpaid`:``].filter(Boolean).join(` `)}" style="color:${e}">
      <span class="network-map-marker__pulse"></span>
      <span class="network-map-marker__ring"></span>
      ${r?`<span class="network-map-marker__dollar" style="background:${e}">${B}</span>`:`<span class="network-map-marker__dot" style="background:${e}"></span>`}
    </span>`}var H=[{value:`all`,label:`Semua status`},{value:`active`,label:`Aktif`},{value:`isolated`,label:`Isolir`},{value:`disabled`,label:`Nonaktif`},{value:`grace`,label:`Grace`},{value:`overdue`,label:`Lewat tempo`}],U={active:`Aktif`,isolated:`Isolir`,disabled:`Nonaktif`};function W(e){if(e==null||Number.isNaN(Number(e)))return`0 bps`;let t=Number(e),n=[`bps`,`Kbps`,`Mbps`,`Gbps`],r=0;for(;t>=1e3&&r<n.length-1;)t/=1e3,r+=1;return`${t.toFixed(r===0?0:2)} ${n[r]}`}function G(e){if(e.status===`isolated`)return`#e11d48`;if(e.status===`disabled`)return`#64748b`;if(e.unpaid_today)return`#ca8a04`;if(e.session_online)return`#059669`;let t=e.optical?.rx_power;if(t!=null){let e=M(t).key;return e===`bad`?`#e11d48`:e===`warn`?`#d97706`:`#0d9488`}return`#2563eb`}function K(e){return e===`isolated`?`bg-rose-50 text-rose-700`:e===`disabled`?`bg-slate-100 text-slate-600`:`bg-emerald-50 text-emerald-700`}function q({icon:e,label:t,value:n,tone:r}){return(0,P.jsxs)(`div`,{className:`border px-3 py-2.5 ${r?.card||`border-ink/10 bg-mist/40`}`,children:[(0,P.jsxs)(`div`,{className:`flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-ink-soft uppercase`,children:[(0,P.jsx)(e,{className:`h-3.5 w-3.5 ${r?.icon||`text-ink-soft`}`,strokeWidth:2}),t]}),(0,P.jsx)(`p`,{className:`mt-1 text-sm font-semibold ${r?.text||`text-ink`}`,children:n})]})}function J({values:e,stroke:t=`#0d9488`}){let n=e?.length?e:[0],r=Math.max(...n,1),i=n.length>1?120/(n.length-1):120,a=n.map((e,t)=>{let n=t*i,a=36-e/r*32-2;return`${t===0?`M`:`L`}${n.toFixed(1)},${a.toFixed(1)}`}).join(` `);return(0,P.jsx)(`svg`,{viewBox:`0 0 120 36`,className:`h-9 w-full`,preserveAspectRatio:`none`,children:(0,P.jsx)(`path`,{d:a,fill:`none`,stroke:t,strokeWidth:`1.75`})})}function Y(e){let[t,n]=(0,N.useState)(null),[r,i]=(0,N.useState)({rx:[],tx:[]}),[a,o]=(0,N.useState)(``),[s,c]=(0,N.useState)(null),[l,u]=(0,N.useState)(!1);return(0,N.useEffect)(()=>{if(!e){n(null),i({rx:[],tx:[]}),o(``),c(null),u(!1);return}let t=!1,r=!1,a=async()=>{if(!(r||t)){r=!0,u(!0);try{let r=await(await fetch(`/admin/network/map/customers/${e}/traffic`,{headers:{Accept:`application/json`,"X-Requested-With":`XMLHttpRequest`},credentials:`same-origin`})).json();if(t)return;if(c(!!r.online),!r.ok){o(r.message||`Traffic tidak tersedia`),n(null);return}o(``),n(r.data),i(e=>({rx:[...e.rx,r.data.rx_bps].slice(-24),tx:[...e.tx,r.data.tx_bps].slice(-24)}))}catch{t||(o(`Tidak bisa mengambil live traffic`),c(null))}finally{r=!1,t||u(!1)}}};i({rx:[],tx:[]}),n(null),o(``),a();let s=window.setInterval(a,L*1e3);return()=>{t=!0,window.clearInterval(s)}},[e]),{traffic:t,history:r,error:a,online:s,loading:l}}function X({customers:t,selectedId:n,onSelect:r,filterActive:i=!1,active:a=!0}){let o=(0,N.useId)().replace(/:/g,``),s=(0,N.useRef)(null),c=(0,N.useRef)(null),l=(0,N.useRef)(new Map),u=(0,N.useRef)(null),d=(0,N.useRef)(r),[f,p]=(0,N.useState)(!1),m=(0,N.useRef)(``),h=(0,N.useRef)(null),g=(0,N.useRef)(null),_=(0,N.useRef)(t),v=(0,N.useRef)(i);(0,N.useEffect)(()=>{d.current=r},[r]),(0,N.useEffect)(()=>{_.current=t,v.current=i},[t,i]);let b=(e,t)=>{let n=c.current,r=u.current;if(!(!n||!r||!e.length))try{if(n.invalidateSize(),e.length===1)n.flyTo(e[0],t?17:16,{animate:!0,duration:1.15,easeLinearity:.2});else{let i=r.latLngBounds(e);i.isValid()&&n.flyToBounds(i,{padding:t?[56,56]:[44,44],maxZoom:t&&e.length<=5?16:15,animate:!0,duration:1.2,easeLinearity:.22})}}catch(e){console.error(`Gagal fokususkan peta:`,e)}};return(0,N.useEffect)(()=>{z();let t=!1;return(async()=>{try{let n=await y(()=>import(`./leaflet-src-KhVH0Xxi.js`).then(t=>e(t.default,1)),__vite__mapDeps([0,1])),r=n.default||n;if(t||!s.current)return;if(u.current=r,delete r.Icon.Default.prototype._getIconUrl,r.Icon.Default.mergeOptions({iconRetinaUrl:O,iconUrl:D,shadowUrl:k}),c.current){p(!0);return}let i=r.map(s.current,{center:F,zoom:I,scrollWheelZoom:!0,zoomAnimation:!0,markerZoomAnimation:!0,fadeAnimation:!0});r.tileLayer(`https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png`,{attribution:`&copy; OpenStreetMap`,maxZoom:19}).addTo(i),c.current=i,p(!0),g.current=window.setTimeout(()=>{try{i.invalidateSize()}catch{}},120)}catch(e){console.error(`Gagal inisialisasi peta:`,e)}})(),()=>{if(t=!0,h.current&&=(window.clearTimeout(h.current),null),g.current&&=(window.clearTimeout(g.current),null),c.current){try{c.current.remove()}catch{}c.current=null,l.current=new Map}p(!1)}},[]),(0,N.useEffect)(()=>{if(!f||!a||!c.current)return;let e=window.setTimeout(()=>{try{c.current?.invalidateSize();let e=(_.current||[]).filter(e=>e.on_map).map(e=>[Number(e.latitude),Number(e.longitude)]).filter(([e,t])=>Number.isFinite(e)&&Number.isFinite(t));e.length&&b(e,v.current)}catch(e){console.error(`Gagal refresh ukuran peta:`,e)}},160);return()=>window.clearTimeout(e)},[a,f]),(0,N.useEffect)(()=>{let e=c.current,r=u.current;if(!(!f||!e||!r))try{let o=new Set,s=[];t.forEach(t=>{if(!t.on_map)return;let a=Number(t.latitude),c=Number(t.longitude);if(!Number.isFinite(a)||!Number.isFinite(c))return;o.add(t.id),s.push([a,c]);let u=G(t),f=t.id===n,p=!!t.unpaid_today,m=i||f,h=V(u,{selected:f,highlighted:m,unpaidToday:p}),g=r.divIcon({className:`network-map-marker-wrap`,html:h,iconSize:[0,0],iconAnchor:[0,0]}),_=l.current.get(t.id);_?(_.setLatLng([a,c]),_.setZIndexOffset(f?1e3:p?750:m?500:0),_.setIcon(g)):(_=r.marker([a,c],{icon:g,zIndexOffset:f?1e3:p?750:m?500:0}).addTo(e),_.on(`click`,()=>d.current?.(t.id)),l.current.set(t.id,_));let v=e=>String(e||``).replace(/&/g,`&amp;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`),y=v(t.name),b=v(t.username),x=v(t.session_ip);_.bindTooltip(`<strong>${y}</strong><br/><span style="opacity:.8">${b}</span>${x?`<br/><span style="opacity:.8">${x}</span>`:``}${p?`<br/><span style="color:#ca8a04;font-weight:600">Belum bayar hari ini</span>`:``}`,{direction:`top`,offset:[0,p?-18:-14]})});for(let[t,n]of l.current.entries())o.has(t)||(e.removeLayer(n),l.current.delete(t));let c=`${i?`f1`:`f0`}|${s.map(e=>e.join(`,`)).join(`|`)}`;if(c!==m.current)m.current=c,h.current&&window.clearTimeout(h.current),a&&(h.current=window.setTimeout(()=>{h.current=null,b(s,i)},100));else if(a)try{e.invalidateSize()}catch{}}catch(e){console.error(`Gagal memperbarui marker peta:`,e)}},[t,n,f,i,a]),(0,N.useEffect)(()=>{let e=c.current;if(!f||!e||!n||!a)return;let t=l.current.get(n);if(t&&!h.current)try{let n=Math.max(e.getZoom()||I,15);e.flyTo(t.getLatLng(),n,{animate:!0,duration:.9,easeLinearity:.25})}catch(e){console.error(`Gagal fokususkan marker terpilih:`,e)}},[n,f,a]),(0,P.jsx)(`div`,{ref:s,id:o,className:`h-full min-h-[320px] w-full bg-mist`})}function Z({customer:e,paymentMethods:t,onClose:n}){let{auth:r}=h().props,i=r?.user?.can_write!==!1,a=Y(e?.id),[o,s]=(0,N.useState)(!1),c=e?.optical,l=A(c?.temperature),u=M(c?.rx_power),d=j(c?.online_ont),p=[c?.manufacturer,c?.model].filter(Boolean).join(` `)||c?.model||`—`;if(!e)return null;let m=()=>{!i||o||!c?.device_id||window.confirm(`Reboot ONT pelanggan ${e.name}? Perangkat akan restart via GenieACS.`)&&(s(!0),f.post(`/admin/network/map/customers/${e.id}/reboot`,{device_id:c.device_id},{preserveScroll:!0,preserveState:!0,onFinish:()=>s(!1)}))};return(0,P.jsxs)(P.Fragment,{children:[(0,P.jsx)(`div`,{className:`absolute inset-x-0 bottom-0 z-[500] flex max-h-[70%] flex-col border-t border-ink/10 bg-white shadow-[0_-8px_30px_rgba(0,0,0,.12)] lg:hidden`,children:(0,P.jsx)(Q,{customer:e,optical:c,modelLabel:p,tempTone:l,rxTone:u,ontTone:d,poll:a,canWrite:i,paymentMethods:t,rebooting:o,onClose:n,onReboot:m})}),(0,P.jsx)(`aside`,{className:`hidden w-full flex-col border-t border-ink/10 bg-white lg:flex lg:w-[340px] lg:border-t-0 lg:border-l`,children:(0,P.jsx)(Q,{customer:e,optical:c,modelLabel:p,tempTone:l,rxTone:u,ontTone:d,poll:a,canWrite:i,paymentMethods:t,rebooting:o,onClose:n,onReboot:m})})]})}function Q({customer:e,optical:t,modelLabel:n,tempTone:s,rxTone:f,ontTone:p,poll:h,canWrite:g,paymentMethods:y=[],rebooting:b,onClose:S,onReboot:C}){let w=Array.isArray(e.unpaid_invoices)?e.unpaid_invoices:[];return(0,P.jsxs)(P.Fragment,{children:[(0,P.jsxs)(`div`,{className:`flex items-start justify-between gap-3 border-b border-ink/10 px-4 py-3`,children:[(0,P.jsxs)(`div`,{className:`min-w-0`,children:[(0,P.jsx)(`p`,{className:`truncate font-display text-base font-bold text-ink`,children:e.name}),(0,P.jsx)(`p`,{className:`mt-0.5 font-mono text-xs text-ink-soft`,children:e.username})]}),(0,P.jsx)(`button`,{type:`button`,onClick:S,className:`rounded-md p-1.5 text-ink-soft hover:bg-mist hover:text-ink`,"aria-label":`Tutup detail`,children:(0,P.jsx)(_,{className:`h-4 w-4`})})]}),(0,P.jsxs)(`div`,{className:`flex-1 space-y-4 overflow-y-auto px-4 py-4`,children:[(0,P.jsxs)(`div`,{className:`flex flex-wrap gap-2`,children:[(0,P.jsx)(`span`,{className:`px-2 py-1 text-[11px] font-semibold ${K(e.status)}`,children:U[e.status]||e.status}),(0,P.jsx)(`span`,{className:`px-2 py-1 text-[11px] font-semibold ${e.session_online||h.online?`bg-emerald-50 text-emerald-700`:`bg-slate-100 text-slate-600`}`,children:e.session_online||h.online?`PPPoE online`:`PPPoE offline`}),!e.on_map&&(0,P.jsx)(`span`,{className:`bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700`,children:`Tanpa GPS`}),e.unpaid_today&&(0,P.jsxs)(`span`,{className:`inline-flex items-center gap-1 bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-800`,children:[(0,P.jsx)(o,{className:`h-3 w-3`}),`Belum bayar hari ini`]})]}),(0,P.jsxs)(`dl`,{className:`grid grid-cols-1 gap-2 text-sm`,children:[(0,P.jsxs)(`div`,{className:`flex justify-between gap-3 border-b border-ink/5 py-1.5`,children:[(0,P.jsx)(`dt`,{className:`text-ink-soft`,children:`Paket`}),(0,P.jsx)(`dd`,{className:`text-right font-medium text-ink`,children:e.package?.name||`—`})]}),(0,P.jsxs)(`div`,{className:`flex justify-between gap-3 border-b border-ink/5 py-1.5`,children:[(0,P.jsx)(`dt`,{className:`text-ink-soft`,children:`Router`}),(0,P.jsx)(`dd`,{className:`text-right font-medium text-ink`,children:e.router?.name||`—`})]}),(0,P.jsxs)(`div`,{className:`flex justify-between gap-3 border-b border-ink/5 py-1.5`,children:[(0,P.jsxs)(`dt`,{className:`inline-flex items-center gap-1.5 text-ink-soft`,children:[(0,P.jsx)(m,{className:`h-3.5 w-3.5`}),`IP`]}),(0,P.jsx)(`dd`,{className:`text-right font-mono text-sm font-medium text-ink`,children:e.session_ip||`—`})]}),(0,P.jsxs)(`div`,{className:`flex justify-between gap-3 border-b border-ink/5 py-1.5`,children:[(0,P.jsx)(`dt`,{className:`text-ink-soft`,children:`Telepon`}),(0,P.jsx)(`dd`,{className:`text-right font-medium text-ink`,children:e.phone||`—`})]}),(0,P.jsxs)(`div`,{className:`flex justify-between gap-3 py-1.5`,children:[(0,P.jsx)(`dt`,{className:`text-ink-soft`,children:`Alamat`}),(0,P.jsx)(`dd`,{className:`max-w-[60%] text-right font-medium text-ink`,children:e.address||`—`})]})]}),w.length>0&&(0,P.jsxs)(`div`,{children:[(0,P.jsx)(`h3`,{className:`text-xs font-semibold tracking-wide text-ink-soft uppercase`,children:`Tagihan`}),(0,P.jsx)(`div`,{className:`mt-2 space-y-2`,children:w.map(e=>(0,P.jsxs)(`div`,{className:`flex items-start justify-between gap-3 border border-ink/10 px-3 py-2.5`,children:[(0,P.jsxs)(`div`,{className:`min-w-0`,children:[(0,P.jsx)(`p`,{className:`truncate text-sm font-semibold text-ink`,children:e.number}),(0,P.jsxs)(`p`,{className:`mt-0.5 text-xs text-ink-soft`,children:[e.total_label,e.due_date?` · ${e.due_date}`:``]})]}),g&&(0,P.jsx)(T,{invoice:e,methods:y})]},e.id))})]}),(0,P.jsxs)(`div`,{children:[(0,P.jsx)(`h3`,{className:`text-xs font-semibold tracking-wide text-ink-soft uppercase`,children:`Optik ONT`}),t?.matched?(0,P.jsxs)(`div`,{className:`mt-2 space-y-2`,children:[(0,P.jsxs)(`div`,{className:`grid grid-cols-2 gap-2`,children:[(0,P.jsx)(q,{icon:u,label:`Suhu`,value:t.temperature_label||`—`,tone:s}),(0,P.jsx)(q,{icon:x,label:`RX Power`,value:t.rx_power_label||`—`,tone:f})]}),(0,P.jsxs)(`div`,{className:`flex items-start justify-between gap-3 border border-ink/10 px-3 py-2.5 text-xs`,children:[(0,P.jsxs)(`span`,{className:`inline-flex items-center gap-1.5 text-ink-soft`,children:[(0,P.jsx)(r,{className:`h-3.5 w-3.5 shrink-0`}),`Model`]}),(0,P.jsx)(`span`,{className:`max-w-[65%] text-right font-semibold text-ink`,children:n})]}),(0,P.jsxs)(`div`,{className:`flex items-center justify-between border border-ink/10 px-3 py-2 text-xs`,children:[(0,P.jsx)(`span`,{className:`text-ink-soft`,children:`Serial / ONT`}),(0,P.jsxs)(`span`,{className:`font-semibold ${p.text}`,children:[t.serial||`—`,t.online_ont?` · online`:` · offline`]})]}),g&&(0,P.jsxs)(`button`,{type:`button`,onClick:C,disabled:b||!t.device_id,className:`btn-action btn-action-sm inline-flex w-full items-center justify-center gap-2 border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 disabled:opacity-60`,children:[b?(0,P.jsx)(a,{className:`h-3.5 w-3.5 animate-spin`}):(0,P.jsx)(i,{className:`h-3.5 w-3.5`}),b?`Mengirim reboot...`:`Reboot ONT`]})]}):(0,P.jsx)(`p`,{className:`mt-2 border border-dashed border-ink/15 bg-mist/50 px-3 py-3 text-xs leading-relaxed text-ink-soft`,children:`ONT tidak cocok / username PPPoE tidak ditemukan di GenieACS.`})]}),(0,P.jsxs)(`div`,{children:[(0,P.jsxs)(`div`,{className:`flex items-center justify-between gap-2`,children:[(0,P.jsx)(`h3`,{className:`text-xs font-semibold tracking-wide text-ink-soft uppercase`,children:`Live trafik`}),h.loading&&(0,P.jsx)(v,{className:`h-3.5 w-3.5 animate-spin text-ink-soft`})]}),h.error&&!h.traffic?(0,P.jsxs)(`p`,{className:`mt-2 flex items-start gap-2 border border-ink/10 bg-mist/40 px-3 py-3 text-xs text-ink-soft`,children:[(0,P.jsx)(d,{className:`mt-0.5 h-3.5 w-3.5 shrink-0`}),h.error]}):(0,P.jsxs)(`div`,{className:`mt-2 space-y-3 border border-ink/10 p-3`,children:[(0,P.jsxs)(`div`,{children:[(0,P.jsxs)(`div`,{className:`flex items-center justify-between text-xs`,children:[(0,P.jsxs)(`span`,{className:`inline-flex items-center gap-1 font-semibold text-teal-700`,children:[(0,P.jsx)(c,{className:`h-3.5 w-3.5`}),`RX`]}),(0,P.jsx)(`span`,{className:`font-mono font-semibold text-ink`,children:W(h.traffic?.rx_bps)})]}),(0,P.jsx)(J,{values:h.history.rx,stroke:`#0d9488`})]}),(0,P.jsxs)(`div`,{children:[(0,P.jsxs)(`div`,{className:`flex items-center justify-between text-xs`,children:[(0,P.jsxs)(`span`,{className:`inline-flex items-center gap-1 font-semibold text-sky-700`,children:[(0,P.jsx)(l,{className:`h-3.5 w-3.5`}),`TX`]}),(0,P.jsx)(`span`,{className:`font-mono font-semibold text-ink`,children:W(h.traffic?.tx_bps)})]}),(0,P.jsx)(J,{values:h.history.tx,stroke:`#0284c7`})]})]})]})]})]})}function $({customers:e=[],routers:r=[],filters:i={},stats:a={},optical_meta:c={},payment_methods:l=[]}){let[u,d]=(0,N.useState)(i.q||``),[m,h]=(0,N.useState)(i.status||`all`),[_,v]=(0,N.useState)(i.router_id?String(i.router_id):``),[y,x]=(0,N.useState)(null),[S,C]=(0,N.useState)(`map`),[T,D]=(0,N.useState)(!1),O=!!u.trim()||m&&m!==`all`||!!_,k=(0,N.useRef)(m),A=(0,N.useRef)(_),j=T||S===`map`;(0,N.useEffect)(()=>{if(typeof window>`u`)return;let e=window.matchMedia(`(min-width: 1024px)`),t=()=>D(e.matches);return t(),e.addEventListener(`change`,t),()=>e.removeEventListener(`change`,t)},[]);let M=(0,N.useMemo)(()=>e.filter(e=>E(u,e.name,e.username,e.phone,e.address,e.session_ip)),[e,u]),F=(0,N.useMemo)(()=>M.find(e=>e.id===y)||e.find(e=>e.id===y)||null,[M,e,y]),I=(0,N.useMemo)(()=>M.filter(e=>e.on_map),[M]),L=({nextStatus:e=m,nextRouterId:t=_}={})=>{f.get(`/admin/network/map`,{status:e&&e!==`all`?e:void 0,router_id:t||void 0},{preserveState:!0,replace:!0,preserveScroll:!0})};(0,N.useEffect)(()=>{h(i.status||`all`),v(i.router_id?String(i.router_id):``)},[i.status,i.router_id]),(0,N.useEffect)(()=>{let e=k.current!==m,t=A.current!==_;k.current=m,A.current=_,!(!e&&!t)&&I.length>0&&C(`map`)},[m,_,I.length]);let R=t=>{x(t),e.find(e=>e.id===t)?.on_map&&C(`map`)};return(0,P.jsxs)(w,{title:`Peta Jaringan`,subtitle:`Sebaran pelanggan, optik ONT, dan live trafik`,children:[(0,P.jsx)(p,{title:`Peta Jaringan`}),(0,P.jsxs)(`div`,{className:`-mx-4 -my-6 flex h-[calc(100dvh-7.5rem)] min-h-[480px] flex-col overflow-hidden border-y border-ink/10 bg-white sm:-mx-6 lg:-mx-8`,children:[(0,P.jsxs)(`div`,{className:`flex flex-wrap items-center gap-3 border-b border-ink/10 bg-mist/40 px-4 py-2.5 text-xs text-ink-soft sm:px-5`,children:[(0,P.jsxs)(`span`,{children:[(0,P.jsx)(`strong`,{className:`text-ink`,children:a.total??0}),` pelanggan`]}),(0,P.jsx)(`span`,{className:`text-ink/20`,children:`·`}),(0,P.jsxs)(`span`,{children:[(0,P.jsx)(`strong`,{className:`text-ink`,children:a.on_map??0}),` di peta`]}),(0,P.jsx)(`span`,{className:`text-ink/20`,children:`·`}),(0,P.jsxs)(`span`,{children:[(0,P.jsx)(`strong`,{className:`text-ink`,children:a.optical_matched??0}),` optik cocok`]}),(0,P.jsx)(`span`,{className:`text-ink/20`,children:`·`}),(0,P.jsxs)(`span`,{children:[(0,P.jsx)(`strong`,{className:`text-ink`,children:a.session_online??0}),` sesi online`]}),(0,P.jsx)(`span`,{className:`text-ink/20`,children:`·`}),(0,P.jsxs)(`span`,{className:`inline-flex items-center gap-1`,children:[(0,P.jsx)(o,{className:`h-3.5 w-3.5 text-amber-600`}),(0,P.jsx)(`strong`,{className:`text-ink`,children:a.unpaid_today??0}),` belum bayar hari ini`]}),!c.enabled&&(0,P.jsxs)(P.Fragment,{children:[(0,P.jsx)(`span`,{className:`text-ink/20`,children:`·`}),(0,P.jsx)(`span`,{className:`text-amber-700`,children:c.message||`GenieACS belum dikonfigurasi`})]}),c.enabled&&c.ok===!1&&c.message&&(0,P.jsxs)(P.Fragment,{children:[(0,P.jsx)(`span`,{className:`text-ink/20`,children:`·`}),(0,P.jsx)(`span`,{className:`text-rose-700`,children:c.message})]})]}),(0,P.jsxs)(`div`,{className:`grid grid-cols-2 border-b border-ink/10 lg:hidden`,children:[(0,P.jsxs)(`button`,{type:`button`,onClick:()=>C(`map`),className:`inline-flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold transition ${S===`map`?`border-b-2 border-signal text-signal-deep`:`text-ink-soft`}`,children:[(0,P.jsx)(s,{className:`h-4 w-4`}),`Peta`]}),(0,P.jsxs)(`button`,{type:`button`,onClick:()=>C(`list`),className:`inline-flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold transition ${S===`list`?`border-b-2 border-signal text-signal-deep`:`text-ink-soft`}`,children:[(0,P.jsx)(b,{className:`h-4 w-4`}),`Daftar`]})]}),(0,P.jsxs)(`div`,{className:`relative flex min-h-0 flex-1 flex-col lg:flex-row`,children:[(0,P.jsxs)(`aside`,{className:`${S===`list`?`flex`:`hidden`} h-full min-h-0 w-full flex-col border-ink/10 lg:flex lg:w-[340px] lg:shrink-0 lg:border-r`,children:[(0,P.jsxs)(`div`,{className:`space-y-2 border-b border-ink/10 p-3`,children:[(0,P.jsxs)(`label`,{className:`relative block`,children:[(0,P.jsx)(g,{className:`pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-ink-soft`}),(0,P.jsx)(`input`,{type:`search`,value:u,onChange:e=>d(e.target.value),placeholder:`Cari nama / username / telepon / IP`,className:`w-full border border-ink/15 bg-white py-2 pr-3 pl-8 text-sm outline-none focus:border-signal`})]}),(0,P.jsxs)(`select`,{value:_,onChange:e=>{let t=e.target.value;v(t),L({nextRouterId:t})},className:`w-full border border-ink/15 bg-white px-3 py-2 text-sm outline-none focus:border-signal`,"aria-label":`Filter RouterOS`,children:[(0,P.jsx)(`option`,{value:``,children:`Semua RouterOS`}),r.map(e=>(0,P.jsx)(`option`,{value:String(e.id),children:e.name},e.id))]}),(0,P.jsx)(`select`,{value:m,onChange:e=>{let t=e.target.value;h(t),L({nextStatus:t})},className:`w-full border border-ink/15 bg-white px-3 py-2 text-sm outline-none focus:border-signal`,children:H.map(e=>(0,P.jsx)(`option`,{value:e.value,children:e.label},e.value))})]}),(0,P.jsxs)(`ul`,{className:`min-h-0 flex-1 overflow-y-auto`,children:[M.length===0&&(0,P.jsx)(`li`,{className:`px-4 py-8 text-center text-sm text-ink-soft`,children:u.trim()?`Tidak ada pelanggan yang cocok.`:`Tidak ada pelanggan.`}),M.map(e=>{let r=e.id===y;return(0,P.jsx)(`li`,{children:(0,P.jsxs)(`button`,{type:`button`,onClick:()=>R(e.id),className:`flex w-full items-start gap-3 border-b border-ink/5 px-3 py-3 text-left transition ${r?`bg-signal/10`:`hover:bg-mist/70`}`,children:[(0,P.jsx)(`span`,{className:`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full`,style:{background:G(e)}}),(0,P.jsxs)(`span`,{className:`min-w-0 flex-1`,children:[(0,P.jsxs)(`span`,{className:`flex items-center gap-2`,children:[(0,P.jsx)(`span`,{className:`truncate text-sm font-semibold text-ink`,children:e.name}),e.unpaid_today&&(0,P.jsx)(o,{className:`h-3.5 w-3.5 shrink-0 text-amber-600`}),!e.on_map&&(0,P.jsx)(t,{className:`h-3 w-3 shrink-0 text-amber-600`})]}),(0,P.jsx)(`span`,{className:`mt-0.5 block truncate font-mono text-[11px] text-ink-soft`,children:e.username}),(0,P.jsx)(`span`,{className:`mt-0.5 block truncate font-mono text-[11px] text-ink-soft`,children:e.session_ip||`IP —`}),(0,P.jsxs)(`span`,{className:`mt-1 flex flex-wrap gap-1.5`,children:[(0,P.jsx)(`span`,{className:`px-1.5 py-0.5 text-[10px] font-semibold ${K(e.status)}`,children:U[e.status]||e.status}),e.optical?.rx_power_label&&(0,P.jsxs)(`span`,{className:`bg-ink/5 px-1.5 py-0.5 text-[10px] font-semibold text-ink-soft`,children:[`RX `,e.optical.rx_power_label]}),e.session_online&&(0,P.jsxs)(`span`,{className:`inline-flex items-center gap-0.5 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700`,children:[(0,P.jsx)(n,{className:`h-2.5 w-2.5`}),`online`]})]})]})]})},e.id)})]})]}),(0,P.jsxs)(`div`,{className:`${S===`map`?`relative flex min-h-0 flex-1 flex-col`:`pointer-events-none invisible absolute inset-0 lg:pointer-events-auto lg:visible lg:relative lg:flex lg:min-h-0 lg:flex-1 lg:flex-col`} min-w-0`,children:[(0,P.jsx)(X,{customers:I,selectedId:y,onSelect:R,filterActive:O,active:j}),(0,P.jsx)(`div`,{className:`pointer-events-none absolute inset-x-0 top-3 z-[400] flex justify-center px-3 lg:hidden`,children:(0,P.jsxs)(`label`,{className:`pointer-events-auto w-full max-w-xs`,children:[(0,P.jsx)(`span`,{className:`sr-only`,children:`Filter RouterOS`}),(0,P.jsxs)(`select`,{value:_,onChange:e=>{let t=e.target.value;v(t),L({nextRouterId:t})},className:`w-full border border-ink/15 bg-white/95 px-3 py-2 text-sm shadow-sm outline-none backdrop-blur focus:border-signal`,children:[(0,P.jsx)(`option`,{value:``,children:`Semua RouterOS`}),r.map(e=>(0,P.jsx)(`option`,{value:String(e.id),children:e.name},`map-${e.id}`))]})]})}),I.length===0&&(0,P.jsx)(`div`,{className:`pointer-events-none absolute inset-0 flex items-center justify-center bg-white/50 p-6`,children:(0,P.jsx)(`p`,{className:`max-w-sm border border-ink/10 bg-white px-4 py-3 text-center text-sm text-ink-soft shadow-sm`,children:`Belum ada pelanggan dengan koordinat GPS pada filter ini.`})})]}),F&&(0,P.jsx)(Z,{customer:F,paymentMethods:l,onClose:()=>x(null)})]})]})]})}export{$ as default};