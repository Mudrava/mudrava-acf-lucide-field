/**
 * Rebuild bundled sprites and search data from upstream packages.
 *
 * Sources (declared in scripts/upstream-versions.json with sha512 integrity):
 *   lucide-static  -> assets/sprite.svg, data/icons.json
 *   simple-icons   -> assets/brand-sprite.svg, data/brand-icons.json, data/brand-icons-meta.json
 *
 * Usage:
 *   node scripts/build-sprites.mjs --lucide-tgz <path> --simple-tgz <path>
 *
 * The script verifies tarball integrity, serializes upstream icon data
 * deterministically (sorted by name), and only emits whitelisted shape
 * elements/attributes. Non-whitelisted content aborts the build.
 */

import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const manifest = JSON.parse(readFileSync(path.join(root, 'scripts/upstream-versions.json'), 'utf8'));
const allowed = JSON.parse(readFileSync(path.join(root, 'data/allowed-svg.json'), 'utf8'));
const allowedElements = new Set(allowed.elements);
const allowedAttributes = new Set(allowed.attributes);

function arg(name) {
    const idx = process.argv.indexOf(`--${name}`);

    if (idx === -1 || !process.argv[idx + 1]) {
        process.stdout.write(`Missing --${name} argument. See scripts/build-sprites.mjs header.\n`);
        process.exit(1);
    }

    return process.argv[idx + 1];
}

function verifyIntegrity(tgz, integrity) {
    const parts = String(integrity || '').split('-');

    if (parts.length !== 2 || !parts[0] || !parts[1]) {
        process.stdout.write(`ABORT: malformed integrity value for ${tgz}
`);
        process.exit(1);
    }

    const [algo, expected] = parts;
    let tarball;

    try {
        tarball = readFileSync(tgz);
    } catch {
        process.stdout.write(`ABORT: cannot read tarball ${tgz}
`);
        process.exit(1);
    }

    const digest = createHash(algo).update(tarball).digest('base64');

    if (digest !== expected) {
        process.stdout.write(`ABORT: integrity mismatch for ${tgz}\n  expected ${expected}\n  got      ${digest}\n`);
        process.exit(1);
    }
}

function extract(tgz, dest) {
    execFileSync('tar', ['xzf', tgz, '-C', dest], { stdio: 'inherit' });

    return path.join(dest, 'package');
}

function escapeXml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function serializeNodes(nodes) {
    return nodes
        .map(([tag, attrs]) => {
            if (!allowedElements.has(tag)) {
                throw new Error(`element <${tag}> not whitelisted`);
            }

            const attrString = Object.entries(attrs)
                .map(([name, value]) => {
                    if (!allowedAttributes.has(name)) {
                        throw new Error(`attribute '${name}' not whitelisted`);
                    }

                    return `${name}="${escapeXml(value)}"`;
                })
                .join(' ');

            return `<${tag} ${attrString}/>`;
        })
        .join('');
}

function buildSprite(symbols) {
    const lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<svg xmlns="http://www.w3.org/2000/svg">'];

    for (const [id, inner] of symbols) {
        lines.push(`<symbol id="${escapeXml(id)}" viewBox="0 0 24 24">${inner}</symbol>`);
    }

    lines.push('</svg>', '');

    return lines.join('\n');
}

function sortedJson(data) {
    const sorted = {};

    for (const key of Object.keys(data).sort()) {
        sorted[key] = data[key];
    }

    return JSON.stringify(sorted, null, 2) + '\n';
}

// All outputs are staged and only flushed after the complete build has
// succeeded, so a mid-run abort never leaves a half-updated data set.
const pendingWrites = [];

function stageWrite(file, content) {
    pendingWrites.push([file, content]);
}

// --- Lucide ---

const lucideTgz = arg('lucide-tgz');
const simpleTgz = arg('simple-tgz');

verifyIntegrity(lucideTgz, manifest.lucideStatic.integrity);
verifyIntegrity(simpleTgz, manifest.simpleIcons.integrity);

const lucideTmp = mkdtempSync(path.join(tmpdir(), 'lucide-'));
const simpleTmp = mkdtempSync(path.join(tmpdir(), 'simple-'));

