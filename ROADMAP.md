# Roadmap

This document tracks where Disk Location is headed across three phases: finishing out
the current PHP codebase, putting it into maintenance mode, and eventually rebuilding on
Unraid's modern plugin architecture. It's a living document - phases and their contents
may shift as work progresses and priorities change.

## Phase 1: Finish out the current PHP codebase (in progress)

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
- [ ] REST/JSON status endpoint, single static token auth (in progress) - for tools like
      Home Assistant, Grafana, or Homepage to poll current disk/tray/SMART state without
      needing an Unraid webGUI session
- [ ] SMART history graphing - currently blocked on the fact that no history is
      retained at all today (`cronjob.php` overwrites one config file on every scan).
      Needs its own short design pass before implementation: what to store, at what
      granularity, how it gets pruned so it doesn't grow forever, and how existing
      installs (starting from zero history) experience it
- [ ] Split the monolithic files (`functions.php`, `cronjob.php`, `page_config.php`,
      `page_system.php`, etc.) into focused modules. Deliberately last: it's an
      internal-only change with no user-facing benefit and real regression risk (no
      live Unraid instance in this workflow to catch mistakes), so it makes more sense
      to refactor the code once its shape has settled from the items above, rather than
      refactor a moving target and then have to redo part of it anyway

Once everything above lands, the PHP codebase is considered feature-complete for its
architecture.

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
deeply with Unraid going forward, and a rewrite on that foundation would likely obsolete
the need for Phase 1's custom token-based REST endpoint entirely - consumers could query
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
