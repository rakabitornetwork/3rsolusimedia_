import{r as e}from"./rolldown-runtime-hePW80VL.js";import{Qt as t}from"./vendor-ui-MghJUXd6.js";import{d as n,m as r}from"./vendor-react-Dyo4ernH.js";var i=e(r(),1),a=(e=>(e[e.Border=-1]=`Border`,e[e.Data=0]=`Data`,e[e.Function=1]=`Function`,e[e.Position=2]=`Position`,e[e.Timing=3]=`Timing`,e[e.Alignment=4]=`Alignment`,e))(a||{}),o=[0,1],s=[1,0],c=[2,3],l=[3,2],u={L:o,M:s,Q:c,H:l},d=/^\d*$/,f=/^[A-Z0-9 $%*+./:-]*$/,p=`0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:`,m=1,h=40,g=3,_=3,v=40,y=10,b=[[-1,7,10,15,20,26,18,20,24,30,18,20,24,26,30,22,24,28,30,28,28,28,28,30,30,26,28,30,30,30,30,30,30,30,30,30,30,30,30,30,30],[-1,10,16,26,18,24,16,18,22,22,26,30,22,22,24,24,28,28,26,26,26,26,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28],[-1,13,22,18,26,18,24,18,22,20,24,28,26,24,20,30,24,28,28,26,30,28,30,30,30,30,28,30,30,30,30,30,30,30,30,30,30,30,30,30,30],[-1,17,28,22,16,22,28,26,26,24,28,24,28,22,24,24,30,28,28,26,28,30,24,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30,30]],x=[[-1,1,1,1,1,1,2,2,2,2,4,4,4,4,4,6,6,6,6,7,8,8,9,9,10,12,12,12,13,14,15,16,17,18,19,19,20,21,22,24,25],[-1,1,1,1,2,2,4,4,4,5,5,5,8,9,9,10,10,11,13,14,16,17,17,18,20,21,23,25,26,28,29,31,33,35,37,38,40,43,45,47,49],[-1,1,1,2,2,4,4,6,6,8,8,8,10,12,16,12,17,16,18,21,20,23,23,25,27,29,34,34,35,38,40,43,45,48,51,53,56,59,62,65,68],[-1,1,1,2,4,4,4,5,6,8,8,11,11,16,16,18,16,19,21,25,25,25,34,30,32,35,37,40,42,45,48,51,54,57,60,63,66,70,74,77,81]],S=class{constructor(e,t,n,r){if(this.version=e,this.ecc=t,e<m||e>h)throw RangeError(`Version value out of range`);if(r<-1||r>7)throw RangeError(`Mask value out of range`);this.size=e*4+17;let i=Array.from({length:this.size}).fill(!1);for(let e=0;e<this.size;e++)this.modules.push(i.slice()),this.types.push(i.map(()=>0));this.drawFunctionPatterns();let a=this.addEccAndInterleave(n);if(this.drawCodewords(a),r===-1){let e=1e9;for(let t=0;t<8;t++){this.applyMask(t),this.drawFormatBits(t);let n=this.getPenaltyScore();n<e&&(r=t,e=n),this.applyMask(t)}}this.mask=r,this.applyMask(r),this.drawFormatBits(r)}size;mask;modules=[];types=[];getModule(e,t){return e>=0&&e<this.size&&t>=0&&t<this.size&&this.modules[t][e]}drawFunctionPatterns(){for(let e=0;e<this.size;e++)this.setFunctionModule(6,e,e%2==0,a.Timing),this.setFunctionModule(e,6,e%2==0,a.Timing);this.drawFinderPattern(3,3),this.drawFinderPattern(this.size-4,3),this.drawFinderPattern(3,this.size-4);let e=this.getAlignmentPatternPositions(),t=e.length;for(let n=0;n<t;n++)for(let r=0;r<t;r++)n===0&&r===0||n===0&&r===t-1||n===t-1&&r===0||this.drawAlignmentPattern(e[n],e[r]);this.drawFormatBits(0),this.drawVersion()}drawFormatBits(e){let t=this.ecc[1]<<3|e,n=t;for(let e=0;e<10;e++)n=n<<1^(n>>>9)*1335;let r=(t<<10|n)^21522;for(let e=0;e<=5;e++)this.setFunctionModule(8,e,w(r,e));this.setFunctionModule(8,7,w(r,6)),this.setFunctionModule(8,8,w(r,7)),this.setFunctionModule(7,8,w(r,8));for(let e=9;e<15;e++)this.setFunctionModule(14-e,8,w(r,e));for(let e=0;e<8;e++)this.setFunctionModule(this.size-1-e,8,w(r,e));for(let e=8;e<15;e++)this.setFunctionModule(8,this.size-15+e,w(r,e));this.setFunctionModule(8,this.size-8,!0)}drawVersion(){if(this.version<7)return;let e=this.version;for(let t=0;t<12;t++)e=e<<1^(e>>>11)*7973;let t=this.version<<12|e;for(let e=0;e<18;e++){let n=w(t,e),r=this.size-11+e%3,i=Math.floor(e/3);this.setFunctionModule(r,i,n),this.setFunctionModule(i,r,n)}}drawFinderPattern(e,t){for(let n=-4;n<=4;n++)for(let r=-4;r<=4;r++){let i=Math.max(Math.abs(r),Math.abs(n)),o=e+r,s=t+n;o>=0&&o<this.size&&s>=0&&s<this.size&&this.setFunctionModule(o,s,i!==2&&i!==4,a.Position)}}drawAlignmentPattern(e,t){for(let n=-2;n<=2;n++)for(let r=-2;r<=2;r++)this.setFunctionModule(e+r,t+n,Math.max(Math.abs(r),Math.abs(n))!==1,a.Alignment)}setFunctionModule(e,t,n,r=a.Function){this.modules[t][e]=n,this.types[t][e]=r}addEccAndInterleave(e){let t=this.version,n=this.ecc;if(e.length!==z(t,n))throw RangeError(`Invalid argument`);let r=x[n[0]][t],i=b[n[0]][t],a=Math.floor(R(t)/8),o=r-a%r,s=Math.floor(a/r),c=[],l=B(i);for(let t=0,n=0;t<r;t++){let r=e.slice(n,n+s-i+(t<o?0:1));n+=r.length;let a=V(r,l);t<o&&r.push(0),c.push(r.concat(a))}let u=[];for(let e=0;e<c[0].length;e++)c.forEach((t,n)=>{(e!==s-i||n>=o)&&u.push(t[e])});return u}drawCodewords(e){if(e.length!==Math.floor(R(this.version)/8))throw RangeError(`Invalid argument`);let t=0;for(let n=this.size-1;n>=1;n-=2){n===6&&(n=5);for(let r=0;r<this.size;r++)for(let i=0;i<2;i++){let a=n-i,o=n+1&2?r:this.size-1-r;!this.types[o][a]&&t<e.length*8&&(this.modules[o][a]=w(e[t>>>3],7-(t&7)),t++)}}}applyMask(e){if(e<0||e>7)throw RangeError(`Mask value out of range`);for(let t=0;t<this.size;t++)for(let n=0;n<this.size;n++){let r;switch(e){case 0:r=(n+t)%2==0;break;case 1:r=t%2==0;break;case 2:r=n%3==0;break;case 3:r=(n+t)%3==0;break;case 4:r=(Math.floor(n/3)+Math.floor(t/2))%2==0;break;case 5:r=n*t%2+n*t%3==0;break;case 6:r=(n*t%2+n*t%3)%2==0;break;case 7:r=((n+t)%2+n*t%3)%2==0;break;default:throw Error(`Unreachable`)}!this.types[t][n]&&r&&(this.modules[t][n]=!this.modules[t][n])}}getPenaltyScore(){let e=0;for(let t=0;t<this.size;t++){let n=!1,r=0,i=[0,0,0,0,0,0,0];for(let a=0;a<this.size;a++)this.modules[t][a]===n?(r++,r===5?e+=g:r>5&&e++):(this.finderPenaltyAddHistory(r,i),n||(e+=this.finderPenaltyCountPatterns(i)*v),n=this.modules[t][a],r=1);e+=this.finderPenaltyTerminateAndCount(n,r,i)*v}for(let t=0;t<this.size;t++){let n=!1,r=0,i=[0,0,0,0,0,0,0];for(let a=0;a<this.size;a++)this.modules[a][t]===n?(r++,r===5?e+=g:r>5&&e++):(this.finderPenaltyAddHistory(r,i),n||(e+=this.finderPenaltyCountPatterns(i)*v),n=this.modules[a][t],r=1);e+=this.finderPenaltyTerminateAndCount(n,r,i)*v}for(let t=0;t<this.size-1;t++)for(let n=0;n<this.size-1;n++){let r=this.modules[t][n];r===this.modules[t][n+1]&&r===this.modules[t+1][n]&&r===this.modules[t+1][n+1]&&(e+=_)}let t=0;for(let e of this.modules)t=e.reduce((e,t)=>e+ +!!t,t);let n=this.size*this.size,r=Math.ceil(Math.abs(t*20-n*10)/n)-1;return e+=r*y,e}getAlignmentPatternPositions(){if(this.version===1)return[];{let e=Math.floor(this.version/7)+2,t=this.version===32?26:Math.ceil((this.version*4+4)/(e*2-2))*2,n=[6];for(let r=this.size-7;n.length<e;r-=t)n.splice(1,0,r);return n}}finderPenaltyCountPatterns(e){let t=e[1],n=t>0&&e[2]===t&&e[3]===t*3&&e[4]===t&&e[5]===t;return(n&&e[0]>=t*4&&e[6]>=t?1:0)+(n&&e[6]>=t*4&&e[0]>=t?1:0)}finderPenaltyTerminateAndCount(e,t,n){return e&&(this.finderPenaltyAddHistory(t,n),t=0),t+=this.size,this.finderPenaltyAddHistory(t,n),this.finderPenaltyCountPatterns(n)}finderPenaltyAddHistory(e,t){t[0]===0&&(e+=this.size),t.pop(),t.unshift(e)}};function C(e,t,n){if(t<0||t>31||e>>>t)throw RangeError(`Value out of range`);for(let r=t-1;r>=0;r--)n.push(e>>>r&1)}function w(e,t){return!!(e>>>t&1)}var T=class{constructor(e,t,n){if(this.mode=e,this.numChars=t,this.bitData=n,t<0)throw RangeError(`Invalid argument`);this.bitData=n.slice()}getData(){return this.bitData.slice()}},E=[1,10,12,14],D=[2,9,11,13],O=[4,8,16,16];function k(e,t){return e[Math.floor((t+7)/17)+1]}function A(e){let t=[];for(let n of e)C(n,8,t);return new T(O,e.length,t)}function j(e){if(!P(e))throw RangeError(`String contains non-numeric characters`);let t=[];for(let n=0;n<e.length;){let r=Math.min(e.length-n,3);C(Number.parseInt(e.substring(n,n+r),10),r*3+1,t),n+=r}return new T(E,e.length,t)}function M(e){if(!F(e))throw RangeError(`String contains unencodable characters in alphanumeric mode`);let t=[],n;for(n=0;n+2<=e.length;n+=2){let r=p.indexOf(e.charAt(n))*45;r+=p.indexOf(e.charAt(n+1)),C(r,11,t)}return n<e.length&&C(p.indexOf(e.charAt(n)),6,t),new T(D,e.length,t)}function N(e){return e===``?[]:P(e)?[j(e)]:F(e)?[M(e)]:[A(L(e))]}function P(e){return d.test(e)}function F(e){return f.test(e)}function I(e,t){let n=0;for(let r of e){let e=k(r.mode,t);if(r.numChars>=1<<e)return 1/0;n+=4+e+r.bitData.length}return n}function L(e){e=encodeURI(e);let t=[];for(let n=0;n<e.length;n++)e.charAt(n)===`%`?(t.push(Number.parseInt(e.substring(n+1,n+3),16)),n+=2):t.push(e.charCodeAt(n));return t}function R(e){if(e<m||e>h)throw RangeError(`Version number out of range`);let t=(16*e+128)*e+64;if(e>=2){let n=Math.floor(e/7)+2;t-=(25*n-10)*n-55,e>=7&&(t-=36)}return t}function z(e,t){return Math.floor(R(e)/8)-b[t[0]][e]*x[t[0]][e]}function B(e){if(e<1||e>255)throw RangeError(`Degree out of range`);let t=[];for(let n=0;n<e-1;n++)t.push(0);t.push(1);let n=1;for(let r=0;r<e;r++){for(let e=0;e<t.length;e++)t[e]=H(t[e],n),e+1<t.length&&(t[e]^=t[e+1]);n=H(n,2)}return t}function V(e,t){let n=t.map(e=>0);for(let r of e){let e=r^n.shift();n.push(0),t.forEach((t,r)=>n[r]^=H(t,e))}return n}function H(e,t){if(e>>>8||t>>>8)throw RangeError(`Byte out of range`);let n=0;for(let r=7;r>=0;r--)n=n<<1^(n>>>7)*285,n^=(t>>>r&1)*e;return n}function U(e,t,n=1,r=40,i=-1,a=!0){if(!(m<=n&&n<=r&&r<=h)||i<-1||i>7)throw RangeError(`Invalid value`);let o,u;for(o=n;;o++){let n=z(o,t)*8,i=I(e,o);if(i<=n){u=i;break}if(o>=r)throw RangeError(`Data too long`)}for(let e of[s,c,l])a&&u<=z(o,e)*8&&(t=e);let d=[];for(let t of e){C(t.mode[0],4,d),C(t.numChars,k(t.mode,o),d);for(let e of t.getData())d.push(e)}let f=z(o,t)*8;C(0,Math.min(4,f-d.length),d),C(0,(8-d.length%8)%8,d);for(let e=236;d.length<f;e^=253)C(e,8,d);let p=Array.from({length:Math.ceil(d.length/8)},()=>0);return d.forEach((e,t)=>p[t>>>3]|=e<<7-(t&7)),new S(o,t,p,i)}function W(e,t){let{ecc:n=`L`,boostEcc:r=!1,minVersion:i=1,maxVersion:a=40,maskPattern:o=-1,border:s=1}=t||{},c=typeof e==`string`?N(e):Array.isArray(e)?[A(e)]:void 0;if(!c)throw Error(`uqr only supports encoding string and binary data, but got: ${typeof e}`);let l=U(c,u[n],i,a,o,r),d=G({version:l.version,maskPattern:l.mask,size:l.size,data:l.modules,types:l.types},s);return t?.invert&&(d.data=d.data.map(e=>e.map(e=>!e))),t?.onEncoded?.(d),d}function G(e,t=1){if(!t)return e;let{size:n}=e,r=n+t*2;e.size=r,e.data.forEach(e=>{for(let n=0;n<t;n++)e.unshift(!1),e.push(!1)});for(let n=0;n<t;n++)e.data.unshift(Array.from({length:r},e=>!1)),e.data.push(Array.from({length:r},e=>!1));let i=a.Border;e.types.forEach(e=>{for(let n=0;n<t;n++)e.unshift(i),e.push(i)});for(let n=0;n<t;n++)e.types.unshift(Array.from({length:r},e=>i)),e.types.push(Array.from({length:r},e=>i));return e}var K=n();function q({value:e,className:t=``}){let n=(0,i.useRef)(null);return(0,i.useEffect)(()=>{let t=n.current;if(!t||!e)return;let r=W(String(e),{ecc:`M`,border:1}),i=r.size,a=t.getContext(`2d`);if(!a)return;let o=i*4;t.width=o,t.height=o,a.fillStyle=`#ffffff`,a.fillRect(0,0,o,o),a.fillStyle=`#111111`;for(let e=0;e<i;e+=1)for(let t=0;t<i;t+=1)r.data[e][t]&&a.fillRect(t*4,e*4,4,4)},[e]),e?(0,K.jsx)(`canvas`,{ref:n,className:t,"aria-hidden":!0}):null}var J=56,Y=7,X=8,Z=[{max:0,key:`free`,label:`Gratis`,from:`#64748b`,to:`#1e293b`,accent:`#94a3b8`,ink:`#0f172a`,soft:`#334155`,chip:`#e2e8f0`},{max:2999,key:`starter`,label:`Starter`,from:`#34d399`,to:`#047857`,accent:`#a7f3d0`,ink:`#064e3b`,soft:`#065f46`,chip:`#d1fae5`},{max:4999,key:`basic`,label:`Basic`,from:`#22d3ee`,to:`#0e7490`,accent:`#a5f3fc`,ink:`#164e63`,soft:`#155e75`,chip:`#cffafe`},{max:9999,key:`standard`,label:`Standard`,from:`#60a5fa`,to:`#1d4ed8`,accent:`#bfdbfe`,ink:`#1e3a8a`,soft:`#1e40af`,chip:`#dbeafe`},{max:14999,key:`plus`,label:`Plus`,from:`#a78bfa`,to:`#6d28d9`,accent:`#ddd6fe`,ink:`#4c1d95`,soft:`#5b21b6`,chip:`#ede9fe`},{max:24999,key:`gold`,label:`Gold`,from:`#fbbf24`,to:`#b45309`,accent:`#fde68a`,ink:`#78350f`,soft:`#92400e`,chip:`#fef3c7`},{max:1/0,key:`premium`,label:`Premium`,from:`#fb7185`,to:`#9f1239`,accent:`#fecdd3`,ink:`#881337`,soft:`#9f1239`,chip:`#ffe4e6`}];function Q(e,t){let n=[];for(let r=0;r<e.length;r+=t)n.push(e.slice(r,r+t));return n.length>0?n:[[]]}function $(e){let t=Math.max(0,Number(e)||0);return Z.find(e=>t<=e.max)||Z[Z.length-1]}function ee({theme:e,uid:t}){let n=`${t}-grad`,r=`${t}-glow`,i=`${t}-pat`;return e.key===`gold`||e.key===`premium`?(0,K.jsxs)(`svg`,{className:`voucher-card__art`,viewBox:`0 0 160 100`,preserveAspectRatio:`none`,"aria-hidden":!0,children:[(0,K.jsxs)(`defs`,{children:[(0,K.jsxs)(`linearGradient`,{id:n,x1:`0%`,y1:`0%`,x2:`100%`,y2:`100%`,children:[(0,K.jsx)(`stop`,{offset:`0%`,stopColor:e.from}),(0,K.jsx)(`stop`,{offset:`55%`,stopColor:e.to}),(0,K.jsx)(`stop`,{offset:`100%`,stopColor:e.from,stopOpacity:`0.85`})]}),(0,K.jsxs)(`radialGradient`,{id:r,cx:`85%`,cy:`15%`,r:`55%`,children:[(0,K.jsx)(`stop`,{offset:`0%`,stopColor:`#fff`,stopOpacity:`0.55`}),(0,K.jsx)(`stop`,{offset:`100%`,stopColor:`#fff`,stopOpacity:`0`})]})]}),(0,K.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${n})`}),(0,K.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${r})`}),(0,K.jsx)(`path`,{d:`M0 72c28-16 52-8 78 2s48 18 72 4 10-18 10-18v40H0V72Z`,fill:e.accent,opacity:`0.22`}),(0,K.jsx)(`path`,{d:`M118 8l14 14-14 14-14-14 14-14Z`,fill:e.accent,opacity:`0.35`}),(0,K.jsx)(`circle`,{cx:`138`,cy:`78`,r:`18`,fill:e.accent,opacity:`0.18`}),(0,K.jsx)(`path`,{d:`M12 18h28M12 24h18`,stroke:`#fff`,strokeWidth:`1.4`,strokeLinecap:`round`,opacity:`0.35`})]}):e.key===`plus`||e.key===`standard`?(0,K.jsxs)(`svg`,{className:`voucher-card__art`,viewBox:`0 0 160 100`,preserveAspectRatio:`none`,"aria-hidden":!0,children:[(0,K.jsxs)(`defs`,{children:[(0,K.jsxs)(`linearGradient`,{id:n,x1:`0%`,y1:`0%`,x2:`100%`,y2:`100%`,children:[(0,K.jsx)(`stop`,{offset:`0%`,stopColor:e.from}),(0,K.jsx)(`stop`,{offset:`100%`,stopColor:e.to})]}),(0,K.jsx)(`pattern`,{id:i,width:`12`,height:`12`,patternUnits:`userSpaceOnUse`,children:(0,K.jsx)(`circle`,{cx:`1.5`,cy:`1.5`,r:`1.1`,fill:e.accent,opacity:`0.35`})})]}),(0,K.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${n})`}),(0,K.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${i})`}),(0,K.jsx)(`circle`,{cx:`142`,cy:`16`,r:`28`,fill:e.accent,opacity:`0.2`}),(0,K.jsx)(`circle`,{cx:`142`,cy:`16`,r:`16`,fill:`none`,stroke:`#fff`,strokeWidth:`1.2`,opacity:`0.35`}),(0,K.jsx)(`path`,{d:`M0 78c36-20 70-12 104 4s40 20 56 8v20H0V78Z`,fill:`#fff`,opacity:`0.12`}),(0,K.jsx)(`path`,{d:`M18 22c10 8 26 8 36 0`,fill:`none`,stroke:`#fff`,strokeWidth:`1.6`,strokeLinecap:`round`,opacity:`0.4`})]}):(0,K.jsxs)(`svg`,{className:`voucher-card__art`,viewBox:`0 0 160 100`,preserveAspectRatio:`none`,"aria-hidden":!0,children:[(0,K.jsxs)(`defs`,{children:[(0,K.jsxs)(`linearGradient`,{id:n,x1:`0%`,y1:`0%`,x2:`100%`,y2:`100%`,children:[(0,K.jsx)(`stop`,{offset:`0%`,stopColor:e.from}),(0,K.jsx)(`stop`,{offset:`100%`,stopColor:e.to})]}),(0,K.jsxs)(`radialGradient`,{id:r,cx:`90%`,cy:`10%`,r:`50%`,children:[(0,K.jsx)(`stop`,{offset:`0%`,stopColor:`#fff`,stopOpacity:`0.45`}),(0,K.jsx)(`stop`,{offset:`100%`,stopColor:`#fff`,stopOpacity:`0`})]})]}),(0,K.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${n})`}),(0,K.jsx)(`rect`,{width:`160`,height:`100`,fill:`url(#${r})`}),(0,K.jsx)(`circle`,{cx:`148`,cy:`10`,r:`34`,fill:e.accent,opacity:`0.18`}),(0,K.jsx)(`circle`,{cx:`148`,cy:`10`,r:`20`,fill:`none`,stroke:`#fff`,strokeWidth:`1.3`,opacity:`0.3`}),(0,K.jsx)(`path`,{d:`M0 70c34-22 72-26 108-10s42 34 52 28v22H0V70Z`,fill:`#fff`,opacity:`0.14`}),(0,K.jsx)(`path`,{d:`M14 26c12 10 32 10 44 0M10 36c16 14 44 14 60 0`,fill:`none`,stroke:`#fff`,strokeWidth:`1.5`,strokeLinecap:`round`,opacity:`0.35`})]})}function te({item:e,number:t,showQr:n}){let r=(0,i.useId)().replace(/:/g,``),a=$(e.sell_price),o=e.username||e.password||``,s=String(t).padStart(2,`0`),c=e.same_code??e.username===e.password,l=e.login_url||``;return(0,K.jsxs)(`article`,{className:`voucher-card voucher-card--${a.key}`,style:{"--vc-from":a.from,"--vc-to":a.to,"--vc-accent":a.accent,"--vc-ink":a.ink,"--vc-soft":a.soft,"--vc-chip":a.chip},children:[(0,K.jsxs)(`div`,{className:`voucher-card__hero`,children:[(0,K.jsx)(ee,{theme:a,uid:r}),(0,K.jsxs)(`div`,{className:`voucher-card__hero-content`,children:[(0,K.jsxs)(`div`,{className:`voucher-card__hero-top`,children:[(0,K.jsxs)(`div`,{className:`voucher-card__brand-block`,children:[e.agent_name&&(0,K.jsx)(`span`,{className:`voucher-card__agent`,children:e.agent_name}),(0,K.jsx)(`span`,{className:`voucher-card__brand`,children:`Hotspot`})]}),(0,K.jsx)(`span`,{className:`voucher-card__tier`,children:a.label})]}),(0,K.jsx)(`p`,{className:`voucher-card__price`,children:e.sell_price_label||`Rp 0`})]})]}),(0,K.jsxs)(`div`,{className:`voucher-card__body`,children:[(0,K.jsxs)(`div`,{className:`voucher-card__creds-row${n&&l?` has-qr`:``}`,children:[(0,K.jsxs)(`div`,{className:`voucher-card__creds`,children:[(0,K.jsx)(`span`,{className:`voucher-card__label`,children:c?`Voucher`:`User / Pass`}),(0,K.jsx)(`span`,{className:`voucher-card__value`,children:o}),!c&&e.password&&(0,K.jsx)(`span`,{className:`voucher-card__value voucher-card__value--pass`,children:e.password})]}),n&&l&&(0,K.jsx)(q,{value:l,className:`voucher-qr voucher-qr--a4`})]}),(0,K.jsxs)(`div`,{className:`voucher-card__footer`,children:[(0,K.jsxs)(`div`,{className:`voucher-card__footer-main`,children:[e.login_url||e.dns_name?(0,K.jsxs)(`span`,{className:`voucher-card__hint`,children:[(0,K.jsx)(`span`,{children:`Scan QR atau buka`}),(0,K.jsx)(`span`,{children:e.dns_name||e.login_url})]}):(0,K.jsxs)(`span`,{className:`voucher-card__hint`,children:[(0,K.jsx)(`span`,{children:`Portal tidak muncul?`}),(0,K.jsx)(`span`,{children:`Ketik DNS hotspot di browser.`})]}),e.profile&&(0,K.jsx)(`span`,{children:e.profile})]}),(0,K.jsxs)(`span`,{className:`voucher-card__seq`,children:[`#`,s]})]})]})]})}function ne({item:e,number:t,showQr:n}){let r=e.same_code??e.username===e.password,i=e.login_url||``;return(0,K.jsxs)(`article`,{className:`voucher-small`,children:[(0,K.jsxs)(`header`,{className:`voucher-small__head`,children:[(0,K.jsx)(`strong`,{children:`Hotspot`}),(0,K.jsxs)(`span`,{children:[`#`,String(t).padStart(2,`0`)]})]}),(0,K.jsxs)(`div`,{className:`voucher-small__body${n&&i?` has-qr`:``}`,children:[(0,K.jsxs)(`div`,{children:[(0,K.jsx)(`p`,{className:`voucher-small__label`,children:r?`Kode voucher`:`Username`}),(0,K.jsx)(`p`,{className:`voucher-small__code`,children:e.username}),!r&&(0,K.jsxs)(K.Fragment,{children:[(0,K.jsx)(`p`,{className:`voucher-small__label`,children:`Password`}),(0,K.jsx)(`p`,{className:`voucher-small__code`,children:e.password})]}),(0,K.jsx)(`p`,{className:`voucher-small__meta`,children:[e.limit_uptime,e.profile,e.sell_price_label].filter(Boolean).join(` · `)})]}),n&&i&&(0,K.jsx)(q,{value:i,className:`voucher-qr voucher-qr--small`})]})]})}function re({item:e,number:t,showQr:n}){let r=e.same_code??e.username===e.password,i=e.login_url||``,a=new Date,o=`${a.getFullYear()}-${String(a.getMonth()+1).padStart(2,`0`)}-${String(a.getDate()).padStart(2,`0`)}`;return(0,K.jsxs)(`article`,{className:`voucher-thermal`,children:[(0,K.jsx)(`h2`,{children:`Hotspot Voucher`}),(0,K.jsxs)(`p`,{className:`voucher-thermal__sub`,children:[`#`,String(t).padStart(2,`0`),` · `,o]}),n&&i&&(0,K.jsx)(q,{value:i,className:`voucher-qr voucher-qr--thermal`}),(0,K.jsx)(`p`,{className:`voucher-thermal__label`,children:r?`Kode voucher`:`Username`}),(0,K.jsx)(`p`,{className:`voucher-thermal__code`,children:e.username}),!r&&(0,K.jsxs)(K.Fragment,{children:[(0,K.jsx)(`p`,{className:`voucher-thermal__label`,children:`Password`}),(0,K.jsx)(`p`,{className:`voucher-thermal__code`,children:e.password})]}),(0,K.jsx)(`p`,{className:`voucher-thermal__meta`,children:[e.profile,e.limit_uptime,e.sell_price_label].filter(Boolean).join(` · `)}),(e.dns_name||e.login_url)&&(0,K.jsxs)(`p`,{className:`voucher-thermal__login`,children:[`Login: `,e.dns_name||e.login_url]})]})}function ie(e,t){let n=new URL(window.location.href);n.searchParams.set(`layout`,e),n.searchParams.set(`qr`,t?`1`:`0`),window.history.replaceState({},``,n)}function ae({vouchers:e=[],layout:n=`a4`,show_qr:r=!0}){let a=(0,i.useMemo)(()=>Q(e,J),[e]),o=(0,i.useMemo)(()=>Q(e,36),[e]),[s,c]=(0,i.useState)([`a4`,`small`,`thermal`].includes(n)?n:`a4`),[l,u]=(0,i.useState)(!!r);return(0,i.useEffect)(()=>{ie(s,l)},[s,l]),(0,i.useEffect)(()=>{let e=window.setTimeout(()=>window.print(),500);return()=>window.clearTimeout(e)},[]),(0,K.jsxs)(`div`,{className:`voucher-print voucher-print--${s}`,children:[(0,K.jsx)(t,{title:`Cetak Kartu Voucher`}),(0,K.jsx)(`style`,{children:`
                @page {
                    size: ${s===`thermal`?`80mm auto`:`A4 portrait`};
                    margin: ${s===`thermal`?`4mm`:`0`};
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
                    grid-template-columns: repeat(${Y}, 1fr);
                    grid-template-rows: repeat(${X}, 1fr);
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
            `}),(0,K.jsxs)(`div`,{className:`voucher-print__toolbar`,children:[(0,K.jsxs)(`div`,{children:[(0,K.jsx)(`h1`,{children:`Cetak Kartu Voucher`}),(0,K.jsxs)(`p`,{children:[e.length,` kartu`,s===`a4`&&` · ${a.length} lembar A4 · ${Y}×${X}`,s===`small`&&` · kartu kecil (seperti Print Small Mikhmon)`,s===`thermal`&&` · struk 80mm (thermal)`,l?` · QR login`:` · tanpa QR`]}),(0,K.jsxs)(`div`,{className:`voucher-print__legend`,children:[[`a4`,`small`,`thermal`].map(e=>(0,K.jsx)(`button`,{type:`button`,onClick:()=>c(e),className:`btn-action btn-action-xs ${s===e?`btn-primary`:`btn-secondary`}`,children:e===`a4`?`A4`:e===`small`?`Kecil`:`Thermal`},e)),(0,K.jsxs)(`label`,{className:`voucher-print__qr-toggle`,children:[(0,K.jsx)(`input`,{type:`checkbox`,checked:l,onChange:e=>u(e.target.checked)}),`QR login`]}),s===`a4`&&Z.map(e=>(0,K.jsxs)(`span`,{children:[(0,K.jsx)(`i`,{style:{background:`linear-gradient(135deg, ${e.from}, ${e.to})`}}),e.label,e.max===1/0?` ≥25rb`:e.max===0?` Rp0`:` ≤${Math.round(e.max/1e3)}rb`]},e.key))]})]}),(0,K.jsx)(`button`,{type:`button`,onClick:()=>window.print(),className:`btn-action btn-action-sm btn-primary`,children:`Print ulang`})]}),(0,K.jsxs)(`div`,{className:`voucher-print__sheets`,children:[s===`a4`&&a.map((e,t)=>(0,K.jsx)(`section`,{className:`voucher-sheet`,"aria-label":`Lembar ${t+1}`,children:e.map((e,n)=>(0,K.jsx)(te,{item:e,number:t*J+n+1,showQr:l},e.id||e.username))},`page-${t}`)),s===`small`&&o.map((e,t)=>(0,K.jsx)(`section`,{className:`voucher-small-sheet`,"aria-label":`Lembar kecil ${t+1}`,children:e.map((e,n)=>(0,K.jsx)(ne,{item:e,number:t*36+n+1,showQr:l},e.id||e.username))},`small-${t}`)),s===`thermal`&&e.map((e,t)=>(0,K.jsx)(re,{item:e,number:t+1,showQr:l},e.id||e.username))]})]})}export{ae as default};