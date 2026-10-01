# SEO AgencyOS

**One Platform. Complete SEO Operations.**

This repository contains a tested initial implementation of the supplied commercial SaaS specification. It is **not the complete product and is not ready for commercial production deployment**. See [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md) for the exact implemented scope and remaining phases.

Implemented functionality uses real database records. No sample accounts, fake SEO scores, invented rankings, external API credentials, or decorative feature buttons are seeded.

## Current functionality

- Agency registration, login, logout, throttling, password reset, password hashing and session rotation.
- Tenant membership resolution, granular permission gates, resource policies, assigned-project restrictions, and composite tenant foreign keys.
- Platform agency overview, suspension/reactivation, configurable client expiry thresholds.
- Agency settings, real dashboard counts, append-only application activity trail.
- Client, project, and website CRUD with validation, search, filters, pagination, soft removal, and team assignments.
- Agency team creation, role changes and access revocation.
- Independent client SEO plans and subscriptions, expiry/progress display, grace periods, renewal, extension, plan changes, suspension and cancellation.
- Real invoices, partial/manual payments, outstanding balances, renewal history, deduplicated in-app and queued email reminders.
- Integrated SEO tool hub with 43 tools/workspaces, queued crawler/technical analyses, bounded keyword processing and CSV/XLSX imports/exports.
- Manual/imported ranking history, backlink records and queued permitted-source verification, competitor comparisons and saved snapshot change detection.
- Audit findings to deduplicated tasks, manager review, report snapshots and queued private PDF generation, scheduled monitoring alerts.
- Optional encrypted DeepSeek configuration and queued recommendations, usage limits, configurable scoring/crawler settings and separate platform tool plans.

See [SEO_TOOLS.md](SEO_TOOLS.md) for the catalog and tool guides. The remaining 50-capability requirements, client portal, aggregate reports, paid SaaS lifecycle and production validation are tracked in [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md).

## Local installation

Use PHP 8.3+ and Composer. Node 22.12+ builds frontend assets. MySQL 8+ is the intended production database; SQLite is supported for isolated development/testing.

```sh
composer install
cp .env.example .env
# Configure .env privately. For local SQLite use the settings in INSTALLATION.md.
php artisan key:generate
php artisan migrate --seed
npm ci --ignore-scripts
npm run build
php artisan agencyos:doctor
php artisan agencyos:admin
php artisan serve
```

On this Windows workspace, PHP 8.4 is installed locally at `../.tools/php/php.exe`; XAMPP's PHP 8.2 does not meet the application requirement. Use the local executable explicitly for Artisan/Composer. The local built-in PHP HTTP server stalled during verification; browser tests use a loopback-only CGI transport instead. A temporary development preview can run with `node tests/Browser/server.mjs` at `http://127.0.0.1:8099` using the private local `.env`. This is a test/development transport, not a production web server.

Register an agency at `/register`. Create a Super Admin through the interactive `agencyos:admin` command; no default admin password exists.

## Verification

```sh
php artisan test
```

For Windows browser verification with installed Chrome:

```powershell
powershell -File scripts/test-browser.ps1
```

Browser verification creates clearly named Browser Test records in `database/browser.sqlite`, separate from the application's database. The PHP feature suite uses a disposable in-memory SQLite database. CI configuration includes a MySQL 8 job; that job has not been executed in this workspace.

Read [INSTALLATION.md](INSTALLATION.md), [ARCHITECTURE.md](ARCHITECTURE.md), [DATABASE.md](DATABASE.md), [SECURITY.md](SECURITY.md), and [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md) before extending the implementation.
