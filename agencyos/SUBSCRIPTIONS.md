# Client SEO subscriptions

Implemented subscriptions represent an agency's SEO services to its clients. The agency's own SaaS subscription is a separate future module.

Owners create SEO plans with price_minor, currency, duration_days and billing cycle. Price 10000 represents 100.00. Disabling a plan preserves existing records and blocks new subscriptions/renewals against it; select an active plan before renewal.

Create a subscription by selecting a client without a current subscription, an active plan, service start/expiry, expiry behavior and grace days. The initial invoice snapshots the selected price. Service timestamps entered in agency timezone are converted to UTC.

Computed status prioritizes cancellation/suspension, then scheduled future starts, expired/grace, then urgent/expiring/renewal-soon/active. Labels and reminder thresholds are configured by Super Admin at `/super-admin/expiry-settings`. Progress describes elapsed service time and is bounded to 0–100%. It is not an SEO performance score.

An authorized renewal extends from the later of now or the current expiry by the plan's duration. It creates another invoice, change history, and an in-app notification without fabricating a payment. Explicit plan changes/extensions/cancellation/suspension/reactivation require a reason. All changes retain previous service history.

Expired read-only and suspended-operation modes reject website/project creation and editing while preserving historical reads. Grace permits operations until the precise grace end. Full suspension also denies website/project viewing outside operational status. Client identity and administrative billing remain available to authorized agency staff. Dynamic access matrices and the client portal remain outstanding.

Default reminders occur at 30,15,7,3,1,0 calendar days in agency timezone. `agencyos:subscription-reminders` creates in-app messages and queues email notifications to permitted staff and the client contact email. Repeated scans deduplicate the same threshold and expiry. Scheduler downtime can miss a day's threshold; catch-up policy/outbox delivery guarantees remain outstanding.
