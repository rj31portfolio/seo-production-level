# Implementation status

The original request is preserved in `docs/REQUEST.md`. The full 14-phase specification is outstanding. This file distinguishes working code from requested work that has not been implemented.

| Phase | Implemented in this workspace | Outstanding work |
| --- | --- | --- |
| 1 Foundation | Laravel 13, authentication/password reset, relational agency memberships, shared roles/permissions, tenant context/scopes, policies/gates, platform agency dashboard, suspension, activity logging | Complete platform settings/management surfaces, email verification, dedicated REST token/API access, installer workflow, further security hardening and production validation |
| 2 Agency operations | Agency settings, employee management, client/project/website CRUD, assigned-project access, scoped parent validation and composite foreign keys | Client logo/private file uploads, project managers/goals metrics, live website ownership verification, full onboarding wizard, large searchable relationship pickers (current form choices capped at 500) |
| 3 Client service billing | Separate client SEO plans/subscriptions, configurable status/reminder thresholds, counters/progress, grace/read-only/operation-suspension/full-suspension behavior, renewal/invoices/manual payments, plan changes/extensions/cancellation, history, in-app and queued email notifications | Dynamic plan-limit enforcement, editable commercial terms, tax/invoice line items, configurable grace-access matrices, auto-renewal/payment providers, WhatsApp, billing exports, transactional outbox for guaranteed reminder delivery |
| 4 Tasks/SOP/automation | Not implemented | Entire phase |
| 5 Crawler/SEO audit | Not implemented | Entire phase, including SSRF-safe fetching/robots compliance, queues, scoring and task integration |
| 6 Keywords/rankings | Not implemented | Entire phase; sources must remain clearly labeled |
| 7 Backlinks | Not implemented | Entire phase, including import mapping, deduplication, verification, campaigns and history |
| 8 Content/competitors | Not implemented | Entire phase |
| 9 Reports/client portal | Not implemented | Entire phase, including PDF, scoped files and white label |
| 10 DeepSeek AI | Environment placeholders only | Entire provider/settings/prompt/usage implementation; there are no AI feature buttons or fake responses |
| 11 Optional APIs | Not implemented | Entire phase |
| 12 SaaS billing | Not implemented | Separate platform plans/subscriptions/feature limits/payment gateway architecture; client SEO billing must never be reused as SaaS billing |
| 13 Operational hardening | Authentication throttling, framework CSRF, escaped Blade output, private environment defaults, scoped validation, doctor command, test coverage | Backups, storage controls, platform security screens, maintenance/queue/cron monitoring, retention, large-data performance tests and deployment hardening |
| 14 Release | Initial module tests, real-browser checks, frontend build, developer guides, MySQL CI configuration | MySQL 8 execution, remaining module tests, concurrent billing tests, full performance/security testing, all requested guides, production release |

Current expiry behavior: subscriptions that allow operations are active/renewal-soon/expiring/urgent/grace. Operational creation/editing is rejected outside those states. Historical reads remain possible except when full suspension blocks website/project viewing. Client identity/settings and administrative billing actions remain available to authorized agency staff. There is no client portal yet. Cancellation/suspension preserves every stored record.

The current plan `limits` JSON is storage for later feature-limit implementation, not enforcement. The UI does not advertise enforced limits. `auto_renew` is stored but never schedules a charge; creation currently defaults to false. Renewals are explicit authorized actions. Recording a manual payment documents money already received externally.

Validation completed locally: 26 PHP tests / 179 assertions on SQLite; one real Chrome end-to-end flow including nine mobile-width screens and CSRF rejection; production Vite build. Screenshots are under ignored `test-results/`. SMTP transport, reminder worker execution, MySQL, payment gateways, and the remaining modules have not been production validated.

Next implementation phase is the task system, SOP workflows, automation, and notifications, followed by its authorization, tenant-isolation and end-to-end tests before beginning the crawler.
