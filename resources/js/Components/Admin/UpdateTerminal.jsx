import { useEffect, useMemo, useRef, useState } from 'react';

/** Warna ANSI/Tango yang umum di terminal Ubuntu. */
const C = {
    bg: '#300A24',
    title: '#2C001E',
    border: '#1A000F',
    fg: '#EEEEEC',
    dim: '#888A85',
    green: '#8AE234',
    blue: '#729FCF',
    red: '#EF2929',
    yellow: '#FCE94F',
    white: '#D3D7CF',
};

const PULL_SCRIPT = [
    { tone: 'prompt', text: 'git fetch origin --prune --tags' },
    { tone: 'dim', text: 'remote: Enumerating objects...' },
    { tone: 'dim', text: 'remote: Counting objects: 100% (12/12), done.' },
    { tone: 'prompt', text: 'git pull --ff-only origin main' },
    { tone: 'fg', text: 'From github.com:origin/main' },
    { tone: 'fg', text: ' * branch            main       -> FETCH_HEAD' },
    { tone: 'fg', text: 'Updating local working tree...' },
    { tone: 'green', text: 'Fast-forward' },
];

const CHECK_SCRIPT = [
    { tone: 'prompt', text: 'git fetch origin --prune --tags' },
    { tone: 'dim', text: 'remote: Enumerating objects...' },
    { tone: 'dim', text: 'remote: Counting objects: 100% (8/8), done.' },
    { tone: 'fg', text: 'From github.com:origin/main' },
    { tone: 'fg', text: 'Checking local HEAD against origin...' },
];

function toneColor(tone) {
    switch (tone) {
        case 'green':
        case 'ok':
            return C.green;
        case 'blue':
            return C.blue;
        case 'red':
        case 'error':
            return C.red;
        case 'yellow':
            return C.yellow;
        case 'dim':
            return C.dim;
        case 'fg':
        default:
            return C.fg;
    }
}

function compactMessage(message, max = 88) {
    const text = String(message || '')
        .replace(/\s+/g, ' ')
        .trim();
    if (text.length <= max) return text;
    return `${text.slice(0, max - 1)}…`;
}

function PromptLine({ command }) {
    return (
        <p className="update-term-line truncate" style={{ color: C.fg }}>
            <span style={{ color: C.green }}>3rsolusi@vps</span>
            <span style={{ color: C.fg }}>:</span>
            <span style={{ color: C.blue }}>~</span>
            <span style={{ color: C.fg }}>$ </span>
            <span style={{ color: C.white }}>{command}</span>
        </p>
    );
}

