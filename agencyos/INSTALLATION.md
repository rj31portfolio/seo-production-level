# Installation

This installs the current implementation, not the full requested commercial product.

## Prerequisites

PHP 8.3+ with ctype, curl, DOM, fileinfo, filter, GD (for report logos), hash, mbstring, OpenSSL, PCRE, PDO, session, tokenizer, XML and ZIP. Enable pdo_mysql for MySQL, or pdo_sqlite/sqlite3 for local tests. Composer 2, Node 22.12+ and npm build assets. Redis is optional; database-backed queues/cache/sessions are the defaults.

On Windows use `..\.tools\php\php.exe` for PHP commands in this workspace. The runtime is ignored by Git and must not be shipped as an application dependency. Install a supported runtime separately on deployment hosts.

## Environment

Copy `.env.example` to `.env` and configure it privately. Never place the project root under the public web root. Generate a unique APP_KEY and retain it in secure operational backups; do not reuse a development key.

Production example values in `.env.example` use MySQL and secure cookies. For **local** SQLite development, use:

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/agencyos/database/database.sqlite
SESSION_SECURE_COOKIE=false
MAIL_MAILER=log
```

Create the empty SQLite file before migration. On Windows an absolute path such as `D:/Working/seo/agencyos/database/database.sqlite` works. Mail log mode writes development reset links to private logs; it does not deliver email. Configure SMTP before expecting password-reset/reminder email delivery.

## Initialize

After configuring `.env`, run `composer install`, `php artisan agencyos:install --force`, `npm ci --ignore-scripts`, and `npm run build`. The installer creates a key only when missing, applies pending migrations, and seeds permissions. It preserves existing keys, accounts and agency records, and can be rerun. `--force` is required in production. It does not build assets or configure hosting services. `php artisan agencyos:doctor` checks the runtime without printing connection credentials. `php artisan agencyos:admin` interactively creates a platform administrator with a hidden password prompt. The permission seeder creates only roles and permissions; it creates no accounts or fabricated business data.

Visit `/register` to create a new agency owner and agency in one database transaction. Login at `/login`; platform administration is `/super-admin`.

Do not run destructive fresh/reset migrations on business databases. Existing migrations are versioned and applied using `php artisan migrate --force` during controlled deployments.

## Background processes

Configure the worker and scheduler explicitly as described in QUEUE.md and CRON.md. Neither executes automatically because the application has been installed. Use a real mail transport and a running queue worker for queued reminder emails.

## Live server setup

1. Create a MySQL 8 database and a dedicated database user. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain.example`, database credentials, SMTP credentials and `SESSION_SECURE_COOKIE=true` in your private `.env`.
2. Install production PHP dependencies with `composer install --no-dev --optimize-autoloader`. Run `php artisan agencyos:install --force`, then `npm ci --ignore-scripts` and `npm run build` (or upload assets built in CI).
3. Create the platform administrator using `php artisan agencyos:admin`. Register the agency at `/register`, then open **Agency settings** to upload your PNG/JPG logo, address, email and support contacts. In Super Admin, configure **AI provider settings**, test authentication, and enable AI when ready. API keys stay encrypted; branding settings do not contain credentials.
4. Point the HTTPS web server document root at `agencyos/public`. Use PHP-FPM with `try_files $uri $uri/ /index.php?$query_string` for Nginx, or enable Apache rewrite support. Only `storage` and `bootstrap/cache` need application write access. Keep `.env`, database files and private storage outside the document root.
5. Supervise `php artisan queue:work --queue=seo,default --sleep=3 --tries=1 --timeout=1800 --memory=384`. Add a cron entry running `cd /absolute/path/agencyos && php artisan schedule:run >> /dev/null 2>&1` every minute. Queue visibility/retry timing must exceed 1800 seconds; the database default is 1860. A worker listening only to `default` will not prepare reports.
6. Run `php artisan optimize` and `php artisan agencyos:doctor --production`. The production check fails for insecure app/session settings, development mail/queue drivers, missing assets, unapplied migrations, missing permissions/admin, or a database outside the supported MySQL baseline. A passing check does not prove SMTP delivery or worker/scheduler execution; verify those with a password reset and an actual queued report PDF.
7. Back up the database, private `storage/app/private` directory, and `APP_KEY` securely. Test restoration before launch. After later deployments, apply pending migrations, rebuild assets, optimize, and run `php artisan queue:restart` under the supervisor.

Branding logos stay in private storage; no public storage symlink is needed. New report snapshots embed their logo and contact information so HTML, portal reports and PDFs keep the saved identity after branding changes. Existing reports without branding remain readable; save a new report to apply new branding. PDFs repeat a compact logo/contact header and include page numbers.

Hosting configuration follows the [Laravel deployment guide](https://laravel.com/framework/docs/deployment). Commercial SaaS payment collection and other unfinished product requirements remain tracked in [IMPLEMENTATION_STATUS.md](IMPLEMENTATION_STATUS.md); these setup steps do not provision hosting or certify those features.
