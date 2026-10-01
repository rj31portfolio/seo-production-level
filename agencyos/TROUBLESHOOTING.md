# Troubleshooting

- Unsupported runtime: XAMPP PHP 8.2 cannot run Laravel 13. In this workspace use `..\.tools\php\php.exe`; run `php -v` on a deployment host before installing.
- Missing Vite manifest: run `npm ci --ignore-scripts` and `npm run build`. Do not configure mandatory third-party font fetching at runtime; current built assets include fonts locally.
- SQLite database missing: create an empty file and set an absolute DB_DATABASE path. Browser tests use a separate browser.sqlite file.
- 403 agency access: verify the authenticated user's membership, selected agency session, active user/agency state, resource permission and project assignment. An expired client service period also blocks operational edits.
- 404 foreign tenant resource: intentional tenant-scoped resolution. Do not bypass scopes to fix it.
- Password email missing: local MAIL_MAILER=log writes reset notifications to private logs and does not deliver. Configure SMTP. Client reminder emails additionally need a running worker.
- Subscription reminder missing: check cron, timezone, configured thresholds, active service start, and queue failures. Reminders only fire on matching calendar-day thresholds; catch-up is not implemented.
- Local PHP HTTP server stalled: this host's downloaded PHP server did not bind a port during testing. The loopback CGI test transport in tests/Browser/server.mjs verified the real application; it is not a production server. Use supported Apache/PHP-FPM deployment infrastructure.
- Payment rejected: compare integer minor-unit amount to the unpaid balance and use a unique per-agency reference. Do not retry with fabricated payment data.
- Production status: consult IMPLEMENTATION_STATUS.md; missing features have not been hidden behind nonfunctional buttons.
