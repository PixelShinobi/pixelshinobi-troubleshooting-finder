# Conflict Radar – Troubleshooting & Plugin Conflict Finder

A WordPress plugin that finds which plugin breaks your site. Instead of the manual routine — turn everything off, re-enable plugins one by one until the break comes back — Conflict Radar runs a **bisect**: it tests halves of your plugin list in a troubleshooting session only you can see, and narrows down to the culprit in a handful of checks.

**Status:** milestone 1 (the bisect engine) is done. Milestone 2 turns it into the WordPress plugin. v1 targets the WordPress.org directory after that.

- [`PLAN.md`](PLAN.md): why this, our angle, v1 scope, milestones and the numbers that decide what's next
- [`CLAUDE.md`](CLAUDE.md): instructions for Claude Code sessions working in this repo

## How the bisect works

You describe the symptom as a check: a page on your site that currently fails (a fatal error, an HTTP 500, missing content). The engine then asks a series of yes/no questions — *does the page still fail when only these plugins are active?* — and plans each question as a binary search:

1. **Everything on:** the page must fail, or there is nothing to find (*not reproducible*).
2. **Everything off:** if the page still fails with every candidate plugin off, no plugin in the list is the cause (*not a plugin* — look at the theme or the hosting).
3. **Halves:** with half the suspects on, a failure puts the culprit in that half; no failure puts it in the other half. Each answer at least halves the suspects.
4. **One left:** the last suspect is only blamed if a test with it alone on the page has actually failed. If it can't fail alone, the break needs a *combination* of plugins, and the report says so (*interaction suspected*) instead of blaming an innocent plugin.

Twenty plugins take at most 8 checks this way, instead of up to 20 manual ones — and the session is scoped to your browser, so your visitors keep seeing the real site the whole time.

## Development

You need PHP 7.4 or later and Composer.

```bash
composer install
composer test   # PHPUnit
composer lint   # WordPress Coding Standards + PHP 7.4 compatibility
```

The engine in `src/Bisect/` is pure PHP with no WordPress dependencies: `BisectSession` plans the search (its state serializes to a plain array, so a search can pause and resume between requests), `BisectRunner` drives it against a caller-supplied check, and `BisectConclusion` is the verdict.

## License

GPLv2 or later.
