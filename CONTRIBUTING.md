# Contributing to Mudrava Icon Field for ACF with Lucide

First off, thank you for considering contributing to this plugin! It's people like you that make the open-source community such a great place to learn, inspire, and create.

## How Can I Contribute?

### Reporting Bugs
If you find a bug, please open an issue in the GitHub repository. Include:
- A clear descriptive title
- Steps to reproduce the bug
- Expected behavior vs actual behavior
- Versions used (WordPress, PHP, ACF Pro, Plugin)

### Suggesting Enhancements
Feature requests are welcome. Please open an issue outlining:
- What you want to achieve
- Why it would be useful to most users
- Any potential implementation ideas

### Pull Requests
1. Fork the repo and create your branch from `main`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Ensure the code follows the official WordPress Coding Standards.
5. Make sure your commit messages are descriptive.
6. Issue that pull request!

## Coding Guidelines

- **PHP:** Follow the WordPress PHP Coding Standards. Run your code through `phpcs` if possible. Ensure strict typing where appropriate.
- **JavaScript:** Follow standard WordPress JS practices. Use ES6+ constructs carefully, considering back-compatibility.
- **CSS:** Use standard, organized CSS keeping to the existing structure.

## Development Setup

```bash
npm ci          # dev tooling: svgo, eslint
composer install # phpunit, WordPress Coding Standards
```

`vendor/` and `node_modules/` are not committed; PHP linting and unit tests run from the repo root.

## Building/Updating Icons

The sprite files and metadata in `data/` are generated artifacts; never hand-edit them.

1. Update `scripts/upstream-versions.json` with the new `lucideStatic` / `simpleIcons` versions, integrity hashes and tarball URLs.
2. Run `node scripts/build-sprites.mjs` to rebuild `assets/sprite.svg` and `assets/brand-sprite.svg` (deterministic, sha512-verified).
3. Run `php scripts/build-assets.php` to rebuild the byte-offset indices (`data/*-index.php`), search tags (`data/*-tags.php`) and JSON metadata (`data/icons.json`, `data/brand-icons.json`), validating every symbol against `data/allowed-svg.json` (the build aborts on any non-allowlisted element or attribute).
4. Run `node scripts/minify-assets.mjs` to produce the shipped minified sprites.

## Verifying Your Changes

```bash
vendor/bin/phpunit            # unit tests (tests/php, WP stubs in tests/php/bootstrap.php)
vendor/bin/phpcs              # WordPress Coding Standards
npm exec eslint .             # JS lint (eslint.config.mjs)
php scripts/build-assets.php --check   # generated data files are in sync
node scripts/minify-assets.mjs --check # shipped sprites are minified
```

## End-to-End Tests (Playwright)

The e2e suite runs against a real WordPress + ACF site. Prepare a local test
site (requires Docker, wp-cli and PHP with mysqli):

Official Plugin Check runs against the distribution archive:

```bash
bash scripts/build-dist.sh
```

The e2e suite itself runs against a real WordPress + ACF site. Prepare a local
test site (requires Docker, wp-cli and PHP with mysqli):

```bash
WP_SITE_DIR=/tmp/wp-e2e WP_PORT=8877 bash scripts/e2e-setup.sh
WP_BASE_URL=http://127.0.0.1:8877 WP_SITE_PATH=/tmp/wp-e2e npx playwright test
```

The fixture mu-plugin (`tests/e2e/fixtures/mudrava-e2e-fixture.php`) registers
a local field group and seeds posts with legacy/unknown values; global setup
logs in, resolves post IDs through REST and resets fixture state before each
test.

All of the above run in CI and must pass before a pull request is merged.

## Translations

The plugin is translation-ready (text domain `mudrava-acf-lucide-field`). Regenerate the template with `php scripts/make-pot.php` when strings change.

Thank you for your contributions!
