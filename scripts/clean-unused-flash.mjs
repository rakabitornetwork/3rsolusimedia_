import fs from 'fs';
import path from 'path';

function walk(dir, acc = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, acc);
        else if (full.endsWith('.jsx')) acc.push(full);
    }
    return acc;
}

for (const file of walk('resources/js/Pages/Admin')) {
    let src = fs.readFileSync(file, 'utf8');
    const before = src;

    src = src.replace(/^[ \t]*const \{ flash \} = usePage\(\)\.props;\r?\n/gm, '');
    src = src.replace(
        /const \{ flash, auth \} = usePage\(\)\.props;/g,
        'const { auth } = usePage().props;',
    );

    if (!src.includes('usePage(') && /from '@inertiajs\/react'/.test(src) && /\busePage\b/.test(src)) {
        src = src.replace(', usePage', '');
        src = src.replace('usePage, ', '');
    }

    if (src !== before) {
        fs.writeFileSync(file, src);
        console.log('cleaned', file);
    }
}
