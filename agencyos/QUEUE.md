# Queue

Database is the default backend. Client expiry mail uses `default`; crawls, AI, backlink verification and report PDF generation use `seo`. The monitor command is scheduled every minute. See [CRAWLER.md](CRAWLER.md) for request safeguards and detailed limits.

```powershell
..\.tools\php\php.exe artisan queue:work --queue=seo,default --sleep=3 --tries=1 --timeout=1800 --memory=384
..\.tools\php\php.exe artisan schedule:work
```

Production needs supervised workers and once-per-minute `artisan schedule:run`. Database retry-after is 1860 seconds and must exceed the longest job timeout. Redis/custom backends must configure equivalent visibility/retry timing. A default-only worker will leave SEO jobs pending. A short hosting worker timeout is unsuitable for a large crawl.

Tenant-aware jobs carry agency IDs, retrieve records in that context, reauthorize the requesting user and avoid replaying completed jobs. There are no automatic retries of billed AI requests. Inspect `artisan queue:failed` and private logs before intentionally retrying failed jobs. Failure callbacks mark pending runs/PDF/verification records failed when possible.

After deployments, use `artisan queue:restart` and let the process supervisor restart workers. The hidden local development processes are not supervised services and require manual restart after exiting or rebooting. Their logs are under `storage/logs/seo-worker-*` and `storage/logs/seo-scheduler-*`.

Windows does not provide Linux `pcntl` timeout semantics. Production job-kill/recovery behavior, durable outboxes, queue dashboards/heartbeats, crash-after-partial-persistence reconciliation, retention and large imports still need further implementation/validation.