export default function UpdateTerminal({
    open,
    mode = 'pull',
    branch = 'main',
    behind = 0,
    result = null,
    onClose,
}) {
    const [visibleCount, setVisibleCount] = useState(0);
    const [cursorOn, setCursorOn] = useState(true);
    const bodyRef = useRef(null);

    const lines = useMemo(() => {
        if (mode === 'check') {
            return CHECK_SCRIPT.map((line) => ({ ...line }));
        }

        const next = PULL_SCRIPT.map((line) => ({ ...line }));
        if (behind > 0) {
            next.splice(6, 0, {
                tone: 'dim',
                text: `Receiving objects for ${behind} commit${behind > 1 ? 's' : ''}...`,
            });
        }
        const pullIdx = next.findIndex(
            (line) => line.tone === 'prompt' && line.text.includes('git pull'),
        );
        if (pullIdx >= 0) {
            next[pullIdx] = {
                tone: 'prompt',
                text: `git pull --ff-only origin ${branch || 'main'}`,
            };
        }
        return next;
    }, [mode, branch, behind]);

    useEffect(() => {
        if (!open) {
            setVisibleCount(0);
            return undefined;
        }

        if (result) {
            setVisibleCount(lines.length);
            return undefined;
        }

        setVisibleCount(0);
        let i = 0;
        const timer = window.setInterval(() => {
            i += 1;
            setVisibleCount(i);
            if (i >= lines.length) {
                window.clearInterval(timer);
            }
        }, 380);

        return () => window.clearInterval(timer);
    }, [open, lines.length, mode, result]);

    useEffect(() => {
        if (!open) return undefined;
        const blink = window.setInterval(() => setCursorOn((v) => !v), 530);
        return () => window.clearInterval(blink);
    }, [open]);

    useEffect(() => {
        if (!bodyRef.current) return;
        bodyRef.current.scrollTop = bodyRef.current.scrollHeight;
    }, [visibleCount, result, open]);

    if (!open) return null;

    const shown = lines.slice(0, visibleCount);
    const scriptDone = visibleCount >= lines.length;
    const waiting = scriptDone && !result;
    const finished = Boolean(result);
    const title =
        mode === 'check'
            ? '3rsolusi@vps: ~'
            : '3rsolusi@vps: ~';

    return (
        <div
            className="fixed inset-0 z-[80] flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Terminal update GitHub"
        >
            <div
                className="flex w-full max-w-xl flex-col overflow-hidden shadow-2xl"
                style={{
                    backgroundColor: C.bg,
                    border: `1px solid ${C.border}`,
                    borderRadius: '8px 8px 0 0',
                }}
            >
                {/* Title bar ala Ubuntu / GNOME */}
                <div
                    className="flex shrink-0 items-center gap-3 px-3 py-2"
                    style={{ backgroundColor: C.title }}
                >
                    <div className="flex items-center gap-1.5">
                        <button
                            type="button"
                            onClick={finished ? onClose : undefined}
                            className="h-3 w-3 rounded-full border border-black/20"
                            style={{ backgroundColor: '#E95420' }}
                            aria-label="Close"
                        />
                        <span
                            className="h-3 w-3 rounded-full border border-black/20"
                            style={{ backgroundColor: '#E5A50A' }}
                        />
                        <span
                            className="h-3 w-3 rounded-full border border-black/20"
                            style={{ backgroundColor: '#33D17A' }}
                        />
                    </div>
                    <p
                        className="min-w-0 flex-1 truncate text-center text-xs font-medium"
                        style={{ color: C.fg }}
                    >
                        {title}
                    </p>
                    <span className="w-12 shrink-0 text-right text-[10px]" style={{ color: C.dim }}>
                        {!finished ? '…' : result.type === 'error' ? 'err' : 'ok'}
                    </span>
                </div>

                <div
                    ref={bodyRef}
                    className="h-[260px] shrink-0 overflow-y-auto px-3 py-3 font-mono text-[12px] leading-6 sm:h-[300px] sm:text-[13px]"
                    style={{ backgroundColor: C.bg, color: C.fg }}
                >
                    <div className="space-y-0.5">
                        {shown.map((line, index) =>
                            line.tone === 'prompt' ? (
                                <PromptLine key={`${line.text}-${index}`} command={line.text} />
                            ) : (
                                <p
                                    key={`${line.text}-${index}`}
                                    className="update-term-line truncate"
                                    style={{ color: toneColor(line.tone) }}
                                >
                                    {line.text}
                                </p>
                            ),
                        )}

                        {finished && (
                            <p
                                className="update-term-line truncate pt-1"
                                style={{
                                    color: result.type === 'error' ? C.red : C.green,
                                }}
                                title={result.message}
                            >
                                {result.type === 'error' ? 'error: ' : ''}
                                {compactMessage(result.message)}
                            </p>
                        )}

                        {!finished && !scriptDone && (
                            <p style={{ color: C.fg }}>
                                <span
                                    className="inline-block h-[1.1em] w-[0.6em] align-middle"
                                    style={{
                                        backgroundColor: cursorOn ? C.fg : 'transparent',
                                    }}
                                />
                            </p>
                        )}

                        {waiting && (
                            <p className="pt-1" style={{ color: C.dim }}>
                                waiting for server
                                <span style={{ opacity: cursorOn ? 1 : 0 }}>_</span>
                            </p>
                        )}

                        {finished && (
                            <PromptLine command="" />
                        )}
                    </div>
                </div>

                {finished && (
                    <div
                        className="shrink-0 px-3 py-2"
                        style={{ backgroundColor: C.title, borderTop: `1px solid ${C.border}` }}
                    >
                        <button
                            type="button"
                            onClick={onClose}
                            className="w-full px-3 py-2 text-sm font-medium"
                            style={{
                                backgroundColor: '#E95420',
                                color: '#FFFFFF',
                            }}
                        >
                            Close
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
