# Crawler operation

Start a worker with the supported PHP installation:

```powershell
..\.tools\php\php.exe artisan queue:work --queue=seo,default --sleep=3 --tries=1 --timeout=1800 --memory=384
..\.tools\php\php.exe artisan schedule:work
```

Production requires supervised workers and a once-per-minute `artisan schedule:run`. Database queue retry-after is 1860 seconds, exceeding the longest job timeout. Windows PHP lacks Unix process-signal timeout guarantees; use supervised Linux workers for production. Local background workers are development processes, not installed services.

Only public HTTP/HTTPS on standard ports is accepted. Credentials/control characters are rejected. Every resolved address must be public; private/reserved destinations, IPv4-mapped/translation IPv6, Teredo and 6to4 are blocked. DNS resolution is pinned for each HTTP request. Every redirect is validated again. TLS peer/hostname checks remain enabled. Proxy bypass prevents unexpected environment proxy routing in the crawler.

Robots directives are fetched and cached within the job per origin. Matching follows user-agent groups, longest applicable path rules and Allow ties, including wildcard/end anchors. HTTP 401/403/429/5xx robots failures stop crawling conservatively. A missing robots file permits crawling. Crawl-delay hints and the configured delay apply. See [RFC 9309](https://www.rfc-editor.org/rfc/rfc9309.html) for the protocol; this implementation is bounded and is not a claim of exhaustive compliance with every server variant.

Defaults: 30 pages, depth 3, 1-second delay, 15-second HTTP timeout, 2 MB decompressed response limit, 5 redirects, 6 submissions/minute. Super Admin can change bounded settings and assign stricter plans/agency quotas. Robots delay can increase wait time. Discovery is capped relative to the page limit. Sitemap extraction caps 10 files and 1,000 URLs; verification samples configured page limits.

Run statuses show actual completed/discovered work. Unknown discovery totals have no fabricated completion percentage. Failed fetches never receive invented scores. Retry creates a new run and consumes a new attempt. Pending/running runs cannot be archived.

Monitoring currently checks one project website page, storing HTTP/TLS request outcomes and HTML signals. In-app alerts cover changes to title, description, headings, canonical declarations, page robots directives and extracted text, plus verification failures. Dedicated robots.txt/sitemap-file changes, certificate-expiry dates and email monitoring alerts remain outstanding.

Local PHP trusts the existing XAMPP CA bundle copied to `.tools/cacert.pem`. PHP and PDF temporary files use the workspace drive because the system drive was full. Configure your deployment's maintained trust store and writable private temporary storage independently.
