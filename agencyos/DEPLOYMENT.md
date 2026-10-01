# Deployment preparation

The complete SaaS specification is not implemented. This document describes infrastructure preparation for the current foundation, not commercial release approval.

Apache/cPanel: point the document root at `agencyos/public` and enable Laravel's supplied public/.htaccess rewriting. Do not expose the application root. Nginx: use public as root and `try_files $uri $uri/ /index.php?$query_string`, pass index.php to PHP-FPM, and deny hidden files. Use a supported PHP 8.3+ runtime and MySQL 8+. Configure HTTPS and secure cookies.

Install Composer production dependencies with `composer install --no-dev --optimize-autoloader`, build assets using `npm ci --ignore-scripts && npm run build`, privately configure .env, generate APP_KEY once, run migrations and permission seeders, and configure writable storage/bootstrap/cache. Retain least-privilege ownership rather than granting universal permissions.

Configure real SMTP, a queue worker and the scheduler. Restart workers after deploy. Verify production settings with `php artisan agencyos:doctor`. Cache configuration/routes/views using Laravel's supported deployment commands after environment values are settled. Never run browser tests against production; they create records.

Shared hosting may only support bounded cron-driven queue work. Confirm provider capabilities before relying on long-running workers. Later crawler/import/export/AI workloads may require VPS resources; those modules are not implemented yet.

Release prerequisites remain: MySQL 8 tests, all remaining feature phases, security/performance validation, backup/restore rehearsals, monitoring, complete installer and release documentation. SQLite/browser checks do not establish production readiness.
