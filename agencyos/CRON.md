# Scheduler

The current scheduler invokes `agencyos:subscription-reminders` daily at 08:00 in configurable AGENCYOS_TIMEZONE and uses withoutOverlapping. Calendar-day comparisons use each agency's own timezone.

Install a real cron entry on Linux/cPanel (adjust paths):

```cron
* * * * * /usr/bin/php /absolute/path/agencyos/artisan schedule:run >> /absolute/private/path/scheduler.log 2>&1
```

`php artisan schedule:list` shows registered tasks; `php artisan agencyos:subscription-reminders` runs a manual scan. A running queue worker is also required for queued email. A failed email is visible in failed_jobs; the current implementation does not provide an outbox guaranteeing eventual dispatch after a scanner crash.

Cron/worker setup is not automatic. No scheduler heartbeat exists yet, and website/backlink/ranking/report schedules remain to be implemented.
