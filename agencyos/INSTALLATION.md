# Installation

This installs the current implementation, not the full requested commercial product.

## Prerequisites

PHP 8.3+ with ctype, curl, DOM, fileinfo, filter, hash, mbstring, OpenSSL, PCRE, PDO, session, tokenizer, XML and ZIP. Enable pdo_mysql for MySQL, or pdo_sqlite/sqlite3 for local tests. Composer 2, Node 22.12+ and npm build assets. Redis is optional; database-backed queues/cache/sessions are the defaults.

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

Run `composer install`, `php artisan key:generate`, `php artisan migrate --seed`, `npm ci --ignore-scripts`, and `npm run build`. `php artisan agencyos:doctor` checks the runtime without printing connection credentials. `php artisan agencyos:admin` interactively creates a platform administrator with a hidden password prompt. The permission seeder creates only roles and permissions; it creates no accounts or fabricated business data.

Visit `/register` to create a new agency owner and agency in one database transaction. Login at `/login`; platform administration is `/super-admin`.

Do not run destructive fresh/reset migrations on business databases. Existing migrations are versioned and applied using `php artisan migrate --force` during controlled deployments.

## Background processes

Configure the worker and scheduler explicitly as described in QUEUE.md and CRON.md. Neither executes automatically because the application has been installed. Use a real mail transport and a running queue worker for queued reminder emails.
