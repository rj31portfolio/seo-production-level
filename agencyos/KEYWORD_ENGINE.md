# Keyword engine

Five offline tools process supplied keywords: clustering, intent, question suggestions, long-tail suggestions and local suggestions. A separate keyword extractor analyzes fetched HTML. Optional AI keyword generation requires configured DeepSeek and can produce editorial suggestions; demand metrics remain unavailable.

Text input accepts at most 500 keywords, one per line, each at most 200 characters. CSV/XLSX imports accept 256 KB files, up to 500 rows; keywords use the first worksheet/column with an optional `keyword` header. Workbooks are bounded by expanded archive size and entry count. Formula text is read without evaluation. Processing remains synchronous because the input is strictly capped; larger queued import support is not implemented.

Intent uses English-language patterns for informational, commercial, transactional, navigational and local queries, otherwise `unclassified`. Clustering groups the first meaningful term with inferred intent. This is heuristic grouping, not SERP-derived clustering. Question/long-tail/local generation uses explicit phrase templates; a location is required for local suggestions. Review all generated phrases before use.

Runs persist supplied input, keyword/intent/cluster rows and suggested paths. Project-linked keyword results can be saved to the project's keyword list. CSV/XLSX export and saved HTML/PDF report snapshots are supported. Generated paths are suggestions and do not create website pages.

Unavailable: search volume, CPC, keyword difficulty, traffic, unrestricted search-engine discovery, entity recognition and automated search demand validation. AI clustering improvements and automatic keyword-to-content-task generation remain outstanding.
