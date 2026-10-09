import { useId } from 'react';

const CHART_WIDTH = 640;
const CHART_HEIGHT = 168;
const MARGIN = { top: 16, right: 16, bottom: 30 };

const TONES = {
    signal: {
        front: ['#8eb6ff', '#1a5fe8', '#0a2d82'],
        side: ['#1c3f86', '#071433'],
        top: ['#f4f8ff', '#7aa7ff'],
        gloss: '#ffffff',
        shadow: '#0a1f4d',
    },
    emerald: {
        front: ['#8ef0c8', '#12b981', '#065f46'],
        side: ['#047857', '#022c22'],
        top: ['#f3fdf8', '#5ee9b5'],
        gloss: '#ffffff',
        shadow: '#064e3b',
    },
};

function niceMax(value) {
    if (value <= 0) return 1;
    const exp = Math.floor(Math.log10(value));
    const fraction = value / 10 ** exp;
    let nice;
    if (fraction <= 1) nice = 1;
    else if (fraction <= 2) nice = 2;
    else if (fraction <= 5) nice = 5;
    else nice = 10;
    return nice * 10 ** exp;
}

function indexShowsLabel(index, every, total) {
    if (every <= 1) return true;
    const last = total - 1;
    if (index === last) return true;
    if (index % every !== 0) return false;
    return last - index >= every;
}

function pointsAttr(pairs) {
    return pairs.map(([x, y]) => `${x.toFixed(2)},${y.toFixed(2)}`).join(' ');
}

function Column3D({ bar, depth, lift, tone, ids }) {
    const height = Math.max(bar.height, bar.total > 0 ? 2.4 : 0);
    if (height <= 0) return null;

    const { x, y, barW: width } = bar;
    const top = [
        [x, y],
        [x + width, y],
        [x + width + depth, y - lift],
        [x + depth, y - lift],
    ];
    const side = [
        [x + width, y],
        [x + width + depth, y - lift],
        [x + width + depth, y + height - lift],
        [x + width, y + height],
    ];

    return (
        <g className="premium-bar">
            <title>{`${bar.label}: ${bar.total_label}`}</title>
            <ellipse
                cx={x + width / 2 + depth * 0.35}
                cy={y + height + 1.6}
                rx={width * 0.72}
                ry={Math.max(1.1, depth * 0.28)}
                fill={tone.shadow}
                opacity="0.16"
            />
            <polygon points={pointsAttr(side)} fill={`url(#${ids.side})`} />
            <rect x={x} y={y} width={width} height={height} fill={`url(#${ids.front})`} />
            <polygon points={pointsAttr(top)} fill={`url(#${ids.top})`} />
            <rect
                x={x + 0.7}
                y={y + 0.8}
                width={Math.max(0.8, width * 0.22)}
                height={Math.max(0, height - 1.6)}
                fill={`url(#${ids.gloss})`}
            />
            <line
                x1={x + 0.4}
                x2={x + width - 0.4}
                y1={y + 0.55}
                y2={y + 0.55}
                stroke="#ffffff"
                strokeOpacity="0.7"
                strokeWidth="0.7"
            />
        </g>
    );
}

