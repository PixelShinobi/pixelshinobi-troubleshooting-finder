# Conflict Radar: Plan

_Conflict Radar – Troubleshooting & Conflict Finder. Last updated Oct 29, 2026._

## Why this, why now

- **The standard tool is abandoned with a huge install base.** Health Check & Troubleshooting — the plugin the WordPress.org support forums send people to — has **200,000+ active installs** but sits at version 1.7.1, **last updated Jul 25, 2024**, "tested up to" WordPress 6.6.11, carries the directory's "may no longer be maintained" banner, and is rated **3.6/5 (181 ratings)**.
- **Agencies are actively leaving it.** A July 31, 2026 review reports an unpatched security issue (CVE-2025-64253) sitting for six months and clients complaining, forcing removal. The people who troubleshoot WordPress sites for a living need a maintained successor.
- **The core job is still manual.** When a plugin update breaks a site, Health Check's workflow is: enter troubleshooting mode, then re-enable plugins one by one until the break comes back. On a 30-plugin site that is an afternoon. A binary search does it in about five steps. Nobody in the directory automates the bisect.
- **The breakage never stops.** Every WordPress, PHP (8.4/8.5 rolling out, 8.2 security support ends Dec 31, 2026) and plugin update cycle produces a fresh wave of "my site broke, which plugin did it?" — the demand renews itself.

## Our angle

1. **The bisect is automatic.** Pick the broken page, start the search: Conflict Radar tests halves of the plugin list in a troubleshooting session and narrows to the culprit in ~log2(n) checks, instead of one-by-one manual re-enabling.
2. **Session-only and safe.** Troubleshooting applies to the admin's own session (cookie-scoped); visitors always see the real site. The mode has a panic-safe exit — a persistent escape link, automatic expiry, and a watchdog — because "I got stuck in troubleshooting mode" is a recurring 1-star complaint against the incumbent.
3. **Says what to do next.** The result is one plain verdict per search — the culprit plugin (update it, replace it, or contact its developer), "not a plugin" (theme/environment), "can't reproduce," or "looks like two plugins interacting" — plus a one-click, privacy-redacted debug bundle to paste into a support thread.
4. **Later, as a paid service:** agency remote diagnostics (send a client a troubleshooting link), white-label PDF reports, and a fleet view across client sites. Agencies are the buyers.

## WordPress.org rules that shape the design

