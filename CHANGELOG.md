# Changelog

## 0.2.0 - 2026-10-30

- Rename the product and Composer package to Easy Captcha and
  `skyyware/easy-captcha`, with a new icon and wordmark.
- Keep the Shopware plugin identity, settings, tables, migrations and existing
  proof protocol. Replace the old Composer 0.1 package without uninstalling.
- Add `window.EasyCaptcha`, Easy Captcha challenge URLs and
  `easy-captcha:cleanup`, keeping their original names as aliases.
- Let an already verified native form submit on its first click. Prevent a
  delayed widget event from reusing a consumed proof on sequential AJAX calls.
- Add bounded operator cleanup with a dry run and a nonzero status when its
  batch budget leaves expired rows. No scheduler or worker is installed.

## 0.1.1 - 2026-10-29

- Keep browser-restored checkbox state aligned with the actual proof state.
- Reset the widget and its feedback when the surrounding form is reset.
- Add regression checks for restoration and reset. Server validation is unchanged.

## 0.1.0 - 2026-10-29

Initial integration-test release.

- Self-hosted ALTCHA proof of work with a locally bundled checkbox and worker.
- Native per-sales-channel configuration for six form groups and selected paths.
- Storefront and Store API enforcement, expiry, atomic replay prevention and
  bounded challenge rates.
- Dynamic form discovery and an explicit API for scripted submissions.
- German and English text, theme colors, keyboard input and responsive layout.
- PHP, JavaScript and isolated Shopware controller tests. No external CAPTCHA
  account or repository automation.

External provider forms need a server-side adapter. No adapter or production
compatibility guarantee is included in this release.
