import { useEffect, useMemo, useRef, useState } from 'react';

const PULL_SCRIPT = [
    { tone: 'muted', text: '3rsolusi@vps:~$ git fetch origin --prune --tags' },
    { tone: 'signal', text: 'remote: Enumerating objects...' },
    { tone: 'signal', text: 'remote: Counting objects: 100% (12/12), done.' },
    { tone: 'muted', text: '3rsolusi@vps:~$ git pull --ff-only origin main' },
    { tone: 'sky', text: 'From github.com:origin/main' },
    { tone: 'sky', text: ' * branch            main       -> FETCH_HEAD' },
    { tone: 'ink', text: 'Updating local working tree...' },
    { tone: 'ink', text: 'Fast-forward' },
];

const CHECK_SCRIPT = [
    { tone: 'muted', text: '3rsolusi@vps:~$ git fetch origin --prune --tags' },
    { tone: 'signal', text: 'remote: Enumerating objects...' },
    { tone: 'signal', text: 'remote: Counting objects: 100% (8/8), done.' },
    { tone: 'sky', text: 'From github.com:origin/main' },
    { tone: 'ink', text: 'Checking local HEAD against origin...' },
];

function lineClass(tone) {
    switch (tone) {
        case 'signal':
            return 'text-teal-300';
        case 'sky':
            return 'text-sky-300';
        case 'ink':
            return 'text-slate-200';
        case 'ok':
            return 'text-emerald-300';
        case 'error':
            return 'text-red-300';
        default:
            return 'text-white/55';
    }
}

function compactMessage(message, max = 96) {
    const text = String(message || '')
        .replace(/\s+/g, ' ')
        .trim();
    if (text.length <= max) return text;
    return `${text.slice(0, max - 1)}…`;
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
                tone: 'sky',
                text: `Receiving objects for ${behind} commit${behind > 1 ? 's' : ''}...`,
            });
        }
        const pullIdx = next.findIndex((line) => line.text.includes('git pull'));
        if (pullIdx >= 0) {
            next[pullIdx] = {
                tone: 'muted',
                text: `3rsolusi@vps:~$ git pull --ff-only origin ${branch || 'main'}`,
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
        const blink = window.setInterval(() => setCursorOn((v) => !v), 480);
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

    return (
        <div
            className="fixed inset-0 z-[80] flex items-center justify-center bg-ink/55 p-4 backdrop-blur-[2px]"
            role="dialog"
            aria-modal="true"
            aria-label="Update dari GitHub sedang berlangsung"
        >
            <div className="flex w-full max-w-xl flex-col overflow-hidden border border-white/10 bg-ink shadow-2xl">
                <div className="flex h-1.5 w-full shrink-0">
                    <span className="flex-1 bg-gradient-to-r from-teal-400 to-teal-800" />
                    <span className="flex-1 bg-gradient-to-r from-slate-500 to-slate-900" />
                    <span className="flex-1 bg-gradient-to-r from-sky-400 to-blue-900" />
                </div>

                <div className="flex shrink-0 items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                    <div className="flex items-center gap-2">
                        <span className="h-2.5 w-2.5 rounded-full bg-gradient-to-br from-teal-400 to-teal-800" />
                        <span className="h-2.5 w-2.5 rounded-full bg-gradient-to-br from-slate-400 to-slate-800" />
                        <span className="h-2.5 w-2.5 rounded-full bg-gradient-to-br from-sky-400 to-blue-800" />
                    </div>
                    <p className="truncate text-xs font-semibold tracking-wide text-white/70">
                        terminal · {mode === 'check' ? 'git fetch' : 'git pull'} · tanpa npm
                    </p>
                    {!finished ? (
                        <span className="inline-flex items-center gap-1.5 text-[10px] font-semibold tracking-wide text-teal-300 uppercase">
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-teal-300" />
                            Running
                        </span>
                    ) : result.type === 'error' ? (
                        <span className="text-[10px] font-semibold tracking-wide text-red-300 uppercase">
                            Failed
                        </span>
                    ) : (
                        <span className="text-[10px] font-semibold tracking-wide text-emerald-300 uppercase">
                            Done
                        </span>
                    )}
                </div>

                <div
                    ref={bodyRef}
                    className="relative h-[260px] shrink-0 overflow-y-auto bg-gradient-to-b from-[#0c1218] via-[#101820] to-[#0a1520] px-4 py-4 font-mono text-[12px] leading-6 sm:h-[300px] sm:text-[13px]"
                >
                    <div
                        className="pointer-events-none absolute inset-0 opacity-[0.12]"
                        style={{
                            backgroundImage:
                                'linear-gradient(to right, transparent, transparent), repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(255,255,255,0.04) 3px)',
                        }}
                    />

                    <div className="relative space-y-1">
                        {shown.map((line, index) => (
                            <p
                                key={`${line.text}-${index}`}
                                className={`update-term-line truncate ${lineClass(line.tone)}`}
                            >
                                {line.text}
                            </p>
                        ))}

                        {finished && (
                            <p
                                className={`update-term-line truncate pt-2 ${lineClass(
                                    result.type === 'error' ? 'error' : 'ok',
                                )}`}
                                title={result.message}
                            >
                                {result.type === 'error' ? '✗ ' : '✓ '}
                                {compactMessage(result.message)}
                            </p>
                        )}

                        {!finished && !scriptDone && (
                            <p className="text-white/80">
                                <span className={cursorOn ? 'opacity-100' : 'opacity-0'}>▋</span>
                            </p>
                        )}

                        {waiting && (
                            <p className="pt-2 text-white/45">
                                Menunggu respons server
                                <span className={cursorOn ? 'opacity-100' : 'opacity-0'}>…</span>
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid shrink-0 grid-cols-3 border-t border-white/10 text-[10px] font-semibold tracking-wide text-white uppercase">
                    <div className="bg-gradient-to-br from-teal-500 to-teal-900 px-3 py-2.5 text-teal-50">
                        Status sync
                    </div>
                    <div className="bg-gradient-to-br from-slate-500 to-slate-950 px-3 py-2.5 text-slate-100">
                        Commit lokal
                    </div>
                    <div className="bg-gradient-to-br from-sky-500 to-blue-950 px-3 py-2.5 text-sky-50">
                        Commit remote
                    </div>
                </div>

                {finished && (
                    <div className="shrink-0 border-t border-white/10 px-4 py-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="w-full bg-gradient-to-r from-teal-600 via-slate-700 to-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:brightness-110"
                        >
                            Tutup terminal
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
