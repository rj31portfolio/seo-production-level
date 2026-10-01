# Database

Production target: MySQL 8+. SQLite is used for local module tests; the MySQL CI job has not been run locally.

Foundation tables: users, password_reset_tokens, sessions, cache/cache_locks, jobs/job_batches/failed_jobs, agencies, roles, permissions, permission_role, agency_users, activity_logs.

Operations: clients, websites, projects, project_users. Agency-owned records have agency_id; composite parent keys enforce the same agency for clients/websites/projects and project memberships. Clients/websites/projects use soft deletes. Referenced clients cannot be removed through the UI while they have operational or subscription records; archive their status instead.

Client billing: client_plans, client_subscriptions, client_invoices, client_payments, client_subscription_history, renewal_reminders, in_app_notifications. System expiry configuration resides in global system_settings.

There is at most one current client subscription per `(agency_id, client_id)`; its changes accumulate in the history table. SaaS subscriptions will require **different tables and services** in Phase 12. Client plan limits are currently stored as JSON and not enforced.

All currency amounts are integer minor units with a separate three-letter currency code. Invoices snapshot plan prices at issuance. Payment references are unique per agency. A reminder's unique key includes agency, subscription, expiry period, and threshold, so renewing can create reminders for the new service period without duplicating previous reminders.

Application timestamps are UTC. Agency timezone is used for service-date entry, display, and calendar-day reminder calculations. Due dates are date-only. Current form relationship selectors cap choices at 500; large searchable selectors remain outstanding. List endpoints use pagination. Subscription invoice/history lists paginate independently.

Database migrations are reversible for disposable environments. Downgrades and destructive operations on a business database require a verified backup and deliberate deployment procedure. No automated backup/restore engine exists yet.
