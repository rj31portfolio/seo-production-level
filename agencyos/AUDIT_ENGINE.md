# SEO audit engine

`NetworkEngine`, `PageAnalyzer`, `SafeFetcher` and `RobotsParser` perform bounded server-side HTTP/HTML analysis. The audit, meta auditor, internal-link analyzer and broken-link checker crawl within the initial URL's final origin, up to the effective platform/plan page and depth limits. Other technical tools inspect one page or their dedicated robots/sitemap input. Competitor tools inspect only the supplied URLs.

Collected measurements include HTTP status, request time, HTML bytes, extracted word counts, title, description, headings, canonical declarations, robots directives, link anchors/relations, image attributes, structured-data types and social metadata. Results, errors and crawl coverage are stored in agency-scoped runs/results. No JavaScript rendering, official schema validation, Core Web Vitals, search metrics or site-wide discovery is claimed.

Scores use configurable weighted category pass rates. Informational checks are excluded. Categories with no applicable checks are omitted from the normalized weighting. The result exposes checks, weights, category scores and the method. Word-count/title-length thresholds are heuristics. **SEO AgencyOS Score is not an official Google score.**

An actionable failed page check can become a project task through an authorized explicit action. Duplicate generation for the same run/result/rule is prevented. Technical/indexability/schema findings prefer an assigned developer; other findings prefer an assigned SEO executive. Unassigned findings remain visible to managers. Employees submit work for review; managers approve completion.

Limitations: duplicate metadata and incoming counts apply to collected pages; omitted pages are not proof of removal/orphan status. Image files are not downloaded for size inspection. Canonical targets are verified only by the canonical tool. General audits do not yet combine sitemap coverage and robots-file reports. Link-check errors are unverified, and 403/429 responses are not automatically classified as broken links.