try {
    const lucide = extract(lucideTgz, lucideTmp);
    const nodes = JSON.parse(readFileSync(path.join(lucide, 'icon-nodes.json'), 'utf8'));
    const tags = JSON.parse(readFileSync(path.join(lucide, 'tags.json'), 'utf8'));

    const lucideSymbols = Object.keys(nodes)
        .sort()
        .map((name) => [name, serializeNodes(nodes[name])]);

    const lucideSprite = buildSprite(lucideSymbols);
    const lucideIcons = {};

    for (const [name] of lucideSymbols) {
        const entry = tags[name];

        if (!Array.isArray(entry)) {
            throw new Error(`missing tags for icon '${name}'`);
        }

        lucideIcons[name] = entry;
    }

    process.stdout.write(`lucide: built ${lucideSymbols.length} symbols\n`);

    // --- Simple Icons ---

    const simple = extract(simpleTgz, simpleTmp);
    const data = JSON.parse(readFileSync(path.join(simple, 'data/simple-icons.json'), 'utf8'));

    const brandSymbols = [];
    const brandTags = {};
    const brandMeta = {};

    const seenSlugs = new Set();

    for (const icon of data) {
        const { title } = icon;
        const rawSlug = String(icon.slug || '');

        // Validate before any filesystem access: an unexpected slug must
        // never influence which path is read.
        if (!rawSlug || !/^[A-Za-z0-9_-]+$/.test(rawSlug)) {
            throw new Error(`unexpected brand slug '${rawSlug}'`);
        }

        // Upstream slugs may contain underscores; normalize to dashes so the
        // symbol id matches the runtime token normalization exactly.
        const slug = rawSlug.replace(/_/g, '-');

        if (!slug || !/^[a-z0-9-]+$/.test(slug)) {
            throw new Error(`unexpected brand slug '${rawSlug}'`);
        }

        if (seenSlugs.has(slug)) {
            throw new Error(`duplicate brand slug '${slug}' after normalization`);
        }

        seenSlugs.add(slug);

        const svg = readFileSync(path.join(simple, 'icons', `${rawSlug}.svg`), 'utf8');

        // Simple Icons SVGs wrap shapes in a root <svg> and carry a <title>
        // for accessibility; the sprite symbol provides its own semantics,
        // so only the allowed shape elements are carried over.
        const shapeRe = /<(path|rect|circle|ellipse|line|polygon|polyline)\b([^>]*?)\/?>/g;
        const nodes2 = [];
        let match;

        while ((match = shapeRe.exec(svg)) !== null) {
            const attrRe = /([A-Za-z-]+)(?:\s*=\s*"([^"]*)")?/g;
            const attrs = {};
            let attrMatch;
            const attrSource = (match[2] || '').trim();

            while ((attrMatch = attrRe.exec(attrSource)) !== null) {
                const name = attrMatch[1];
                const value = attrMatch[2] ?? '';

                if (!allowedAttributes.has(name)) {
                    throw new Error(`brand icon '${slug}': attribute '${name}' not whitelisted`);
                }

                attrs[name] = value;
            }

            nodes2.push([match[1], attrs]);
        }

        if (nodes2.length === 0) {
            throw new Error(`brand icon '${slug}': no shapes found`);
        }

        brandSymbols.push([`simple-${slug}`, serializeNodes(nodes2)]);
        const hex = typeof icon.hex === 'string' ? icon.hex : '';

        brandTags[slug] = ['brand', 'logo', 'simple-icons', slug, title, `#${hex}`];
        brandMeta[slug] = {
            title,
            hex,
            source: icon.source ?? '',
            guidelines: icon.aliases?.guidelines ?? '',
        };
    }

    brandSymbols.sort((a, b) => (a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : 0));

    process.stdout.write(`simple-icons: built ${brandSymbols.length} symbols\n`);

    // Update version constants in the main plugin file.
    const mainFile = path.join(root, 'mudrava-acf-lucide-field.php');
    let main = readFileSync(mainFile, 'utf8');

    const replacements = [
        [/define\('MUDRAVA_LUCIDE_FIELD_LUCIDE_VERSION', '[^']*'\);/, `define('MUDRAVA_LUCIDE_FIELD_LUCIDE_VERSION', '${manifest.lucideStatic.version}');`],
        [/define\('MUDRAVA_LUCIDE_FIELD_SIMPLE_ICONS_VERSION', '[^']*'\);/, `define('MUDRAVA_LUCIDE_FIELD_SIMPLE_ICONS_VERSION', '${manifest.simpleIcons.version}');`],
    ];

    for (const [pattern, replacement] of replacements) {
        if (!pattern.test(main)) {
            throw new Error(`version constant pattern ${pattern} not found in main plugin file`);
        }

        main = main.replace(pattern, replacement);
    }

    stageWrite('assets/sprite.svg', lucideSprite);
    stageWrite('data/icons.json', sortedJson(lucideIcons));
    stageWrite('assets/brand-sprite.svg', buildSprite(brandSymbols));
    stageWrite('data/brand-icons.json', sortedJson(brandTags));
    stageWrite('data/brand-icons-meta.json', sortedJson(brandMeta));
    stageWrite('mudrava-acf-lucide-field.php', main);
} finally {
    rmSync(lucideTmp, { recursive: true, force: true });
    rmSync(simpleTmp, { recursive: true, force: true });
}

for (const [file, content] of pendingWrites) {
    writeFileSync(path.join(root, file), content, 'utf8');
}

process.stdout.write(`flushed ${pendingWrites.length} generated files\n`);
