<?php

namespace App\Services\Seo;

use App\Models\SeoToolRun;
use RuntimeException;

class NetworkEngine
{
    private array $robotsCache = [];

    private float $lastRequest = 0;

    public function __construct(private SafeFetcher $fetcher, private PublicUrl $urls, private RobotsParser $robots, private PageAnalyzer $pages) {}

    public function fetchAllowed(string $url): array
    {
        return $this->fetcher->fetch($url, fn (string $target) => $this->before($target));
    }

    public function execute(SeoToolRun $run): void
    {
        $this->robotsCache = [];
        $this->lastRequest = 0;
        $input = $run->input;
        $settings = ToolRegistry::settings();
        $root = $this->urls->normalize($input['url']);
        $origin = $this->urls->origin($root);
        if ($run->tool === 'robots') {
            $response = $this->fetcher->fetch($origin.'/robots.txt');
            $run->results()->create(['url' => $response['url'], 'kind' => 'robots', 'data' => ['metrics' => ['http_status' => $response['status']], 'robots' => $this->robots->parse($response['body']), 'notes' => ['Robots.txt directives control crawler access; they do not establish definitive indexing behavior.']]]);
            $run->update(['processed' => 1, 'discovered' => 1, 'summary' => ['files_analyzed' => 1]]);

            return;
        }
        if ($run->tool === 'sitemap') {
            $this->sitemap($run, $root, $settings);

            return;
        }
        $crawl = in_array($run->tool, ['audit', 'broken-links', 'internal-links', 'meta-auditor'], true);
        $limit = isset($input['urls']) ? count($input['urls']) : ($crawl ? min($settings['max_pages'], $input['max_pages'] ?? $settings['max_pages']) : 1);
        $queue = new \SplQueue;
        $seen = [];
        foreach ($input['urls'] ?? [$root] as $target) {
            $queue->enqueue([$target, 0]);
            $seen[$target] = true;
        }
        $run->update(['discovered' => count($seen)]);
        $analyzed = [];
        $failures = 0;
        $referenceUrl = null;
        while (! $queue->isEmpty() && $run->processed < $limit) {
            [$url,$depth] = $queue->dequeue();
            try {
                $response = $this->fetcher->fetch($url, fn ($target) => $this->before($target));
                if ($response['status'] < 200 || $response['status'] >= 300) {
                    throw new RuntimeException('Page returned HTTP '.$response['status'].'. Content was not audited.');
                }
                if (! str_contains(strtolower($response['headers']['content-type'] ?? ''), 'html')) {
                    throw new RuntimeException('Response is not HTML; page checks were not run.');
                }
                $data = $this->pages->analyze($response);
                $data['depth'] = $depth;
                $data['score'] = $this->pages->score($data['checks']);
                if ($run->tool === 'local-seo') {
                    $data['local_signals'] = $this->pages->localSignals($response, $input);
                }
                if ($url === $root) {
                    $origin = $this->urls->origin($response['url']);
                    $seen[$response['url']] = true;
                    $referenceUrl = $response['url'];
                }
                $analyzed[$response['url']] = $data;
                $run->results()->create(['url' => $response['url'], 'kind' => 'page', 'data' => $data]);
                if ($crawl && $depth < $settings['max_depth']) {
                    foreach ($data['links'] as $link) {
                        if ($link['internal'] && $this->urls->origin($link['url']) === $origin && ! isset($seen[$link['url']]) && count($seen) < $limit * 5) {
                            $seen[$link['url']] = true;
                            $queue->enqueue([$link['url'], $depth + 1]);
                        }
                    }
                }
                if ($run->tool === 'canonical' && count($data['canonicals']) === 1 && $data['canonicals'][0]) {
                    $canonical = $data['canonicals'][0];
                    try {
                        $target = $this->fetcher->fetch($canonical, fn ($target) => $this->before($target));
                        $canonicalData = ['metrics' => ['http_status' => $target['status']], 'redirects' => $target['redirects']];
                    } catch (RuntimeException $e) {
                        $canonicalData = ['error' => $e->getMessage(), 'notes' => ['Canonical target status could not be established.']];
                    }
                    $run->results()->create(['url' => $canonical, 'kind' => 'canonical_target', 'data' => $canonicalData]);
                }
            } catch (RuntimeException $e) {
                $failures++;
                $run->results()->create(['url' => $url, 'kind' => 'fetch_error', 'data' => ['error' => $e->getMessage(), 'notes' => ['No SEO measurements were generated for this page.']]]);
            }
            $run->processed++;
            $run->discovered = count($seen);
            $run->save();
        }
        if (! $analyzed) {
            throw new RuntimeException('Website could not be analyzed. Review the saved fetch errors; no score was generated.');
        }
        if ($run->tool === 'broken-links') {
            $this->checkLinks($run, $analyzed, $settings['max_pages']);
        }
        if ($crawl) {
            $this->crawlSummary($run, $analyzed, $queue->count());
        }
        if (isset($input['urls'])) {
            if (! $referenceUrl) {
                throw new RuntimeException('Your reference page could not be analyzed. Competitor findings were retained, but no content-gap comparison was generated.');
            }$first = $referenceUrl;
            $reference = $analyzed[$first];
            $gaps = [];
            foreach ($analyzed as $url => $page) {
                if ($url !== $first) {
                    $gaps[$url] = array_values(array_diff(array_column($page['terms'], 'term'), array_column($reference['terms'], 'term')));
                }
            }
            $run->results()->create(['kind' => 'comparison', 'data' => ['metrics' => ['collected_pages' => count($analyzed)], 'reference_url' => $first, 'topic_candidates_not_seen_on_reference' => $gaps, 'notes' => ['Comparisons cover fetched HTML only. Term differences are frequency-based candidates for editorial review, not proof of missing search demand or competitor traffic.']]]);
        }
        $scores = array_column(array_column(array_values($analyzed), 'score'), 'overall');
        $run->update(['summary' => ['analyzed_pages' => count($analyzed), 'fetch_failures' => $failures, 'discovered_pages' => count($seen), 'unvisited_pages' => $queue->count(), 'average_diagnostic_score' => round(array_sum($scores) / count($scores), 1), 'coverage' => 'Bounded crawl; findings apply only to collected pages.']]);
    }

