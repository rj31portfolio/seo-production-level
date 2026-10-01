# Reports and exports

Authorized users generate report snapshots from completed tool runs. Snapshots preserve the agency name, project, collection time, source, summary, metrics, checks, score methodology, keyword analysis, recommendations and data-scope notes. Changing the original results later does not silently rewrite a report.

HTML downloads escape all supplied values. PDF preparation is an explicit POST queued on `seo`; progress reflects queued/running/completed/failed state. PDFs are saved under private agency/report paths and downloaded only after permission and tenant checks. Remote resources, PDF JavaScript and PHP execution are disabled. The local worker uses private workspace temporary storage.

Tool results offer CSV and XLSX exports. CSV streams escape formula-like values. XLSX uses explicit string cells and splits large JSON fields into labeled 30,000-character segments rather than silently truncating them. Keyword, ranking and backlink imports are bounded; ranking/backlink list exports are CSV. Streams capture the authorized tenant for the deferred response lifetime and restore/clear context afterward.

Branding currently uses the agency name. Client sharing/portal, PDF logo/brand themes, report-template editing, white-label domains, composite monthly reports, completed-work sections, ranking/backlink aggregate reports and report retention/cleanup controls remain outstanding. Archiving a run preserves its existing report records, but the report listing currently derives from visible unarchived runs.
