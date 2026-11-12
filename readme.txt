=== PixelShinobi Troubleshooting Finder ===
Contributors: pixelshinobi
Tags: troubleshooting, plugin conflict, health check, debug, support
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find which plugin breaks your site — automatically. Troubleshooting only you can see, and a search that pinpoints the culprit in a few checks.

== Description ==

Your site is broken after an update — a white screen of death, a fatal error message, or a 500 error where a page used to be — and the advice is always the same: deactivate all your plugins, then turn them back on one by one until it breaks again. On a site with 20 or 30 plugins, that is an afternoon of tedious clicking — on your live site.

PixelShinobi Troubleshooting Finder does that search for you, and does it safely:

**Troubleshooting mode that only affects you.** PixelShinobi Troubleshooting Finder turns plugins off *for your browser only*, using a private session. Your visitors keep seeing the real site, exactly as it is now. The mode switches itself off after an hour, there is always an exit button in the admin bar, and a break-glass link ends it even if wp-admin itself is broken. You cannot get stuck.

**An automated search (bisect), not one-by-one testing.** Tell PixelShinobi Troubleshooting Finder which page breaks, and it tests half your plugins at a time — the way a binary search works. Twenty plugins take at most about 8 checks instead of 20 rounds. You watch each step happen, and the search survives page reloads and slow hosting because it runs one small step per request.

**A plain-English verdict.** Every search ends with one honest answer:

* *This plugin is the problem* — proven by a test with that plugin active on its own.
* *It is not a plugin* — the problem happens even with everything off, so look at the theme or the server.
* *It needs a combination* — no single plugin is to blame; PixelShinobi Troubleshooting Finder shows the smallest set of plugins that breaks the page together.
* *Could not reproduce* — the page worked with everything on, so the problem is intermittent or already fixed.

**Debug info that is safe to share.** One click builds the bundle every support forum asks for — WordPress and PHP versions, theme, active plugins — with email addresses, file paths, IP addresses and tokens already removed. Copy it straight to your clipboard, or download it as a text file, and paste it into a support thread.

PixelShinobi Troubleshooting Finder never changes your real plugin list, never switches your live theme, and never phones home. Everything happens on your own site.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/pixelshinobi-troubleshooting-finder/`, or install it through the WordPress plugins screen.
2. Activate it through the 'Plugins' screen.
3. Go to **Tools → PixelShinobi Troubleshooting Finder** and either start troubleshooting mode or run the automated search.

== Frequently Asked Questions ==

= A plugin update broke my site. Where do I start? =

That is exactly what this plugin is for. As long as wp-admin still loads, run the automated search from **Tools → PixelShinobi Troubleshooting Finder**: it tests your plugins in halves, in a session only you can see, and ends with a verdict naming the culprit — or telling you honestly that the problem is not a plugin at all. If the white screen or error only shows on one page, give the search that page's address, and name a piece of text a healthy page always shows if the broken page still loads but loses content.

= Will my visitors see anything while I troubleshoot? =

No. Troubleshooting applies only to requests carrying your private session cookie — in practice, only your browser. Visitors always get the real site with all plugins active, and PixelShinobi Troubleshooting Finder never writes your real active-plugins option.

= What if wp-admin breaks while troubleshooting mode is on? =

You have three ways out: the exit button on the PixelShinobi Troubleshooting Finder page and in the admin bar, a break-glass exit link shown on the page (bookmark it before you start, if you like), and automatic expiry after one hour. Deleting the `conflict-radar-troubleshoot.php` file from `wp-content/mu-plugins` also ends the mode immediately.

= What if the search cannot find a culprit? =

Then it says so, honestly. If the problem happens with every plugin off, it is not a plugin conflict. If it only happens when several plugins are active together, PixelShinobi Troubleshooting Finder reports the smallest combination it saw breaking the page instead of guessing one plugin to blame.

= Does the search test my theme too? =

The automated search always runs against the default WordPress theme, so a theme problem cannot masquerade as a plugin problem. In manual troubleshooting mode, switching to the default theme is a checkbox you control.

= Does PixelShinobi Troubleshooting Finder collect any data? =

No. There are no accounts, no tracking and no calls to outside servers. The test requests the search makes are loopback requests to your own site.

== Screenshots ==

1. The PixelShinobi Troubleshooting Finder page in Tools: run the automated search, use manual troubleshooting mode, or grab debug info.
2. A finished search: the verdict names the culprit, explains the next step, and lists every test the search ran.
3. Troubleshooting mode is on: the admin bar keeps the exit one click away.

== Changelog ==

= 0.1.1 =
* Debug info now appears right on the page with a copy-to-clipboard button, so you can paste it into a support thread without downloading the file first (the download is still there).
* Clearer wording in the description and a new FAQ entry for sites broken by a plugin update.

= 0.1.0 =
* First release: session-only troubleshooting mode, the automated plugin-conflict search, plain-English verdicts, and the privacy-redacted debug bundle.

== Upgrade Notice ==

= 0.1.1 =
Adds a copy-to-clipboard button for the debug bundle, shown right on the page; the search and troubleshooting mode are unchanged.

= 0.1.0 =
First release.