    private function before(string $url): void
    {
        $origin = $this->urls->origin($url);
        if (! isset($this->robotsCache[$origin])) {
            $response = $this->fetcher->fetch($origin.'/robots.txt');
            if ($response['status'] >= 500 || in_array($response['status'], [401, 403, 429], true)) {
                throw new RuntimeException('Robots.txt is unavailable or access restricted; crawling stopped conservatively.');
            }
            $this->robotsCache[$origin] = $this->robots->parse($response['status'] === 200 ? $response['body'] : '');
        }
        $parsed = $this->robotsCache[$origin];
        if (! $this->robots->allowed($parsed, $url)) {
            throw new RuntimeException('URL excluded by robots.txt.');
        }
        $delay = max(ToolRegistry::settings()['delay_ms'] / 1000, $parsed['crawl_delay_seconds']);
        $remaining = $delay - (microtime(true) - $this->lastRequest);
        if ($remaining > 0) {
            usleep((int) ($remaining * 1000000));
        }
        $this->lastRequest = microtime(true);
    }

    private function checkLinks(SeoToolRun $run, array $pages, int $limit): void
    {
        $targets = [];
        foreach ($pages as $source => $page) {
            foreach ($page['links'] as $link) {
                if (count($targets) < $limit || isset($targets[$link['url']])) {
                    $targets[$link['url']][] = ['source' => $source, 'anchor' => $link['anchor']];
                }
            }
        }
        foreach ($targets as $url => $sources) {
            try {
                $r = $this->fetcher->fetch($url, fn ($target) => $this->before($target));
                $data = ['metrics' => ['http_status' => $r['status'], 'response_ms' => $r['response_ms']], 'sources' => array_slice($sources, 0, 100), 'redirects' => $r['redirects'], 'classification' => $r['status'] === 404 ? 'not_found' : ($r['status'] >= 500 ? 'server_error' : (in_array($r['status'], [401, 403, 429]) ? 'restricted_or_rate_limited' : 'response_received'))];
            } catch (RuntimeException $e) {
                $data = ['error' => $e->getMessage(), 'sources' => array_slice($sources, 0, 100), 'classification' => 'unverified'];
            }
            $run->results()->create(['url' => $url, 'kind' => 'link_check', 'data' => $data]);
        }
    }

