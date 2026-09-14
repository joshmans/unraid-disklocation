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

### Hotlinking a manufacturer-hosted logo instead

The same override field also accepts a direct **https** image URL (ending in `.svg`,
`.png`, `.jpg`, `.jpeg`, `.webp`, or `.gif`) instead of a brand name. If you'd rather
point at a manufacturer's own hosted logo than download and commit a copy here, type
the URL in and the tray tile will render it directly via `<img src="...">` - nothing
gets stored in this repo for that device.

This is a genuinely different tradeoff than bundling a file here, but it isn't a free
pass either, and it's worth going in with eyes open:

- It's on you to confirm the specific URL you use actually fits within that
  manufacturer's own usage terms for this kind of embedding - most brand kits are
  scoped to editorial/press use, and decorating a third-party admin tool's UI is a
  different context that may or may not qualify.
- Every time that tile renders, your Unraid box makes a live request to the
  manufacturer's server. The plugin sets `referrerpolicy="no-referrer"` so it doesn't
  leak which page/site made the request, but the request itself (and your server's IP)
  still happens - a consideration for what's otherwise a fully local admin tool.
- It depends on that URL staying valid long-term. Manufacturers reorganize their sites;
  a hotlinked URL can break without warning, whereas a local file won't.
- Most manufacturer press pages don't expose a stable, direct, hotlink-friendly image
  URL anyway (many sit behind JS-rendered brand portals or deliver a zip download
  instead) - so this option mainly helps when you've found one that does.

Disk Location never picks or hardcodes a manufacturer URL itself; this is entirely a
per-device choice you make.

## Where to find official logos

The links below are each manufacturer's own press/brand resources, current as of when this
was written - websites reorganize, so if a link is stale, search the manufacturer's site
for "press," "media kit," "newsroom," or "brand assets." A few (Toshiba, SK hynix,
Corsair) don't have a dedicated logo-download page; for those, the newsroom/press contact
is the starting point instead. Always check each site's own usage terms before adding a
file here - some require written permission for anything beyond editorial/news use, which
this plugin's display case may or may not fall under depending on how you read it.

| Slug             | Brand           | Official source                                                                 |
|------------------|-----------------|----------------------------------------------------------------------------------|
| westerndigital   | Western Digital | https://www.westerndigital.com/company/newsroom/brand-assets                    |
| seagate          | Seagate         | https://www.seagate.com/stories/media-assets/company-logos/                     |
| samsung          | Samsung         | https://www.samsung.com/us/about-us/brand-identity/logo/                        |
| kingston         | Kingston        | https://www.kingston.com/en/company/public-relations (assets via LoDA, linked from that page) |
| crucial / micron | Crucial/Micron  | https://www.micron.com/about/press/image-gallery/micron-logos                   |
| sandisk          | SanDisk         | https://www.sandisk.com/company/newsroom                                        |
| intel            | Intel           | https://newsroom.intel.com/press-hub                                            |
| toshiba          | Toshiba         | https://news.toshiba.com/press-releases/default.aspx (newsroom; contact PR for assets) |
| skhynix          | SK hynix        | https://news.skhynix.com/press-center/press-release/ (newsroom; contact PR for assets) |
| corsair          | Corsair         | https://www.corsair.com/newsroom/press-release/category/product-announcements (newsroom; contact PR for assets) |

Brands not listed here (ADATA, Patriot, Silicon Power, Transcend, PNY, Lexar, Team Group,
HGST, Hitachi, Fujitsu) aren't included because a quick search didn't turn up an obvious
official logo-download page - their own newsroom/investor-relations/contact pages are the
place to start looking.

**Note on Crucial:** Micron discontinued the Crucial consumer brand in February 2026 and
is winding down its operations. The `crucial` slug/detection is kept here anyway, since
existing Crucial-branded drives are already out in the field and will be for years - this
just means Crucial's own site is no longer the place to look for current-brand assets, and
any logo you add for it should be treated as identifying a legacy/discontinued brand.

## Disclaimer

All product names, logos, and brands referenced or displayed by this plugin are the
property of their respective owners. Use of any manufacturer name or logo is for drive
identification purposes only and does not imply any endorsement, sponsorship, or
affiliation. The Disk Location plugin and its author(s)/contributors are not affiliated
with, sponsored by, or endorsed by any of the manufacturers referenced.
