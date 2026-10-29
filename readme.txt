=== Conflict Radar – Troubleshooting & Conflict Finder ===
Contributors: pixelshinobi
Tags: troubleshooting, plugin conflict, health check, debug, support
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find which plugin breaks your site — automatically. Troubleshooting only you can see, and a search that pinpoints the culprit in a few checks.

== Description ==

Your site broke after an update, and the advice is always the same: deactivate all your plugins, then turn them back on one by one until it breaks again. On a site with 20 or 30 plugins, that is an afternoon of tedious clicking — on your live site.

Conflict Radar does that search for you, and does it safely:

**Troubleshooting mode that only affects you.** Conflict Radar turns plugins off *for your browser only*, using a private session. Your visitors keep seeing the real site, exactly as it is now. The mode switches itself off after an hour, there is always an exit button in the admin bar, and a break-glass link ends it even if wp-admin itself is broken. You cannot get stuck.

**An automated search (bisect), not one-by-one testing.** Tell Conflict Radar which page breaks, and it tests half your plugins at a time — the way a binary search works. Twenty plugins take at most about 8 checks instead of 20 rounds. You watch each step happen, and the search survives page reloads and slow hosting because it runs one small step per request.

**A plain-English verdict.** Every search ends with one honest answer:

* *This plugin is the problem* — proven by a test with that plugin active on its own.
* *It is not a plugin* — the problem happens even with everything off, so look at the theme or the server.
* *It needs a combination* — no single plugin is to blame; Conflict Radar shows the smallest set of plugins that breaks the page together.
* *Could not reproduce* — the page worked with everything on, so the problem is intermittent or already fixed.

**Debug info that is safe to share.** One click builds the bundle every support forum asks for — WordPress and PHP versions, theme, active plugins — with email addresses, file paths, IP addresses and tokens already removed, ready to paste into a support thread.

Conflict Radar never changes your real plugin list, never switches your live theme, and never phones home. Everything happens on your own site.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/conflict-radar/`, or install it through the WordPress plugins screen.
2. Activate it through the 'Plugins' screen.
3. Go to **Tools → Conflict Radar** and either start troubleshooting mode or run the automated search.

== Frequently Asked Questions ==

= Will my visitors see anything while I troubleshoot? =

No. Troubleshooting applies only to requests carrying your private session cookie — in practice, only your browser. Visitors always get the real site with all plugins active, and Conflict Radar never writes your real active-plugins option.

= What if wp-admin breaks while troubleshooting mode is on? =

You have three ways out: the exit button on the Conflict Radar page and in the admin bar, a break-glass exit link shown on the page (bookmark it before you start, if you like), and automatic expiry after one hour. Deleting the `conflict-radar-troubleshoot.php` file from `wp-content/mu-plugins` also ends the mode immediately.

= What if the search cannot find a culprit? =

Then it says so, honestly. If the problem happens with every plugin off, it is not a plugin conflict. If it only happens when several plugins are active together, Conflict Radar reports the smallest combination it saw breaking the page instead of guessing one plugin to blame.

= Does the search test my theme too? =

The automated search always runs against the default WordPress theme, so a theme problem cannot masquerade as a plugin problem. In manual troubleshooting mode, switching to the default theme is a checkbox you control.

= Does Conflict Radar collect any data? =

No. There are no accounts, no tracking and no calls to outside servers. The test requests the search makes are loopback requests to your own site.

== Screenshots ==

1. The Conflict Radar page in Tools: run the automated search, use manual troubleshooting mode, or grab debug info.
2. A finished search: the verdict names the culprit, explains the next step, and lists every test the search ran.
3. Troubleshooting mode is on: the admin bar keeps the exit one click away.

== Changelog ==

= 0.1.0 =
* First release: session-only troubleshooting mode, the automated plugin-conflict search, plain-English verdicts, and the privacy-redacted debug bundle.

== Upgrade Notice ==

= 0.1.0 =
First release.
