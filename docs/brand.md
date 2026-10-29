# Starcode mark

Concept B, selected on 29 October 2026, uses a two-part geometric S in near-black
and emerald. It replaces the earlier brackets-and-star mark, which remains in
Git history.

Use `src/Resources/config/plugin.png` for the native Shopware extension icon.
Use `docs/starcode-logo.png` for the horizontal symbol and wordmark. Both PNGs
have transparent backgrounds and are intended for light surfaces. Preserve
their aspect ratios and clear space. Do not add a badge, tagline or shadow.

The icon is 1254 x 1254 pixels. The horizontal logo is 2172 x 724 pixels.
The selected source files were copied without pixel changes. Native Shopware
scales the extension icon to its display size; do not replace that presentation
with a separate custom administration component.

## Provenance and limits

The artwork was produced with the built-in image-generation tool on
29 October 2026 from the selected concept B. Separate reference-image edits
extracted the standalone symbol and horizontal lockup, retaining their
geometry and lettering and requesting transparent backgrounds.

Requested colors were `#141817` and `#087F63`. The raster fills retain slight
tonal variation and are not exact-color vector masters. No editable vector
file or trademark clearance is claimed.

| Asset | SHA-256 |
| --- | --- |
| `src/Resources/config/plugin.png` | `ec44eedc46aded772246fd1b1c1e48c7df50f5216704ac855b5c0c96007709c0` |
| `docs/starcode-logo.png` | `c5a2a7ee94deb1ba2d9a0500dc3203fb243f5d44bf3a7e0987763cd43a62c0a7` |

After changing the icon, run `php bin/console plugin:refresh` in the test shop
and inspect Extensions > My extensions at the native display size. Asset
presence alone does not prove that the administration has refreshed its icon.
