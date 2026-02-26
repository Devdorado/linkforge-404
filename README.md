<p align="center">
  <img src="https://devdorado.com/favicon.ico" alt="Devdorado" width="64" height="64" />
</p>

<h1 align="center">LinkForge 404</h1>

<p align="center">
  <strong>Enterprise-grade WordPress 404 Management</strong><br>
  Built by <a href="https://devdorado.com">Devdorado</a>
</p>

<p align="center">
  <a href="https://devdorado.com"><img src="https://img.shields.io/badge/Devdorado-devdorado.com-blue?style=flat-square" alt="Devdorado" /></a>
  <img src="https://img.shields.io/badge/version-1.0.0-green?style=flat-square" alt="Version" />
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.1+" />
  <img src="https://img.shields.io/badge/WordPress-6.4%2B-21759B?style=flat-square&logo=wordpress&logoColor=white" alt="WordPress 6.4+" />
  <img src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue?style=flat-square" alt="License" />
</p>

---

**LinkForge 404** turns your WordPress 404 errors into seamless redirects. It intercepts every 404 request and runs it through a multi-stage rerouting cascade before logging it — keeping visitors on your site and protecting your SEO rankings.

## Features

| Feature | Description |
|---|---|
| **Zero-latency logging** | Async buffer via Redis/Memcached (flat-file fallback) — zero DB writes in the frontend lifecycle |
| **Multi-stage rerouting** | Exact match → Regex patterns → Fuzzy matching (Phase 2) → AI-powered (Phase 3) |
| **GDPR-compliant** | IP anonymization with HMAC-SHA256 hashing; WordPress Privacy Tools integration |
| **Dashboard analytics** | At-a-glance stats, searchable 404 log, and one-click resolution |
| **Server rules export** | Generate Apache `.htaccess` or Nginx rewrite configs from your redirects |
| **WP-CLI support** | Full command-line management for developers and CI/CD pipelines |
| **Rate limiting** | Configurable per-IP limits protect against bot floods |
| **Garbage collection** | Automated log retention with batched deletion |

## Rerouting Cascade

```
404 Request
    │
    ├─ 1. Exact Match ──── O(log n) B-Tree indexed DB lookup
    │
    ├─ 2. Regex Match ──── PCRE patterns with capture group substitution
    │
    ├─ 3. Fuzzy Match ──── Jaro-Winkler similarity scoring (Phase 2)
    │
    ├─ 4. AI Match ──────── OpenAI embeddings for semantic suggestions (Phase 3)
    │
    └─ 5. Log ───────────── Aggregated miss recording (async buffer)
```

## Installation

### Via ZIP Upload (recommended)

1. Download the latest release from [GitHub Releases](https://github.com/Devdorado/linkforge-404/releases)
2. In WordPress: **Plugins → Add New Plugin → Upload Plugin**
3. Upload the ZIP, install, and activate
4. Navigate to **LinkForge 404 → Settings** to configure

### Via Composer

```bash
composer require devdorado/linkforge-404
```

## Requirements

- PHP 8.1+
- WordPress 6.4+
- MySQL 8.0+ / MariaDB 10.6+
- Redis or Memcached recommended for optimal async logging

## WP-CLI Commands

```bash
# Manage redirects
wp linkforge redirect list
wp linkforge redirect add /old-page /new-page --status=301
wp linkforge redirect add '^/blog/(\d+)' '/posts/$1' --type=regex
wp linkforge redirect delete 42

# Operations
wp linkforge flush          # Force buffer flush to DB
wp linkforge gc             # Run garbage collection
wp linkforge stats          # Show plugin statistics

# Export server rules
wp linkforge export --format=apache
wp linkforge export --format=nginx --output=/tmp/redirects.conf
```

## Architecture

```
linkforge-404.php          ← Bootstrap, autoloader, activation hooks
├── includes/
│   ├── class-linkforge-core.php            ← Central orchestrator, REST API
│   ├── class-linkforge-interceptor.php     ← 404 intercept + rerouting cascade
│   ├── class-linkforge-logger.php          ← Async logging (Redis + file fallback)
│   ├── class-linkforge-privacy.php         ← IP anonymization, GDPR integration
│   ├── class-linkforge-redirects.php       ← CRUD model + server rules export
│   ├── class-linkforge-garbage-collector.php ← Batched log retention cleanup
│   ├── class-linkforge-activator.php       ← DB tables, defaults, cron scheduling
│   └── class-linkforge-deactivator.php     ← Cron cleanup on deactivation
├── admin/
│   ├── class-linkforge-admin.php           ← Menus, settings, asset enqueueing
│   ├── views/                              ← Dashboard, Redirects, Settings pages
│   ├── css/linkforge-admin.css             ← Admin styles
│   └── js/linkforge-admin.js              ← REST API calls, bulk actions, UI logic
├── cli/
│   └── class-linkforge-cli.php             ← WP-CLI commands
└── uninstall.php                           ← Clean removal (tables + options)
```

## Privacy & GDPR

LinkForge 404 follows **Privacy-by-Design** principles:

- **IP addresses are never stored.** They are masked (last octet zeroed) and irreversibly hashed with HMAC-SHA256 before any persistence.
- Integrates with WordPress **Privacy Tools** (Export/Erase personal data).
- Configurable log retention with automated garbage collection.
- All processing happens server-side — no external API calls in Phase 1.

## Roadmap

- [x] **Phase 1** — Exact & Regex matching, async logging, admin dashboard, WP-CLI
- [ ] **Phase 2** — Fuzzy matching (Jaro-Winkler), broken link scanner on post save
- [ ] **Phase 3** — AI-powered semantic matching (OpenAI embeddings), multisite support

## Contributing

Contributions are welcome! Please open an issue or PR on [GitHub](https://github.com/Devdorado/linkforge-404).

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

<p align="center">
  Made with ❤️ by <a href="https://devdorado.com"><strong>Devdorado</strong></a>
</p>