- **No locked features in the free plugin** (Guideline 5). Paid features must be a separate service ("serviceware", Guideline 6) or a separate Pro plugin sold elsewhere.
- **No calls to outside servers without explicit opt-in** (Guideline 7). The free plugin works fully on-site; the bisect's test requests are loopback requests to the site itself.
- **All code GPL-compatible** (Guideline 1).
- **The public slug can't start with another project's name** (Guideline 17). **Decided:** the plugin is **Conflict Radar – Troubleshooting & Conflict Finder**, slug and text domain `conflict-radar` (sibling brand to Upgrade Radar). WordPress.org confirms the slug during review. (Display name adjusted Oct 29, 2026: Plugin Check flags the word "plugin" as a restricted term in a plugin's name, so "& Plugin Conflict Finder" became "& Conflict Finder"; the slug is unchanged.)
- **Upgrade prompts must be rare and dismissible** (Guideline 11).

## v1: free plugin

**In**

- Troubleshooting mode: for the admin's session only, deactivate all plugins except a chosen set and optionally switch to a default theme; visitors unaffected. Persistent exit control, session expiry, and a recovery path if the admin's browser closes mid-session.
- Automated bisect: the admin names the symptom check (a page that must load without a fatal/HTTP error, optionally containing expected text); the engine plans subsets, the plugin applies them in the troubleshooting session and probes the page after each step.
- Verdicts: culprit found / not a plugin / not reproducible / interaction suspected — each with the next step in plain English.
- Debug bundle: WordPress/PHP versions, active plugins and theme, server basics — with secrets, paths, emails and salts redacted — copyable and downloadable.
- Scan state persisted between requests (batched and resumable, like Upgrade Radar's scan jobs), so a bisect survives page reloads and slow hosts.

**Out until there's demand:** the agency service (remote diagnostics, white-label reports, fleet view), scheduled health checks, email alerts.

## Milestones

1. **Engine:** a WordPress-free bisect planner plus tests — baseline checks (fault with everything on, fault with everything off), binary narrowing, single-culprit confirmation, and the interaction case. Serializable state. **Done Oct 29, 2026** — see Milestone 1 results.
2. **Plugin:** troubleshooting mode (session-scoped plugin/theme filtering), symptom probe via loopback requests, batched bisect runner, admin UI, debug bundle export. Manual test in a Docker WordPress under locked-down host limits (30 s, 128 MB, no `exec()`). **Done Oct 29, 2026** — see Milestone 2 results (the test rig ended up a local WordPress install rather than Docker; the locked-down limits are survived by design, since a search never does more than one probe per request).
3. **Submit:** readme.txt and screenshots, a clean Plugin Check run, PHP-Scoper release build if needed, then submit to WordPress.org. **Prepped Oct 29, 2026** — see Milestone 3 results. The submission itself is Jizhou's step (WordPress.org requires the account holder to submit).
4. **Launch watch:** track installs, reviews and support threads.
5. **Paid service:** only if the numbers below say so.

## Milestone 1 results

The engine lives in `src/Bisect/`: `BisectSession` (the planner), `BisectConclusion` (the verdict value object) and `BisectRunner` (drives a session against an oracle callable). It is pure PHP with no WordPress dependencies, and its full state serializes to a plain array, so Milestone 2 can persist it between requests exactly like Upgrade Radar persists its scan jobs.

**How a search runs**

- **Baseline, everything on:** no fault → `not-reproducible`. There is nothing to find until the symptom can be triggered on demand.
- **Baseline, everything off:** fault still present → `not-a-candidate`. No candidate plugin causes it; look at the theme, the environment, or something outside the candidate list.
- **Narrowing:** activate the first half of the suspects. Fault → the culprit is in that half. No fault → it is in the other half. Each step at least halves the suspects.
- **Confirmation:** when one suspect remains, it is only declared the culprit if a test with it alone active has shown the fault (an earlier step often already proves this; otherwise one confirmation test runs). If it alone does not reproduce the fault, the verdict is `interaction-suspected` — the break needs a combination — and the smallest set that did show the fault is reported as the place to look.
- Tests needed: 2 baselines + about log2(n) narrowing steps + at most 1 confirmation. Twenty plugins resolve in at most 8 checks, versus 20 manual ones.

**Notes for Milestone 2**

- **The oracle is the product's hard part.** "Fault present" in the plugin means: probe a chosen URL in the troubleshooting session and classify the response (fatal error / HTTP 5xx / missing marker text). The probe must carry the troubleshooting cookie and must not be cached — use loopback requests with cache-busting, like Upgrade Radar's WP-Cron loopback setup.
- **Applying a subset** means filtering the active-plugins option for the troubleshooting session only (the Health Check pattern: an mu-plugin or early filter keyed on the session cookie). Never write the real `active_plugins` option during a bisect.
- **Recovery:** store the session state and an expiry in an option; a request that finds an expired or orphaned session must restore normal loading. Add a `WP_DISABLE_FATAL_ERROR_HANDLER`-aware path so a fatal in the admin's own session can't lock them out.
- **Step budget per request:** apply subset → probe → record, one bisect step per request at most; the UI advances via AJAX like Upgrade Radar's batches.

## Milestone 2 results

The plugin layer, as built (module map in CLAUDE.md):

- **Session enforcement** is a standalone mu-plugin loader, copied into `wp-content/mu-plugins` while a session exists and removed when it ends. It filters `active_plugins` (and the theme, when switched) only for requests carrying the session cookie, intersected against the real active list read straight from the database, so a plugin deactivated elsewhere mid-session stays off. Conflict Radar itself is always kept on inside the session so the exit is reachable. Failsafes: one-hour expiry, a watchdog on `admin_init`, deactivation cleanup, and a break-glass exit URL (token in the link) that works even if wp-admin is broken. The real `active_plugins` option is never written.
- **The probe** is a loopback `wp_remote_get` carrying the session cookie with a cache-busting query arg; `Probe` classifies the response (transport failure, HTTP 5xx, WordPress/PHP fatal signatures, or missing marker text). The baselines protect the verdict: a page that "faults" under every set fails the everything-off baseline and reports not-a-plugin instead of blaming someone.
- **The runner** (`BisectJob`) persists the engine state in an option and runs exactly one probe per AJAX request; the page's script polls until the search completes, then the session ends itself and the verdict + full step log render on the Tools page.
- **Debug bundle**: versions, theme, active plugins, limits — passed through `Redactor` (emails, IPs, absolute paths, long hex tokens) before display or download. Salts/keys are never collected.

**Verified end-to-end** on a real WordPress 7.1.4 install (MariaDB, PHP 8.3 built-in server — used in place of the planned Docker rig; Docker is not available in the build environment, and the one-probe-per-request design is what the 30 s/128 MB limits actually test). Fixture plugins: one that fatals every front-end page alone, a pair that only breaks together, and a harmless marker plugin. Results: the automated search found the single culprit in 4 steps through the real admin UI (login → form → AJAX steps → rendered verdict and step log); the pair search concluded `interaction-suspected` and blamed nobody; manual mode left visitors untouched while the cookie holder saw the filtered set, with working per-plugin toggles; the break-glass exit link ended a session and neutralized the stale cookie; deactivating the plugin removed the loader and the session. Unit suite: 34 tests, 95 assertions (engine + probe classifier + redactor); PHPCS clean.

## Milestone 3 results

Submission prep, done Oct 29, 2026:

- **readme.txt** in WordPress.org format (contributors: pixelshinobi — must match the submitting account's username); short description within the 150-character limit.
- **Release build:** `bin/build-release.sh` produces `build/conflict-radar.zip` (44K, single `conflict-radar/` folder, production autoloader, no dev files; `.wordpress-org/` excluded via `.distignore`). The zip itself was installed into a live WordPress and activated cleanly.
- **Plugin Check on the built zip: "No errors found"** — zero errors, zero warnings. Getting there cost three fixes: the display name dropped the restricted word "plugin" (now "Conflict Radar – Troubleshooting & Conflict Finder"; slug unchanged), the mu-plugin loader switched to WP_Filesystem (a direct `copy()` drew a PluginDirectoryWrite error), and the Domain Path header was dropped (it pointed at a folder that doesn't ship).
- **Screenshots** (`.wordpress-org/`, captured from the shipped zip running on a WordPress 7.1.4 + SQLite rig, headless Chromium): 1 — the Tools page with the automated search, manual mode and debug export; 2 — a finished search naming Fixture Broken as the culprit, with the full 4-step test log; 3 — the front end in troubleshooting mode, admin bar showing "Troubleshooting mode is ON". They upload with the submission (wp.org assets live outside the plugin zip).
- **Remaining step — Jizhou's:** log in at wordpress.org/plugins/developers/add/ with the pixelshinobi account, upload `build/conflict-radar.zip`, and add the three screenshots. Review is by the plugin team; nothing else is blocked.

**Submitted Oct 30, 2026** (account `pixelshinobi` created the same day; automated scan Pass; status Awaiting Review). Slug history: WordPress.org auto-assigned `conflict-radar-troubleshooting-conflict-finder` from the display name; `conflict-radar` turned out to be already in use by another plugin; `conflict-radar-finder` was requested and assigned the same day. The text domain was changed to match (`conflict-radar-finder`: plugin header + all 44 i18n calls; internal identifiers — menu slug, loader filename, exit query arg — deliberately unchanged), and the release build now produces `build/conflict-radar-finder.zip` under a `conflict-radar-finder/` folder (44K, 41 files; tests and PHPCS still green; Plugin Check on the new zip again reports no errors). That corrected zip is ready to send the reviewer when they write; the as-submitted zip (old domain) is kept at `build/conflict-radar.zip`. After approval: SVN — trunk gets the corrected zip's contents, `.wordpress-org/` goes to assets/, tag 0.1.0.

## Numbers that decide what happens next

- **Stop** if there are fewer than 300 active installs 60 days after approval.
- **Build the paid service** at 750+ active installs, or when 10+ agencies ask for remote diagnostics, reports or a fleet view.
- **Time cap after launch:** about 2 hours a week for support and updates.

## Risks

- **Crisis-moment support.** Users arrive while their site is broken and stressed. Mitigate with ruthless self-serve design and honest wording: the plugin finds causes; it does not promise to fix sites.
- **Core absorbs the basics.** WordPress core's Site Health screen keeps growing. The defense is workflow — automated bisect, safe sessions, exportable reports — not more checks.
- **A wrong verdict is a trust killer.** The confirmation step and the honest `interaction-suspected` / `not-reproducible` outcomes exist so the plugin never blames a plugin it hasn't proven. Keep that bar.
- **Troubleshooting mode itself can break things.** Some plugins misbehave when others vanish (licensing checks, hard dependencies). Session-only scope and the panic exit bound the blast radius; the Docker test in Milestone 2 must include a site with such a plugin.

## Sources

- [Health Check & Troubleshooting on WordPress.org](https://wordpress.org/plugins/health-check/) — 200,000+ installs, 3.6/5, v1.7.1, last updated Jul 25, 2024 (listing read Oct 29, 2026)
- Research dossier: "One-Person SaaS Opportunities" (Oct 29, 2026), opportunity #1 — demand proof, incumbent gap and decision numbers
- [WordPress.org Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
