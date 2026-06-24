=== ART Master Install ===
Contributors: artbashlykov
Tags: installer, github, art, catalog
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Install and manage ART WordPress extensions from public GitHub repositories.

== Description ==

ART Master Install is a hub for installing and updating other ART extensions from public GitHub repositories.

Open **Settings → Плагины Арта** to install or update ART Starter, ART Editor, and other catalog items in one click from GitHub releases.

== Installation ==

1. Upload the `art-master-install` folder to `/wp-content/plugins/` or install through the WordPress admin.
2. Activate ART Master Install on the Plugins screen.
3. Open **Settings → Плагины Арта** in the admin to view the catalog.

== Frequently Asked Questions ==

= What does ART Master Install do? =

It helps you install and update ART extensions from public GitHub releases without manual zip downloads.

= Which items are in the catalog? =

ART Starter, ART Editor, and ART LMS. More ART extensions will be added in future releases.

== Changelog ==

= 1.4.3 =
* Fix: update checker initializes reliably in admin and checks GitHub on the Plugins / Updates screens.

= 1.4.2 =
* AJAX install/update queue: multiple plugins can be installed without page reload, with live status badges.

= 1.4.1 =
* GitHub release build script (`scripts/build-release.php`) includes vendor/ for self-updates.
* Admin menu and settings require `install_plugins` capability.
* Install/update actions validate catalog plugin state before running.

= 1.4.0 =
* Self-updates via GitHub releases (Plugin Update Checker).
* Auto-activate catalog plugins after install (optional setting, enabled by default).
* Inactive catalog items now show an update-available status.
* Smarter GitHub API cache (6 hours, stale fallback on API errors).
* Catalog: added ART LMS (`artbashlykov/art-lms`).

= 1.3.0 =
* Renamed slug to `art-master-install` for WordPress.org trademark compliance.
* Plugin Check fixes: Tested up to 7.0, admin notice sanitization, distignore.

= 1.2.0 =
* Catalog: added ART Editor (`artbashlykov/art-editor`).

= 1.1.0 =
* GitHub install and update flow for catalog items.
* Catalog: ART Starter.

= 1.0.0 =
* Initial scaffold: settings catalog page.
