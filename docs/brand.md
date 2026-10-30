# Easy Captcha mark

The mark pairs an open rounded C with an emerald check. The visible name is
Easy Captcha. It replaces the Starcode S mark, which remains in Git history.

Use `src/Resources/config/plugin.png` for the native Shopware extension icon.
Use `docs/easy-captcha-logo.png` for the horizontal symbol and wordmark on light
surfaces. Both have transparent backgrounds. The README uses the opaque white
version, `docs/easy-captcha-logo-readme.png`, so the dark lettering remains
readable in light and dark GitHub themes. Preserve aspect ratios and clear
space. Do not add a badge, tagline, shadow or certification claim.

The icon is 1254 x 1254 pixels. Both wordmarks are 2172 x 724 pixels. The
supplied source files were copied without pixel changes. Shopware scales its
native extension icon; no custom administration component is needed.

## Provenance and limits

The built-in image-generation tool produced the assets on 30 October 2026.
The brief requested a near-black open C and emerald check, followed by the
exact wordmark Easy Captcha. The horizontal versions were generated using the
preceding asset as a reference. They are separate raster outputs, not identical
vector geometry or editable vector masters.

Requested colors were `#141817` and `#087F63`. Raster fills retain slight tonal
variation. Trademark availability has not been assessed. The check symbol is
a visual mark, not proof of security, privacy compliance or human identity.

| Asset | SHA-256 |
| --- | --- |
| `src/Resources/config/plugin.png` | `fbd110d5b9f023c2be58cc1a02dcf56fa5359ab0d2631dec5d06d4347221e66f` |
| `docs/easy-captcha-logo.png` | `6836be948c3af4df02992c4fe6a12461b22baf52a4168010690a93768f0441eb` |
| `docs/easy-captcha-logo-readme.png` | `287d218ecd52040343c890828b70604dd1ab0cb614abbad0986f7725d46ed9bb` |

After changing the icon, run `php bin/console plugin:refresh` in the test shop
and inspect Extensions > My extensions at the native display size. Asset
presence alone does not prove that the administration has refreshed its icon.
