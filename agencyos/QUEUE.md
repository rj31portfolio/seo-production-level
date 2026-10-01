# Queue

Database is the default backend; Redis is optional. Laravel's standard job/failed-job migrations are included. Current queued workloads are client-expiry emails. Laravel handles authentication reset mail through its password-broker notification flow.

Run `php artisan queue:work --queue=default --tries=3 --timeout=60` under a process manager for a VPS. Restart workers with `php artisan queue:restart` after deployments. On constrained hosting, a periodic bounded `php artisan queue:work --stop-when-empty --max-time=50` command is an alternative when the hosting provider permits it; avoid overlapping workers without operational planning.

Inspect failed jobs with `php artisan queue:failed`; review the private error logs before deliberately retrying a job. Do not assume a healthy queue merely because its table exists. Queue monitoring/heartbeat/dashboard is not implemented yet.

Future tenant-aware jobs must carry agency ID and enter trusted tenant context before retrieving records. Jobs must handle retries/idempotence explicitly. Crawler/import/export/report/AI queues remain unimplemented.
