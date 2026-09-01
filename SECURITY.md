# Security Policy

## Supported Versions

Currently, the latest release and the previous minor line receive security updates.

| Version | Supported          |
| ------- | ------------------ |
| 1.2.x   | :white_check_mark: |
| 1.1.x   | :white_check_mark: |
| < 1.1   | :x:                |

## Reporting a Vulnerability

We take the security of this plugin seriously. If you discover a vulnerability, please do NOT open a public issue.

Instead, please email us at **security@mudrava.com** with the details of the vulnerability, explaining the steps to reproduce it. We will reply as soon as possible, give an estimate on when the fix will be available, and keep you informed of the progress.

## Security Posture

- Icon markup from the bundled sprite passes through a strict element/attribute allowlist at build time (`data/allowed-svg.json`) and again through `wp_kses()` at runtime. Event-handler attributes, external references (`href`, `xlink:href`) and unexpected elements are dropped or unwrapped.
- The REST icon catalog (`wp-json/mudrava-lucide/v1/icons`) defaults to logged-in users and can be widened or tightened via the `mudrava_lucide_field_icons_permission` filter.
- All output is escaped at render time (`esc_html`, `esc_attr`, `esc_url`); stored values are sanitized on save (`update_value`) and validated on save (`validate_value`).
- The plugin stores no credentials, options with secrets, or user data, and makes no external HTTP requests.

Thank you for helping keep the WordPress community secure.
