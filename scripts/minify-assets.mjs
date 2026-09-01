/**
 * Minify bundled sprite assets with SVGO.
 *
 * Sprites are the single source of truth for both the admin picker
 * (<use> references) and the frontend inline renderer (byte-offset slices),
 * so they must stay well-formed: symbol IDs and shape markup are preserved.
 * The script aborts if the symbol count changes during optimization.
 *
 * Usage:
 *   node scripts/minify-assets.mjs          # write minified sprites in place
 *   node scripts/minify-assets.mjs --check  # exit non-zero if sprites are not minified
 */

import { optimize } from 'svgo';
import { mkdtemp, rename, rm, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

// IMPORTANT: pass the plugins array itself to optimize(). Passing a nested or
// undefined value silently falls back to SVGO's default preset, which strips
// <symbol> elements (removeHiddenElems/cleanupIds) and destroys the sprite.
const plugins = [
    {
        name: 'preset-default',
        params: {
            overrides: {
                // Symbol ids are referenced by <use href="#..."> and by the
                // PHP byte-offset index; ids must never be renamed or removed.
                cleanupIds: false,
                removeUselessDefs: false,
                removeHiddenElems: false,
                inlineStyles: false,
                minifyStyles: false,
                collapseGroups: false,
            },
        },
    },
    'removeComments',
];

const countSymbols = (svg) => (svg.match(/<symbol\b/g) || []).length;

const targets = ['assets/sprite.svg', 'assets/brand-sprite.svg'];
const check = process.argv.includes('--check');

let drift = false;

for (const target of targets) {
    const file = path.join(root, target);
    let source;

    try {
        source = await readFile(file, 'utf8');
    } catch (err) {
        process.stdout.write(`ABORT: cannot read ${target}: ${err.message}\n`);
        process.exitCode = 1;
        break;
    }
    const sourceSymbols = countSymbols(source);
    const { data } = optimize(source, { path: file, multipass: true, js2svg: { pretty: false }, plugins });
    const outputSymbols = countSymbols(data);

    // Structure guard: the byte-offset index depends on every symbol
    // surviving optimization with its id intact (svgo itself throws on
    // unparseable XML, so a successful optimize() call proves well-formedness).
    const openSymbols = (data.match(/<symbol\b/g) || []).length;
    const closedSymbols = (data.match(/<\/symbol>/g) || []).length;

    if (openSymbols !== closedSymbols || !data.includes('</svg>')) {
        process.stdout.write(`ABORT: optimized ${target} has unbalanced symbol markup.\n`);
        process.exitCode = 1;
        break;
    }

    if (sourceSymbols !== outputSymbols) {
        process.stdout.write(`ABORT: minification would change the symbol count of ${target} (${sourceSymbols} -> ${outputSymbols}).\n`);
        process.exitCode = 1;
        break;
    }

    if (check) {
        if (data !== source) {
            process.stdout.write(`CHECK FAILED: ${target} is not minified (run npm run build:assets).\n`);
            drift = true;
        }
    } else if (data !== source) {
        const tmp = await mkdtemp(path.join(tmpdir(), 'minify-'));
        const tmpFile = path.join(tmp, path.basename(target));

        await writeFile(tmpFile, data, 'utf8');
        await rename(tmpFile, file);
        await rm(tmp, { recursive: true, force: true });

        process.stdout.write(`minified ${target}: ${Buffer.byteLength(source)} -> ${Buffer.byteLength(data)} bytes\n`);
    } else {
        process.stdout.write(`${target} already minified\n`);
    }
}

if (drift) {
    process.exitCode = 1;
}
