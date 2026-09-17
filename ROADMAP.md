# Roadmap

This document tracks where Disk Location is headed across three phases: finishing out
the current PHP codebase, putting it into maintenance mode, and eventually rebuilding on
Unraid's modern plugin architecture. It's a living document - phases and their contents
may shift as work progresses and priorities change.

## Phase 1: Finish out the current PHP codebase (complete)

The plugin's existing architecture (classic Unraid webGUI plugin, PHP, SQLite/flat-file
config) is stable and well-understood. Before starting anything new, we're finishing out
a set of planned improvements within that architecture:

- [x] Fix broken `pkill` quoting in the locate "stop" command
- [x] JSON export alongside the existing TSV export
- [x] GitHub Actions PHP lint CI
- [x] Shell-escaping hardening (`smart_controller_cmd`, device-failure notify command)
- [x] Mobile-responsive tray map (groups stack/scroll on narrow viewports)
- [x] Drive type icons (HDD/SSD/NVMe) and an optional manufacturer logo framework
      (auto-detect + manual override, including hotlinking a manufacturer-hosted URL) -
      no logos shipped in-repo; see `disklocation/pages/styles/manufacturers/README.md`
- [x] SMART history graphing - new purpose-built SQLite time series (temp, power-on
      hours, sector counts, wear level, overall status), one row per device per
      completed full SMART scan, with configurable retention/pruning, plus a new
      "Trends" tab charting it with Chart.js. Ships in two parts: storage/cron/pruning,
      then the charting UI once real data existed to look at
- [x] Split the monolithic files. `functions.php`'s 49-function grab-bag became six
      topic-focused files (`functions_core.php`, `functions_export.php`,
      `functions_smart.php`, `functions_zfs.php`, `functions_devices.php`,
      `functions_drive_brand.php`) behind a thin loader; `page_system.php`'s six
      backup/restore functions moved to `functions_backup.php`. `cronjob.php` and
      `page_config.php` were reviewed but left as-is - both are already single-purpose
      scripts, not grab-bags, so splitting either would have been unnecessary
      fragmentation rather than the focused modules this item was after. Pure move, no
      logic changes - verified by diffing extracted function bodies byte-for-byte
      against git history, and confirmed on real hardware after catching (and fixing) a
      function-hoisting-order regression that `php -l` alone couldn't catch, since it
      only checks syntax, not execution

Everything above has landed - the PHP codebase is feature-complete for its architecture.
Phase 2 (maintenance mode) begins now.

## Phase 2: PHP version enters maintenance mode

Once Phase 1 is done:

- No new features land in the PHP codebase.
- Bug fixes, security patches, and Unraid-version compatibility fixes continue to be
  accepted and released.
- This keeps the plugin fully working indefinitely for anyone who doesn't move to
  whatever comes next in Phase 3 - including anyone on Unraid versions older than 7.2,
  which can't run the plugin architecture Phase 3 depends on at all.

## Phase 3: Rewrite on NestJS/TypeScript/Node.js (native Unraid API plugin)

Starting with Unraid 7.2, Unraid ships a built-in GraphQL API (`unraid-api`) with a real
plugin architecture: plugins can register their own GraphQL resolvers, background jobs,
and WebGUI components, on NestJS/TypeScript/Node.js, with API keys/session
cookies/SSO-OIDC auth already built in. That's the modern, sanctioned way to integrate
deeply with Unraid going forward.

**REST/JSON status endpoint (moved here from Phase 1):** we scoped and partly built this
as a single-static-token PHP endpoint for tools like Home Assistant, Grafana, or Homepage
to poll current disk/tray/SMART state without an Unraid webGUI session. It turned out not
to be buildable that way: Unraid's own webGUI auth wall sits in front of every path under
the webGUI, including plugin script paths, and there's no supported way for a third-party
classic PHP plugin to carve out an exception - confirmed by testing directly against a
live instance (an unauthenticated request to an existing plugin export endpoint gets
redirected to the Unraid login page rather than reaching the PHP script at all). The only
precedent for that kind of nginx exception is Unraid's own core team patching their
bundled config for their own official Connect plugin - not a mechanism available to us,
and not something worth this plugin trying to replicate given the fragility (config gets
regenerated) and security responsibility involved. Unraid's native GraphQL API is
specifically built with its own supported auth (API keys) at a documented layer designed
for exactly this - the right home for real external-polling access, rather than a
workaround bolted onto the PHP-era plugin.

This would also obsolete the need for that custom token entirely - consumers could query
Disk Location's data straight through Unraid's own GraphQL surface, using auth Unraid
already provides.

This is a different technology stack from the rest of this codebase, not just a new
file in the existing one, so it's being scoped as its own project rather than folded
into ongoing PHP work. Requires Unraid 7.2+, so it won't replace the PHP version for
everyone immediately - both will coexist for a transition period, hence Phase 2.

Open questions to work through before writing code, rather than deciding blind:

- Full rewrite vs. gradual migration - e.g. start by wrapping the existing PHP
  config-reading logic behind a new GraphQL resolver, rather than reimplementing
  everything on day one
- How much of the tray-map/visual UI can carry over conceptually vs. needs to be
  rebuilt as native WebGUI components in the new framework
- Minimum supported Unraid version for the rewrite (7.2, or a later version once the
  plugin architecture has had more time to mature)
- Repo structure: same repo with both codebases side by side, or a separate
  repo/package for the rewrite

This phase doesn't have a start date yet - it begins once Phase 1 is done.
