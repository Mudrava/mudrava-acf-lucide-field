import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const packages = [
	{ key: 'lucideStatic', name: 'lucide-static' },
	{ key: 'simpleIcons', name: 'simple-icons' },
];

let manifest;

try {
	manifest = JSON.parse(
		await readFile(path.join(__dirname, 'upstream-versions.json'), 'utf8'),
	);
} catch (err) {
	console.error(`Failed to read scripts/upstream-versions.json: ${err.message}`);
	process.exit(2);
}

let behind = 0;

for (const pkg of packages) {
	const res = await fetch(`https://registry.npmjs.org/${pkg.name}/latest`);

	if (!res.ok) {
		console.error(`Failed to fetch latest version for ${pkg.name}: ${res.status}`);
		process.exitCode = 2;
		continue;
	}

	const latest = (await res.json()).version;
	const current = manifest[pkg.key].version;

	if (latest !== current) {
		behind += 1;
		console.log(`${pkg.name}: bundled ${current} -> upstream latest ${latest}`);
	} else {
		console.log(`${pkg.name}: up to date (${current})`);
	}
}

if (behind > 0) {
	console.log('\nUpstream icon libraries have newer versions.');
	console.log('Update scripts/upstream-versions.json, then run:');
	console.log('  node scripts/build-sprites.mjs && php scripts/build-assets.php && node scripts/minify-assets.mjs');
	process.exitCode = 1;
}
