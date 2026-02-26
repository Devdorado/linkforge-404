=== LinkForge 404 ===
Contributors: devdorado
Donate link: https://devdorado.com
Tags: 404, redirect, seo, broken links, url management
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enterprise-grade 404 management with async logging, multi-stage rerouting, and GDPR-compliant privacy-by-design.

== Description ==

**LinkForge 404** turns your WordPress 404 errors into seamless redirects. It intercepts every 404 request and runs it through a multi-stage rerouting cascade before logging it — keeping visitors on your site and protecting your SEO rankings.

= Key Features =

* **Zero-latency logging** — Async buffer via Redis/Memcached (flat-file fallback) with zero DB writes in the frontend lifecycle
* **Multi-stage rerouting** — Exact match → Regex patterns → Fuzzy matching (Phase 2) → AI-powered (Phase 3)
* **GDPR-compliant** — IP anonymization with HMAC-SHA256 hashing; WordPress Privacy Tools integration
* **Dashboard analytics** — At-a-glance stats, searchable 404 log, and one-click resolution
* **Server rules export** — Generate Apache .htaccess or Nginx rewrite configs from your redirects
* **WP-CLI support** — Full command-line management for developers and CI/CD pipelines
* **Rate limiting** — Configurable per-IP limits protect against bot floods
* **Garbage collection** — Automated log retention with batched deletion

= Rerouting Cascade =

1. **Exact Match** — O(log n) B-Tree indexed database lookup
2. **Regex Match** — PCRE patterns with capture group substitution
3. **Fuzzy Match** — Jaro-Winkler similarity scoring (Phase 2)
4. **AI Match** — OpenAI embeddings for semantic redirect suggestions (Phase 3)
5. **Log** — Aggregated miss recording if no match found

= Requirements =

* PHP 8.1+
* WordPress 6.4+
* MySQL 8.0+ / MariaDB 10.6+
* Redis or Memcached recommended for optimal async logging

== Installation ==

1. Upload `linkforge-404` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **LinkForge 404 → Settings** to configure.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. LinkForge 404 only runs on actual 404 responses and uses async logging to avoid any database writes during the frontend response lifecycle.

= What happens to my redirects if I deactivate the plugin? =

Your redirect rules and logs are preserved in the database. They will be restored when you reactivate the plugin. Data is only deleted when you fully uninstall (delete) the plugin.

= Is it compatible with other redirect plugins? =

LinkForge 404 hooks into WordPress at priority 999 on `template_redirect`, so it runs after most other plugins. Conflicts are unlikely but possible if another plugin also intercepts 404s at a high priority.

= Does it support multisite? =

Each site in a multisite network gets its own tables and settings. Network-wide management is planned for a future release.

== Support ==

* **Email:** support@devdorado.com
* **GitHub:** [Devdorado/linkforge-404](https://github.com/Devdorado/linkforge-404)
* **Website:** [devdorado.com/linkforge-404](https://devdorado.com/linkforge-404)

== Changelog ==

= 1.0.0 =
* Initial release
* Exact and regex redirect matching
* Async 404 logging (Redis + flat-file fallback)
* GDPR-compliant IP anonymization
* Admin dashboard with analytics
* WP-CLI integration
* Server rules export (Apache / Nginx)
* Configurable rate limiting and garbage collection

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Screenshots ==

1. Dashboard — Active redirects, hit counters, and 404 log at a glance.
2. Redirects — Manage rules with bulk actions and server export.
3. Settings — Logging, rate limiting, privacy, and AI configuration.
