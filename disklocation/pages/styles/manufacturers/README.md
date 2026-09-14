# Manufacturer logos

Disk Location can optionally show a small manufacturer logo on each tray tile, next to
the drive-type icon (HDD/SSD/NVMe). **This folder ships empty.** The plugin itself does
not include, generate, or bundle any manufacturer logos, because those are trademarked
assets owned by their respective companies - it isn't our call to redistribute them, even
for identification purposes.

If you'd like logos to show up, you (or a contributor to your fork) can add your own
files here, sourced from each manufacturer's own official press/brand kit page and used
in accordance with that manufacturer's own trademark/brand guidelines. Once a file is in
place, the plugin will pick it up automatically - no code changes needed.

## Naming convention

Files are looked up by a normalized brand slug: lowercase, letters and digits only, no
spaces or punctuation. Save your file as:

```
pages/styles/manufacturers/<slug>.svg
```

The auto-detector (`detect_drive_brand()` in `pages/functions.php`) and the manual
override field on the Tray Allocations page both resolve to the same slugs, so a file you
add will be picked up either way. Currently recognized slugs (feel free to add more brand
patterns to `detect_drive_brand()` if yours is missing):

| Slug             | Brand           |
|------------------|-----------------|
| westerndigital   | Western Digital |
| seagate          | Seagate         |
| toshiba          | Toshiba         |
| hgst             | HGST            |
| samsung          | Samsung         |
| crucial          | Crucial         |
| micron           | Micron          |
| sandisk          | SanDisk         |
| kingston         | Kingston        |
| intel            | Intel           |
| adata            | ADATA           |
| corsair          | Corsair         |
| skhynix          | SK hynix        |
| lexar            | Lexar           |
| teamgroup        | Team Group      |
| patriot          | Patriot         |
| siliconpower     | Silicon Power   |
| transcend        | Transcend       |
| pny              | PNY             |
| fujitsu          | Fujitsu         |
| hitachi          | Hitachi         |

## Auto-detection vs. override

Drive brand is guessed automatically from smartctl's `model_family` field, with a
fallback to common model-number prefixes when that's empty - which happens often for
SSDs built around a generic/OEM controller rather than a brand-specific one. Since this
is best-effort, it **will** get it wrong sometimes.

Every drive has a manual override on the **Tray Allocations** page: the "Manufacturer"
column is an editable text field. Leave it blank to use auto-detection (shown as the
field's placeholder text), or type the correct brand name to override it - the typed
value is what gets slugified and matched against this folder.

## Disclaimer

All product names, logos, and brands referenced or displayed by this plugin are the
property of their respective owners. Use of any manufacturer name or logo is for drive
identification purposes only and does not imply any endorsement, sponsorship, or
affiliation. The Disk Location plugin and its author(s)/contributors are not affiliated
with, sponsored by, or endorsed by any of the manufacturers referenced.