    private function crawlSummary(SeoToolRun $run, array $pages, int $remaining): void
    {
        $titles = [];
        $descriptions = [];
        $incoming = array_fill_keys(array_keys($pages), 0);
        foreach ($pages as $url => $page) {
            if ($page['title'] !== '') {
                $titles[$page['title']][] = $url;
            }if ($page['description'] !== '') {
                $descriptions[$page['description']][] = $url;
            }
            foreach ($page['links'] as $link) {
                if ($link['internal'] && isset($incoming[$link['url']])) {
                    $incoming[$link['url']]++;
                }
            }
        }
        $run->results()->create(['kind' => 'crawl_summary', 'data' => ['metrics' => ['collected_pages' => count($pages), 'unvisited_discovered_pages' => $remaining], 'duplicate_titles' => array_filter($titles, fn ($v) => count($v) > 1), 'duplicate_descriptions' => array_filter($descriptions, fn ($v) => count($v) > 1), 'incoming_links_within_crawl' => $incoming, 'notes' => ['Incoming counts and potential orphan-like pages are relative to this bounded crawl. They are not proof of site-wide orphan pages.']]]);
    }

    private function sitemap(SeoToolRun $run, string $root, array $settings): void
    {
        $files = [$root];
        $seen = [];
        $entries = [];
        $duplicates = 0;
        while ($files && count($seen) < 10) {
            $url = array_shift($files);
            if (isset($seen[$url])) {
                continue;
            }$seen[$url] = true;
            $r = $this->fetcher->fetch($url, fn ($target) => $this->before($target));
            if ($r['status'] !== 200 || stripos($r['body'], '<!DOCTYPE') !== false || stripos($r['body'], '<!ENTITY') !== false) {
                throw new RuntimeException('Sitemap is unavailable or contains prohibited XML declarations.');
            }
            $previous = libxml_use_internal_errors(true);
            try {
                $xml = simplexml_load_string($r['body'], 'SimpleXMLElement', LIBXML_NONET);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            if (! $xml || ! in_array($xml->getName(), ['urlset', 'sitemapindex'], true)) {
                throw new RuntimeException('Invalid sitemap XML.');
            }
            $nodes = $xml->xpath('//*[local-name()="url"]|//*[local-name()="sitemap"]');
            foreach ($nodes as $node) {
                $target = $this->urls->relative($url, (string) $node->loc);
                if (! $target) {
                    continue;
                }
                if ($xml->getName() === 'sitemapindex') {
                    if (count($files) < 10 && $this->urls->origin($target) === $this->urls->origin($root)) {
                        $files[] = $target;
                    }

                    continue;
                }
                if (isset($entries[$target])) {
                    $duplicates++;

                    continue;
                }if (count($entries) >= 1000) {
                    break;
                }
                $entries[$target] = ['url' => $target, 'last_modified' => (string) $node->lastmod, 'change_frequency' => (string) $node->changefreq, 'priority' => (string) $node->priority];
            }
            $run->processed = count($seen);
            $run->discovered = count($seen) + count($files);
            $run->save();
        }
        $run->results()->create(['url' => $root, 'kind' => 'sitemap', 'data' => ['metrics' => ['sitemap_files' => count($seen), 'urls' => count($entries), 'duplicates' => $duplicates], 'entries' => array_values($entries), 'notes' => ['Extraction is bounded to 10 sitemap files and 1,000 URLs. URL status/canonical verification is limited to the configured sample below.']]]);
        foreach (array_slice(array_keys($entries), 0, $settings['max_pages']) as $url) {
            try {
                $r = $this->fetcher->fetch($url, fn ($target) => $this->before($target));
                $data = ['metrics' => ['http_status' => $r['status']], 'redirects' => $r['redirects']];
            } catch (RuntimeException $e) {
                $data = ['error' => $e->getMessage()];
            }
            $run->results()->create(['url' => $url, 'kind' => 'sitemap_url', 'data' => $data]);
        }
        $run->update(['summary' => ['sitemap_files' => count($seen), 'urls' => count($entries), 'duplicate_urls' => $duplicates, 'checked_urls' => min(count($entries), $settings['max_pages'])]]);
    }
}
