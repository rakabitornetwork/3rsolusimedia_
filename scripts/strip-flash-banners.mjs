import fs from 'fs';
import path from 'path';

const root = 'resources/js/Pages/Admin';
const files = [];

function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full);
        else if (full.endsWith('.jsx')) files.push(full);
    }
}

walk(root);

const successRe =
    /\r?\n\s*\{flash\?\.success && \(\r?\n\s*<div className="mb-[46] border border-signal\/30 bg-signal\/10 px-4 py-3 text-sm text-signal-deep">\r?\n\s*\{flash\.success\}\r?\n\s*<\/div>\r?\n\s*\)\}\r?\n/g;

const errorRe =
    /\r?\n\s*\{flash\?\.error && \(\r?\n\s*<div className="mb-[46] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">\r?\n\s*\{flash\.error\}\r?\n\s*<\/div>\r?\n\s*\)\}\r?\n/g;

const combinedErrorRe =
    /\r?\n\s*\{\(flash\?\.error \|\| error\) && \(\r?\n\s*<div className="mb-[46] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">\r?\n\s*\{flash\?\.error \|\| error\}\r?\n\s*<\/div>\r?\n\s*\)\}\r?\n/g;

let changed = 0;

for (const file of files) {
    let src = fs.readFileSync(file, 'utf8');
    const before = src;

    src = src.replace(successRe, '\n');
    src = src.replace(errorRe, '\n');
    src = src.replace(
        combinedErrorRe,
        `
            {error && (
                <div className="mb-4 border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {error}
                </div>
            )}
`,
    );

    if (src !== before) {
        fs.writeFileSync(file, src);
        changed += 1;
        console.log('updated', file);
    }
}

console.log('files changed', changed);
