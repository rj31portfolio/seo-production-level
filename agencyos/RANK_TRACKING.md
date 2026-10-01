# Rank tracking

`/seo/rankings` stores project keywords and dated observations from **Manual**, **CSV import** or **XLSX import** sources. No search-engine scraping or provider connection is configured. Positions must be supplied from an appropriate source.

Required import headers: `keyword,observed_on,position,country,location,device,search_engine`. Optional: `evidence`. Files are limited to 256 KB and 500 complete rows. Dates must be YYYY-MM-DD text and not in the future; positions must be 1–1000 or blank. Country is two letters; device is desktop/mobile/tablet. XLSX uses the first worksheet, plain values and at most ten columns; formulas are not evaluated. Duplicate date/context observations reject the entire transactional import, preserving existing history.

Blank position means not found within the supplied observation range. Describe that range in evidence. It is not position zero or proof the site has disappeared from search.

The history screen compares observations only for matching keyword, country, location, device and search engine. It shows current, previous, previous-minus-current movement, best/worst known positions and a chart of up to 180 recent observations. Unknown positions remain null. CSV export includes source labels and escapes formula-like values.

Outstanding: supported provider/API adapters, aggregate Top 3/10/20/50/100 reports, dedicated ranking PDF/XLSX reports, automated integrations and project-wide distribution/movement charts. The current 'ranking report' is the per-keyword contextual history screen; it is not a complete aggregate reporting module.
