# Architecture

This document records the load-bearing design decisions of the plugin and the
reasoning behind them, so future contributors can judge which trade-offs still
hold. Audience: contributors and reviewers.

## System overview

```
┌────────────────────────────── build (dev machine / CI) ──────────────────────────────┐
│ lucide-static / simple-icons tarballs                                                 │
│   └─ scripts/build-sprites.mjs ─► assets/sprite.svg, assets/brand-sprite.svg          │
│        └─ scripts/build-assets.php  (php/xml)                                         │
│             ├─ data/{icons,brand-icons}.json      catalog + search tags               │
│             ├─ data/{lucide,simple}-index.php     byte offsets {offset,length}        │
│             └─ validate against data/allowed-svg.json (abort on violation)            │
│                  └─ scripts/minify-assets.mjs ─► shipped (minified) sprites           │
└────────────────────────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────── WordPress runtime ──────────────────────────────────┐
│ Admin (ACF edit screen)                                                               │
│   includes/class-mudrava-acf-field-lucide-icon.php                                    │
│     ├─ enqueue field.js/css (once per screen, filemtime cache-bust)                   │
│     ├─ field filters: load/update/format/validate_value, render_field                  │
│     └─ capability-gated admin notices (invalid defaults, unknown values)               │
│   mudrava-acf-lucide-field.php (bootstrap)                                            │
│     └─ register REST route mudrava-lucide/v1/icons (lazy catalog)                      │
│   assets/js/field.js  Vue-like vanilla controller                                     │
│     ├─ Repository: search index + compat aliases (from REST payload)                  │
│     ├─ Sprites: lazy <symbol> prefetch from spriteUrl via byte-range-free fetch+slice │
│     └─ Picker: paginated grid, combobox a11y (roving tabindex + aria-activedescendant)│
│                                                                                       │
│ Frontend / templates                                                                  │
│   mudrava_get_lucide_icon()  ├─ mode=inline: <svg>…children…</svg>                    │
│     [lucide_icon] shortcode  └─ mode=sprite: <svg><use href="#id"/></svg>             │
│                                 + queue_symbol() → print_sprite() on wp_footer        │
│   read path: data/*-index.php byte offsets → one sprite read + substr → wp_kses       │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

Sources of truth: `mudrava-acf-lucide-field.php` (runtime, helpers, REST),
`includes/class-mudrava-acf-field-lucide-icon.php` (ACF field type),
`scripts/` (pipeline), `data/allowed-svg.json` (sanitizer contract).

## ADR-1: Lazy REST catalog instead of localized payloads

**Problem.** 1,791 Lucide + 3,457 Simple Icons names/tags were serialized into
`acf/input_admin_enqueue_scripts` per admin screen (~600 KB of inline JSON on
every post edit screen, plus re-parsing on every paginated grid render client-side).

**Options considered.** (a) keep localize + slim the payload; (b) paginate server-side
over admin-ajax; (c) register a read-only REST route and fetch the catalog once per
admin screen, memoized client-side.

**Decision.** (c) — `mudrava-lucide/v1/icons` returns search index + tags + sprite
URLs + compat aliases + sanitizer vocabulary in one request, permission callback
`is_user_logged_in()` filterable via `mudrava_lucide_field_icons_permission`.

**Consequences.** Admin payload drops to the inline script tag only; the catalog is
cached in `Repository` for the page lifetime; non-bundled JS must not rely on
`mudravaLucideField` global anymore. Permission is default-deny-ish (logged-in) but
the endpoint is public-by-design on multisite author roles; the payload contains only
bundled public asset metadata, never user data.

**Rejected.** (a) still pays the JSON parse cost per screen and re-sends tags with
every field group; (b) admin-ajax cannot be reused by frontend renderers and bypasses
core's REST caching/permission layer.

## ADR-2: Byte-offset index instead of per-request regex parsing

**Problem.** Every `mudrava_get_lucide_icon()` call used to `file_get_contents` the
whole sprite and run regex over it; a page with 30 icons re-read and re-parsed
hundreds of KB dozens of times.

**Options considered.** (a) per-request memoized parse (the 1.0.1 transient approach);
(b) one symbol per file with thousands of small files; (c) build a byte-offset index
(`name → {offset,length}` of the symbol's inner markup) and slice out the exact
symbol bytes at runtime.

**Decision.** (c). `data/lucide-index.php` / `data/simple-index.php` are PHP
`var_export` arrays; the runtime reads each sprite once per request through the
`WP_Filesystem` API (statically memoized) and `substr`s out the exact symbol bytes,
passing them through `wp_kses` (allowlist from `data/allowed-svg.json`).

**Consequences.** The first symbol from a sprite reads that sprite once (bounded by
file size, memoized for the request); every further symbol is O(1) `substr` + kses
over one symbol; the
index is generated with the sprites in the same pipeline (drift is caught by
`build-assets.php --check`); the index is a new attack/corruption surface — a stale
index yields garbage bytes, but the allowlist sanitizer still bounds the output, and
`--check` fails the build if the sprite and index disagree.

**Rejected.** (a) O(sprite size) per call and transient invalidation churn; (b)
tens of thousands of inodes, slow installs, no space savings after minification.

## ADR-3: Inline SVG by default, opt-in sprite mode

**Problem.** `<use href="sprite.svg#…">` breaks with CDN/domain rewrites, CSP and
some proxies (the 1.0.1 incident), but inlining large paths per icon inflates HTML.

**Decision.** Default is inline SVG (children of the symbol inside a freshly built
`<svg>` wrapper). `mode: 'sprite'` (helper arg and shortcode attr) emits
`<use>` and queues the symbol; `print_sprite()` assembles a single hidden
`<svg>` sprite sheet on `wp_footer`, deduplicated per request.

**Consequences.** The wrapper `<symbol id viewBox="0 0 24 24">` is rebuilt by
`print_sprite()` because the sanitizer allowlist intentionally excludes `symbol`/`svg`
— stored and sliced markup is inner children only. Helper functions strip any
accidental wrapper defensively.

**Rejected.** Sprite-only default (fragile under CDNs/CSP, fails silently as blank
icons); inline-only (loses the sprite option that frontends explicitly asked for).

## ADR-4: Build-time allowlist + runtime kses (defense in depth)

**Problem.** Sprite markup becomes trusted HTML the moment it is echoed; upstream
icon SVGs can contain `<script>`, `<foreignObject>`, event handlers, `href` to
`javascript:` URLs.

**Options considered.** (a) sanitize once at build, trust the artifact; (b) runtime
`wp_kses` only; (c) both, sharing one machine-readable allowlist.

**Decision.** (c). `data/allowed-svg.json` (elements + attributes, no event
handlers, no `href`/`xlink:href`) is enforced by `build-assets.php` (the build
aborts when an upstream symbol violates it) and by `wp_kses()` with
`mudrava_lucide_field_allowed_svg_children()` at every render. The admin picker re-sanitizes in the browser with
`DOMParser` before injecting into the grid.

**Consequences.** Upstream policy changes fail loudly at build time rather than
silently shipping markup that the runtime would strip; the runtime kses cost is paid
on the small per-icon slice only; JS must never trust raw sprite strings.

**Rejected.** (a) a compromised or hand-edited sprite file becomes an XSS vector
with no second line of defence; (b) build would happily generate assets that then
render as sanitized-to-nothing icons, deferring the problem to runtime.

## ADR-5: Compatibility aliases instead of database rewrites

**Problem.** Lucide 1.38.0 removed nine icon names (`angry`, `annoyed`, `frown`,
`history`, `laugh`, `meh`, `podcast`, `smile`, `smile-plus`) that are stored as plain
strings in post meta by thousands of sites. The old plan ("run a one-time rewrite on
upgrade") was destructive, unrollbackable, and would have silently overwritten values
that site owners had intentionally remapped.

**Options considered.** (a) destructive one-time meta migration on upgrade; (b) ship
the removed icons as vendored files (fork drift forever); (c) resolve removed names
through a data-driven alias map at render/resolve time.

**Decision.** (c). `data/compat-aliases.json` maps each removed name to its current
canonical replacement (`smile → face-slightly-smiling`, `history → rotate-ccw`,
`podcast → rss`, …). `resolve_icon()` consults it after an exact-miss on the catalog,
and the picker exposes the same map so newly picked values are canonical
(`smile`-era users keep seeing icons; the picker now writes `face-slightly-smiling`).

**Consequences.** Zero DB writes on upgrade, fully reversible by editing the JSON,
works for ACF local JSON fields and repeaters alike; the alias map is shipped data
reviewed with each icon update; alias resolution is one array lookup on the miss path.

**Rejected.** (a) rewrites user content without consent, breaks rollback, and cannot
know which replacement a site prefers; (b) keeps maintaining upstream gaps as our own
asset debt.

## ADR-6: Unknown values warn by default instead of hard-failing

**Problem.** Strict validation ("this icon does not exist → refuse to save") blocks
saving entire posts when an icon was removed upstream or an editor imports legacy
content — worst outcome for content editors, and undecidable at our layer (we cannot
know the site owner's intent for an unknown string).

**Options considered.** (a) always hard-fail; (b) always accept silently; (c)
per-field setting `on_unknown: warn|error`, default `warn`, aggregates unknown values
into a short-lived transient surfaced as a dismissible admin notice.

**Decision.** (c), implemented as `Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT`.
`validate_value()` only hard-fails in `error` mode; otherwise the unknown token is
recorded (deduplicated, 120 s transient, merged with field-group default validation)
and rendered by `mudrava_lucide_field_invalid_defaults_notice()`. Default is `warn`
so field groups serialized without the new key do not change behavior for existing
sites. The setting UI lives in ACF Pro's validation settings section (free ACF keeps
the `warn` default).

**Consequences.** Editors never lose a post save over a removed icon; admins still
learn about broken references; strict teams can opt in per field; unknowns are a
notice, not a silent no-op.

**Rejected.** (a) caused the "cannot save the post at all" class of bug reports; (b)
lets broken references rot silently in production.

## Data files

| File | Producer | Consumer | Notes |
|------|----------|----------|-------|
| `data/icons.json`, `data/brand-icons.json` | build-assets.php | picker (REST), resolve() | name→label metadata |
| `data/lucide-tags.php`, `data/simple-tags.php` | build-assets.php | REST catalog | precomputed lowercase search tags |
| `data/lucide-index.php`, `data/simple-index.php` | build-assets.php | runtime render | symbol inner-markup byte offsets |
| `data/compat-aliases.json` | manual review per update | resolve(), picker | removed-name aliases |
| `data/allowed-svg.json` | manual | build-assets.php, runtime kses, JS DOMParser | the sanitizer contract |

## Compatibility constraints

- PHP >= 7.4 (typed properties with defaults are fine; no PHP 8 syntax).
- WordPress >= 6.0; REST permission callback signature `($request, $registered)`.
- ACF >= 6.x: `load_value`/`update_value`/`format_value`/`validate_value`/
  `render_field`/`render_field_settings` are registered by ACF core as *filters*
  (`acf/load_value/{type}` etc.) if the subclass defines them; they are not base
  class methods, so subclass signatures must match ACF's call sites (tested against
  ACF 6.8.x).
- The sprite is the single source of truth; regenerated artifacts must stay in sync
  or `--check` modes fail (CI enforces this).

## Testing

Unit tests (`tests/php/`, PHPUnit 9 with a minimal WordPress-stub bootstrap) run without WordPress:
`tests/php/bootstrap.php` provides minimal WP/ACF stubs (gettext, escaping,
transients, filters, REST, `acf_field` base class with hook-registration mirroring
ACF 6.x, `PHP_kses_Test_Kses` as a DOM-based kses double). The full plugin is loaded
through it, so helper/field-class logic (resolve, sanitize, validate, render,
notices) is covered. `php scripts/build-assets.php --check` and
`node scripts/minify-assets.mjs --check` guard the generated artifacts;
`vendor/bin/phpcs` and `npx eslint .` enforce style. End-to-end tests
(`tests/e2e/`, Playwright) run against a real WordPress + ACF instance created
by `scripts/e2e-setup.sh`: they cover the lazy catalog fetch, picker search and
keyboard a11y, save persistence, the warn/error unknown-value paths, the
inline/sprite frontend rendering and REST permission, with fixture state reset
before every test.
