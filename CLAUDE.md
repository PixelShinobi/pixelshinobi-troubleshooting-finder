# CLAUDE.md — working in this repo

Conflict Radar is a WordPress plugin that finds which plugin breaks a site, using an automated bisect in a session-scoped troubleshooting mode. Read `PLAN.md` before changing scope: it holds the angle, the v1 in/out list, the milestones and the decision numbers. This file holds the engineering rules.

## Commands

```bash
composer install   # dependencies (PHPUnit, WPCS) — PHP 7.4+ and Composer
composer test      # PHPUnit unit tests (tests/Unit)
composer lint      # PHPCS: WordPress Coding Standards + PHPCompatibilityWP (7.4-)
composer lint:fix  # PHPCBF autofix
```

Tests and lint must pass before a milestone commit. There is no CI yet; run both locally and say so in the commit/PR notes.

## Layout

- `conflict-radar.php` — plugin header and bootstrap. Milestone 1: autoloader only.
- `src/Bisect/` — the engine. **Pure PHP, no WordPress calls, no globals.** `BisectSession` (planner + serializable state), `BisectConclusion` (verdict), `BisectRunner` (drives a session against an oracle callable).
- `tests/Unit/` — PHPUnit tests, mirroring `src/` structure.
- Milestone 2 adds `src/Admin/` and `src/Jobs/` (names may change; update this file when they land).

## Rules

- **PHP 7.4 is the syntax ceiling** in `src/` and `tests/`: no enums, readonly, constructor promotion, union types, nullsafe operator, match expressions, or PHP 8-only functions (e.g. `str_contains`). `composer.json` pins `platform.php` to 7.4.33 on purpose — do not raise it.
- **The engine never executes anything and never touches the filesystem or the network.** It plans tests and records answers. WordPress-side effects (option filters, loopback probes) belong to the Milestone 2 layers that call it.
- **Session state must stay serializable.** Everything `BisectSession` knows must round-trip through `toArray()` / `fromArray()` — Milestone 2 persists it in a WordPress option between requests. No closures or object graphs in state.
- **Never blame without proof.** A culprit verdict requires a recorded test where that plugin alone was active and the fault appeared. If the evidence says "combination", the verdict is `interaction-suspected`. Do not weaken this to make results look cleaner.
- **Troubleshooting is session-scoped, always.** Code must never write the real `active_plugins` option or switch the real theme for visitors. (Milestone 2 rule, recorded now because it shapes the design.)
- **Output escaping:** engine messages are plain text; anything printed in wp-admin gets escaped at the printing layer (`esc_html` / `esc_attr`), as in Upgrade Radar.
- **Annotations and comments** explain *why*, in the voice of the codebase: short, plain, no hype. Match PLAN.md/README.md tone.
- **Commits:** imperative subject line, body explaining what changed and why (see git history of Upgrade Radar for the style). One milestone step per commit where practical.

## Product facts not to drift from

- Plugin name: **Conflict Radar – Troubleshooting & Plugin Conflict Finder**; slug/text domain: `conflict-radar`.
- Verdict strings are API: `culprit-found`, `not-a-candidate`, `not-reproducible`, `interaction-suspected`, `no-candidates` (constants on `BisectConclusion`). Renaming one is a breaking change for stored Milestone 2 state.
- Decision numbers live in PLAN.md only. If they change, change them there, not in code or docs elsewhere.
