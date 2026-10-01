# Security and current limits

Implemented controls: Laravel session authentication/CSRF, login and reset request throttling, 12-character mixed-case/numeric passwords, password hashing, session rotation, reset token flow, session invalidation on password reset, escaped Blade values, trusted tenant membership resolution, permission gates, resource policies, assignment restrictions, scoped validation, tenant foreign keys, private environment defaults and audit events.

Tests cover privilege escalation attempts, foreign tenant session/resource/parent IDs, missing tenant context, suspended/inactive access, employee assignment boundaries, billing access, cross-tenant foreign keys, XSS escaping and actual-browser CSRF rejection.

Only `public/` belongs under a web document root. Keep .env, source, storage logs, backups and vendor private. Use HTTPS, APP_DEBUG=false, encrypted/secure session cookies, a distinct production APP_KEY and least-privilege database credentials. The example environment contains no operational secrets.

Public registration currently permits creation of new agencies. Registration verification, anti-abuse provisioning, MFA, signed invitations, API token lifecycle, detailed security settings, private uploads, crawler SSRF protection, API secret storage, backup controls and full penetration testing remain outstanding. Website URLs are stored but are not fetched; no crawler or verification endpoint exists yet. Do not introduce fetches until SSRF/redirect/DNS-rebinding defenses are tested.

Shared global models such as ActivityLog require deliberate platform authorization or explicit agency filtering. Never introduce unscoped tenant queries into a user-facing route. Model event guards do not replace safe query construction; raw SQL and bulk writes must be reviewed separately.
