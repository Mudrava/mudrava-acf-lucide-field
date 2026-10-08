## What

- [ ] Describe the change and the problem it solves.

## Compatibility

- [ ] Stored icon values stay backward compatible (no value-format change).
- [ ] Works with ACF free and Pro 6.0+.
- [ ] Generated `data/` files regenerated with `php scripts/build-assets.php`,
      never hand-edited.

## Verification

- [ ] `composer lint` and `npm exec eslint .` pass.
- [ ] `composer test` passes.
- [ ] `php scripts/build-assets.php --check` and
      `node scripts/minify-assets.mjs --check` pass.
- [ ] Playwright e2e passes against the local WP+ACF site (if picker behavior
      changed).

## Docs

- [ ] `readme.txt`, `README.md` and `CHANGELOG.md` updated.
