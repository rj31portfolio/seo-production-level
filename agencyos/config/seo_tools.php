<?php

return [
    'role_permissions' => [
        'agency_owner'=>['seo_tools.view','seo_tools.audit','seo_tools.keywords','seo_tools.content','seo_tools.competitors','seo_tools.reports','seo_tools.ai','seo_tools.delete','tasks.view','tasks.create','tasks.assign','tasks.complete'],
        'seo_manager'=>['seo_tools.view','seo_tools.audit','seo_tools.keywords','seo_tools.content','seo_tools.competitors','seo_tools.reports','seo_tools.ai','tasks.view','tasks.create','tasks.assign','tasks.complete'],
        'seo_executive'=>['seo_tools.view','seo_tools.audit','seo_tools.keywords','seo_tools.content','tasks.view','tasks.complete'],
        'content_writer'=>['seo_tools.view','seo_tools.content','seo_tools.keywords'],
        'developer'=>['seo_tools.view','seo_tools.audit','tasks.view','tasks.complete'],
    ],
    'tools' => [
        'keyword-clustering'=>['name'=>'Keyword clustering','category'=>'Keywords','permission'=>'seo_tools.keywords','mode'=>'keywords','description'=>'Group supplied keywords by topic and inferred intent using transparent rules.'],
        'search-intent'=>['name'=>'Search intent classifier','category'=>'Keywords','permission'=>'seo_tools.keywords','mode'=>'keywords','description'=>'Classify supplied keywords with English-language rules and explicit uncertainty.'],
        'question-keywords'=>['name'=>'Question keyword generator','category'=>'Keywords','permission'=>'seo_tools.keywords','mode'=>'keywords','description'=>'Generate editable question phrases from supplied seeds; suggestions have no measured search volume.'],
        'longtail-keywords'=>['name'=>'Long-tail keyword generator','category'=>'Keywords','permission'=>'seo_tools.keywords','mode'=>'keywords','description'=>'Generate rule-based long-tail editorial suggestions from your seed keywords.'],
        'local-keywords'=>['name'=>'Local keyword generator','category'=>'Keywords','permission'=>'seo_tools.keywords','mode'=>'keywords','description'=>'Combine supplied services and location into local keyword suggestions.'],
        'audit'=>['name'=>'SEO website audit','category'=>'Audit','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Crawl a bounded set of pages and collect technical, on-page, indexability and content findings.'],
        'page-score'=>['name'=>'Page SEO score','category'=>'Audit','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Analyze one HTML page and show the checks and weights behind its internal diagnostic score.'],
        'headings'=>['name'=>'Heading analyzer','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Inspect H1–H6, empty headings, hierarchy and main-heading counts.'],
        'meta-auditor'=>['name'=>'Meta auditor','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Collect titles, descriptions, robots, canonical, social metadata and duplicates across crawled pages.'],
        'broken-links'=>['name'=>'Broken link checker','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Fetch a bounded set of discovered links and report response statuses, restrictions and unverified failures.'],
        'redirects'=>['name'=>'Redirect checker','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Record actual HTTP redirect hops with loop detection and safe target validation.'],
        'canonical'=>['name'=>'Canonical checker','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Detect preferred URL declarations and verify the canonical target where access permits.'],
        'robots'=>['name'=>'Robots.txt analyzer','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Read crawler directives, sitemap references and crawl-delay hints.'],
        'sitemap'=>['name'=>'XML sitemap analyzer','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Parse bounded sitemap indexes, identify duplicate URLs and sample URL responses.'],
        'schema'=>['name'=>'Schema analyzer','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Detect JSON-LD types/properties, malformed JSON, microdata and RDFa indicators.'],
        'images'=>['name'=>'Image SEO auditor','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Inspect image URLs, ALT attributes, declared dimensions and loading hints.'],
        'internal-links'=>['name'=>'Internal link analyzer','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'network','description'=>'Collect anchors and incoming-link counts within a bounded crawl, with depth and duplicate metadata.'],
        'keyword-extractor'=>['name'=>'Keyword extractor','category'=>'Keywords','permission'=>'seo_tools.keywords','mode'=>'network','description'=>'Extract frequency-based topic candidates from fetched visible content; no search volumes are invented.'],
        'content-analyzer'=>['name'=>'Content analyzer','category'=>'Content','permission'=>'seo_tools.content','mode'=>'text','description'=>'Measure supplied content, paragraphs, keyword occurrences and structure.'],
        'url-analyzer'=>['name'=>'URL SEO analyzer','category'=>'Technical','permission'=>'seo_tools.audit','mode'=>'url-local','description'=>'Inspect URL length, protocol, path, parameters and readability without fetching it.'],
    ],
    'defaults'=>['max_pages'=>30,'max_depth'=>3,'delay_ms'=>1000,'timeout'=>15,'max_bytes'=>2097152,'max_redirects'=>5,'runs_per_minute'=>6],
];