export default function PremiumBarChart({
    points = [],
    xLabel,
    yLabel,
    formatY = (value) => String(Math.round(value)),
    tone = 'signal',
    marginLeft = 52,
    gapCap = 12,
    minBar = 6,
    labelEvery = 1,
    integerTicks = false,
}) {
    const uid = useId().replace(/:/g, '');
    const palette = TONES[tone] || TONES.signal;
    const ids = {
        front: `${uid}-front`,
        side: `${uid}-side`,
        top: `${uid}-top`,
        gloss: `${uid}-gloss`,
        stage: `${uid}-stage`,
    };

    const totals = points.map((point) => point.total || 0);
    const yMax = niceMax(Math.max(...totals, 1));
    const plotW = CHART_WIDTH - marginLeft - MARGIN.right;
    const depthRoom = 8;
    const plotH = CHART_HEIGHT - MARGIN.top - MARGIN.bottom - depthRoom;
    const count = Math.max(points.length, 1);
    const gap = Math.min(gapCap, plotW / (count * 3.2));
    const barW = Math.max(minBar, (plotW - gap * (count + 1)) / count);
    const depth = Math.max(2, Math.min(6.5, barW * 0.38, gap * 0.7));
    const lift = depth * 0.62;
    const yTicks = integerTicks
        ? [...new Set([0, Math.round(yMax / 2), yMax])]
        : [0, yMax / 2, yMax];

    const bars = points.map((point, index) => {
        const height = ((point.total || 0) / yMax) * plotH;
        const x = marginLeft + gap + index * (barW + gap);
        const y = MARGIN.top + depthRoom + plotH - height;
        return { ...point, x, y, height, barW };
    });
    const baseline = MARGIN.top + depthRoom + plotH;

    return (
        <div className="w-full">
            <div className="mb-1 flex items-center justify-between gap-2 text-[10px] font-semibold tracking-wide text-ink/55 uppercase">
                <span>{yLabel}</span>
                <span>{xLabel}</span>
            </div>
            <svg
                viewBox={`0 0 ${CHART_WIDTH} ${CHART_HEIGHT}`}
                className="w-full"
                style={{ aspectRatio: `${CHART_WIDTH} / ${CHART_HEIGHT}`, maxHeight: 176 }}
                role="img"
                aria-label={`${yLabel} terhadap ${xLabel}`}
            >
                <title>{`${yLabel} · ${xLabel}`}</title>
                <defs>
                    <linearGradient id={ids.stage} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#ffffff" stopOpacity="0.2" />
                        <stop offset="100%" stopColor="#d7e2ee" stopOpacity="0.45" />
                    </linearGradient>
                    <linearGradient id={ids.front} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={palette.front[0]} />
                        <stop offset="46%" stopColor={palette.front[1]} />
                        <stop offset="100%" stopColor={palette.front[2]} />
                    </linearGradient>
                    <linearGradient id={ids.side} x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0%" stopColor={palette.side[0]} />
                        <stop offset="100%" stopColor={palette.side[1]} />
                    </linearGradient>
                    <linearGradient id={ids.top} x1="0" y1="1" x2="1" y2="0">
                        <stop offset="0%" stopColor={palette.top[1]} />
                        <stop offset="100%" stopColor={palette.top[0]} />
                    </linearGradient>
                    <linearGradient id={ids.gloss} x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stopColor={palette.gloss} stopOpacity="0.42" />
                        <stop offset="100%" stopColor={palette.gloss} stopOpacity="0" />
                    </linearGradient>
                </defs>
                <style>
                    {`.premium-bar{cursor:pointer}
                      .premium-bar:hover{filter:brightness(1.08)}`}
                </style>

                <rect
                    x={marginLeft}
                    y={MARGIN.top}
                    width={plotW}
                    height={depthRoom + plotH}
                    fill={`url(#${ids.stage})`}
                />

                {yTicks.map((value) => {
                    const y = baseline - (value / yMax) * plotH;
                    return (
                        <g key={`y-${value}`}>
                            <line
                                x1={marginLeft}
                                x2={marginLeft + plotW}
                                y1={y}
                                y2={y}
                                stroke="#101820"
                                strokeOpacity="0.08"
                                strokeDasharray="2.5 3.5"
                                strokeWidth="1"
                            />
                            <text
                                x={marginLeft - 8}
                                y={y + 3}
                                textAnchor="end"
                                className="fill-ink/50 text-[10px]"
                            >
                                {formatY(value)}
                            </text>
                        </g>
                    );
                })}

                <line
                    x1={marginLeft}
                    x2={marginLeft}
                    y1={MARGIN.top}
                    y2={baseline}
                    stroke="#101820"
                    strokeOpacity="0.28"
                    strokeWidth="1.25"
                />
                <line
                    x1={marginLeft}
                    x2={marginLeft + plotW}
                    y1={baseline}
                    y2={baseline}
                    stroke="#101820"
                    strokeOpacity="0.28"
                    strokeWidth="1.25"
                />

                {bars.map((bar, index) => (
                    <g key={bar.key}>
                        <Column3D bar={bar} depth={depth} lift={lift} tone={palette} ids={ids} />
                        {indexShowsLabel(index, labelEvery, bars.length) && (
                            <text
                                x={bar.x + bar.barW / 2}
                                y={CHART_HEIGHT - 8}
                                textAnchor="middle"
                                className="fill-ink/55 text-[9px]"
                            >
                                {bar.label}
                            </text>
                        )}
                    </g>
                ))}
            </svg>
        </div>
    );
}
