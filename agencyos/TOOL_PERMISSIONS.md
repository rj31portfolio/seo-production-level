# Tool access and limits

All tool routes require authentication and tenant resolution. Tool runs/results, keywords, rankings, backlinks/verifications, monitoring, reports and generated tasks use agency scopes and composite tenant foreign keys where applicable. Streamed exports explicitly restore the captured authorized tenant after request middleware cleanup. Queue workers enter an agency context and restore the prior context/user afterward.

Owners/managers can use permitted agency tools. Executives can audit, process keywords/content and manage permitted ranking/backlink observations for assigned projects. Writers have content/keyword tools. Developers have technical audits and assigned task review. Standalone runs require `clients.view`; employees must select an assigned project. AI/report/manager permissions are not implicitly granted to every employee. Client accounts have no SEO tool permission and no client portal has been implemented.

Tool permissions: `seo_tools.view`, `.audit`, `.keywords`, `.rankings`, `.backlinks`, `.content`, `.competitors`, `.reports`, `.ai`, `.delete`; task workflow uses `tasks.view`, `.create`, `.assign`, `.complete`. Default role permissions are in `config/seo_tools.php`, merged by `PermissionSeeder`. Super Admin controls crawler/scoring settings, tool enablement, agency overrides, AI configuration and platform tool plans.

Admission checks active user/membership, active agency, role permission, global feature availability, assigned platform plan, selected-project permission and operational client service. Jobs repeat authorization when executing. Historical reads use policies; archiving and manager approval require their own permissions.

Tool plans are **separate from client SEO service plans**. Super Admin defines allowed tools, per-tool monthly attempts, per-crawl page cap, reserved pages/month and AI daily/monthly caps. Assignment is manual. This does not charge money or implement full SaaS subscription billing. An unassigned agency follows global settings and agency overrides. Agency monthly overrides take precedence over per-tool plan quotas; they do not enable features excluded from a plan.

The agency row is locked while admitting requests and updating counters, preventing first-counter races. Invalid local inputs roll back their admission transaction. Accepted attempts, including failed jobs, consume quota. Crawl pages represent reserved capacity, not measured successful crawl count. Backlink verification counts as a backlink tool attempt; monitoring currently consumes page-score attempts. Larger exports and actual request/page/time cost accounting need further hardening.

Usage is shown by month with used/limit/remaining values. AI caps additionally apply per user/project. Full quotas for project/website/backlink totals, configurable per-role permission matrices, paid plan lifecycles and concurrent production load validation remain outstanding.
