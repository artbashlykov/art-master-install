=== ART Master Install ===
Contributors: artbashlykov
Tags: installer, github, art, catalog
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Install and manage ART WordPress extensions from public GitHub repositories.

== Description ==

ART Master Install is a hub for installing and updating other ART extensions from public GitHub repositories.

Open **Settings → Каталог Арта** to install or update ART Starter, ART Editor, ART Theme, and other catalog items in one click from GitHub releases.

== Installation ==

1. Upload the `art-master-install` folder to `/wp-content/plugins/` or install through the WordPress admin.
2. Activate ART Master Install on the Plugins screen.
3. Open **Settings → Каталог Арта** in the admin to view the catalog.

== Frequently Asked Questions ==

= What does ART Master Install do? =

It helps you install and update ART extensions from public GitHub releases without manual zip downloads.

= Which items are in the catalog? =

ART Starter, ART Editor, ART LMS, and ART Theme. More ART extensions will be added in future releases.

== Changelog ==

= 1.6.1 =
* Settings: auto-update for catalog and ART Master Install enabled by default.

= 1.6.0 =
* Каталог тем: установка, обновление и активация ART Theme из GitHub.
* Страница «Каталог Арта» (Настройки → Каталог Арта); кнопка «Проверить обновления» в шапке.
* Автообновления каталога: раз в сутки; проверяются плагины и темы.
* Настройка «Удалять все данные плагина при удалении плагина» (uninstall.php).
* Порядок плагинов в каталоге: ART Starter, ART LMS, ART Editor.
* Fix (1.5.1): предупреждение Plugin Check для автообновлений.
* Fix: дублирование уведомления «Настройки сохранены» на странице каталога.
* Fix: некорректный список плагинов после блока тем в шаблоне.

= 1.5.1 =
* Fix: Plugin Check warning for auto-update routines (use WordPress filter instead of direct option writes).

= 1.5.0 =
* Catalog: button «Проверить обновления» with AJAX refresh from GitHub.
* Settings: auto-update for catalog plugins and for ART Master Install.
* Scheduled catalog update checks twice daily when auto-update is enabled.
* ART Master Install: PUC auto-update field enabled on the Plugins screen.
* Fix: WordPress auto-update preference sync uses the correct site option format.

= 1.4.9 =
* Catalog page is only under Settings → Каталог Арта (removed duplicate Plugins submenu).
* Old plugins.php?page= URL redirects to Settings automatically.

= 1.4.8 =
* Fix: GitHub update checks send User-Agent and Accept headers (fixes HTTP 403 from GitHub API).

= 1.4.7 =
* Fix: admin menu and catalog page register before admin_menu (fixes missing menu item and access denied).

= 1.4.6 =
* Catalog page is under Plugins → Каталог Арта (primary) and Settings → Каталог Арта.
* Old options-general.php URL redirects to plugins.php automatically.

= 1.4.5 =
* Fix: catalog page under Settings uses manage_options again (fixes access denied on multisite and similar setups).
* Install/update buttons still require install_plugins and update_plugins.

= 1.4.4 =
* Self-update GitHub checks are throttled to once every 6 hours (Plugins / Updates screens).

= 1.4.3 =
* Fix: update checker initializes reliably in admin and checks GitHub on the Plugins / Updates screens.

= 1.4.2 =
* AJAX install/update queue: multiple plugins can be installed without page reload, with live status badges.

= 1.4.1 =
* GitHub release build script (`scripts/build-release.php`) includes vendor/ for self-updates.
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
