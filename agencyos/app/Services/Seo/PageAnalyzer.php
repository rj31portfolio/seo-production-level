<?php

namespace App\Services\Seo;

use App\Models\SystemSetting;
use DOMDocument;
use DOMXPath;

class PageAnalyzer
{
    public function localSignals(array $response, array $input): array
    {
        $text = mb_strtolower(html_entity_decode(strip_tags(preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $response['body'])), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $matches = [];
        foreach (['business_name', 'location', 'address', 'phone'] as $field) {
            $value = $input[$field] ?? null;
            $matches[$field] = $value ? mb_strpos($text, mb_strtolower($value)) !== false : null;
        }

        return ['supplied_details_found_in_text' => $matches, 'note' => 'Exact text matches only. Formatting differences can cause misses. Business identity, GBP, citations and reviews are not externally verified.'];
    }

    public function __construct(private PublicUrl $urls, private LocalAnalyzer $content) {}

    public function analyze(array $response): array
    {
        $url = $response['url'];
        $html = $response['body'];
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($dom);
        $text = fn (string $expression) => (string) $xpath->evaluate('string('.$expression.')');
        $title = trim($text('(//title)[1]'));
        $meta = function (string $name) use ($xpath): string {
            foreach ($xpath->query('//meta') as $node) {
                if (strtolower($node->getAttribute('name') ?: $node->getAttribute('property')) === strtolower($name)) {
                    return trim($node->getAttribute('content'));
                }
            }

            return '';
        };
        $metadata = [];
        foreach (['description', 'robots', 'viewport', 'og:title', 'og:description', 'og:image', 'twitter:card', 'twitter:title'] as $name) {
            $metadata[$name] = $meta($name);
        }
        $meta = fn (string $name): string => $metadata[$name] ?? '';
        $headings = [];
        $last = 0;
        $hierarchy = true;
        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') as $h) {
            $level = (int) substr($h->nodeName, 1);
            if ($level > $last + 1) {
                $hierarchy = false;
            }$last = $level;
            $headings[] = ['level' => $level, 'text' => trim($h->textContent)];
        }
        $canonicals = [];
        foreach ($xpath->query('//link[@href]') as $link) {
            if (in_array('canonical', preg_split('/\s+/', strtolower($link->getAttribute('rel'))), true)) {
                $canonicals[] = $this->urls->relative($url, $link->getAttribute('href'));
            }
        }
        $links = [];
        $invalidLinks = 0;
        foreach ($xpath->query('//a[@href]') as $link) {
            $href = trim($link->getAttribute('href'));
            if ($href === '' || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript|data):/i', $href)) {
                continue;
            }
            $target = $this->urls->relative($url, $href);
            if (! $target) {
                $invalidLinks++;

                continue;
            }
            $links[] = ['url' => $target, 'anchor' => mb_substr(trim($link->textContent), 0, 300), 'internal' => $this->urls->origin($url) === $this->urls->origin($target), 'rel' => $link->getAttribute('rel')];
            if (count($links) >= 1000) {
                break;
            }
        }
        $images = [];
        foreach ($xpath->query('//img') as $img) {
            $images[] = ['url' => $this->urls->relative($url, $img->getAttribute('src')), 'alt' => $img->hasAttribute('alt') ? $img->getAttribute('alt') : null, 'width' => $img->getAttribute('width'), 'height' => $img->getAttribute('height'), 'loading' => $img->getAttribute('loading'), 'file_size_bytes' => null];
            if (count($images) >= 500) {
                break;
            }
        }
        $schema = [];
        $invalidSchema = 0;
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            try {
                $data = json_decode($script->textContent, true, 32, JSON_THROW_ON_ERROR);
                $this->schemaTypes($data, $schema);
            } catch (\Throwable) {
                $invalidSchema++;
            }
        }
        foreach ($xpath->query('//*[@itemscope and @itemtype]') as $node) {
            $schema[] = ['type' => $node->getAttribute('itemtype'), 'format' => 'microdata'];
        }
        foreach ($xpath->query('//*[@typeof]') as $node) {
            $schema[] = ['type' => $node->getAttribute('typeof'), 'format' => 'RDFa indicator'];
        }
        $mixed = str_starts_with($url, 'https://') && $xpath->query('//*[@src and starts-with(@src,"http:")]|//link[@href and starts-with(@href,"http:")]')->length > 0;
        foreach ($xpath->query('//head|//script|//style|//nav|//noscript') as $node) {
            $node->parentNode?->removeChild($node);
        }$textParts = [];
        foreach ($xpath->query('//body//text()') as $node) {
            $textParts[] = $node->textContent;
        }$visible = trim(preg_replace('/\s+/u', ' ', implode(' ', $textParts)));
        $content = $this->content->content($visible);
        $h1 = array_filter($headings, fn ($h) => $h['level'] === 1);
        $robots = strtolower($meta('robots').' '.($response['headers']['x-robots-tag'] ?? ''));
        $internal = count(array_filter($links, fn ($l) => $l['internal']));
        $missingAlt = count(array_filter($images, fn ($i) => $i['alt'] === null));
        $checks = [];
        $add = function (string $rule, bool $passed, string $severity, string $category, string $recommendation) use (&$checks): void {
            $checks[] = compact('rule', 'passed', 'severity', 'category', 'recommendation');
        };
        $add('http_success', $response['status'] >= 200 && $response['status'] < 300, 'critical', 'technical', 'Review the page response status.');
        $add('https', str_starts_with($url, 'https://'), 'high', 'technical', 'Serve the page over HTTPS.');
        $add('title_present', $title !== '', 'high', 'on_page', 'Give the page a descriptive title.');
        $add('title_length', mb_strlen($title) >= 15 && mb_strlen($title) <= 65, 'low', 'on_page', 'Review title length for clarity. Display width varies; this is a heuristic.');
        $add('description_present', $meta('description') !== '', 'medium', 'on_page', 'Write a useful meta description for this page.');
        $add('description_length', mb_strlen($meta('description')) >= 50 && mb_strlen($meta('description')) <= 170, 'low', 'on_page', 'Review meta description clarity and length. The 50–170 character range is a heuristic; search snippets can be rewritten.');
        $add('viewport_present', $meta('viewport') !== '', 'medium', 'technical', 'Declare a viewport appropriate for mobile devices, then check the layout in a browser. This check does not measure mobile usability.');
        $add('document_language', trim($text('(//html/@lang)[1]')) !== '', 'low', 'technical', 'Declare the document language with the HTML lang attribute.');
        $add('one_h1', count($h1) === 1, 'medium', 'on_page', 'Review the main heading and ensure a clear page topic.');
        $add('heading_hierarchy', $hierarchy, 'low', 'on_page', 'Use a logical heading structure.');
        $add('headings_not_empty', ! array_filter($headings, fn ($h) => $h['text'] === ''), 'low', 'on_page', 'Add descriptive text to empty headings.');
        $add('one_valid_canonical', count($canonicals) === 1 && $canonicals[0] !== null, 'high', 'indexability', 'Review the preferred URL indicated by canonical tags.');
        $add('no_noindex', ! str_contains($robots, 'noindex'), 'information', 'indexability', 'Confirm whether excluding this page from indexing is intentional.');
        $add('content_length', $content['metrics']['word_count'] >= 150, 'low', 'content', 'Review whether the page sufficiently answers its purpose; no word count is a Google requirement.');
        $add('internal_links', $internal > 0, 'medium', 'internal_linking', 'Link to relevant pages within the site.');
        $add('schema_detected', count($schema) > 0, 'low', 'schema', 'Consider appropriate structured data. Detection does not establish rich-result eligibility.');
        $add('valid_json_ld', $invalidSchema === 0, 'medium', 'schema', 'Correct malformed JSON-LD blocks.');
        $add('image_alt_attributes', $missingAlt === 0, 'medium', 'images', 'Add useful alt text to meaningful images; empty alt may be appropriate for decorative images.');
        $add('image_dimensions', ! array_filter($images, fn ($i) => ! $i['width'] || ! $i['height']), 'low', 'images', 'Set image width and height to help prevent layout shifts.');
        $add('no_mixed_content', ! $mixed, 'high', 'technical', 'Load resources over HTTPS.');

        return ['metrics' => ['http_status' => $response['status'], 'response_ms' => $response['response_ms'], 'html_bytes' => $response['bytes'], 'word_count' => $content['metrics']['word_count'], 'h1_count' => count($h1), 'internal_links' => $internal, 'external_links' => count($links) - $internal, 'images' => count($images), 'missing_alt' => $missingAlt, 'schema_types' => count($schema), 'invalid_links' => $invalidLinks], 'title' => $title, 'description' => $meta('description'), 'robots' => $robots, 'canonicals' => $canonicals, 'headings' => $headings, 'links' => $links, 'images' => $images, 'schema' => $schema, 'open_graph' => ['title' => $meta('og:title'), 'description' => $meta('og:description'), 'image' => $meta('og:image')], 'twitter' => ['card' => $meta('twitter:card'), 'title' => $meta('twitter:title')], 'terms' => $content['terms'], 'content_hash' => hash('sha256', $visible), 'redirects' => $response['redirects'], 'checks' => $checks, 'notes' => ['SEO AgencyOS Score is an internal diagnostic score and is not an official Google score.', 'HTML signals describe the fetched response; JavaScript-rendered content is not executed.', 'Image file sizes and canonical target statuses are unavailable until explicitly fetched.']];
    }

    private function schemaTypes(mixed $data, array &$types): void
    {
        if (! is_array($data)) {
            return;
        }if (isset($data['@type'])) {
            foreach ((array) $data['@type'] as $type) {
                $types[] = ['type' => $type, 'format' => 'JSON-LD', 'properties' => array_keys($data)];
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $this->schemaTypes($value, $types);
            }
        }
    }

    public function score(array $checks): array
    {
        $weights = SystemSetting::find('seo_score_weights')?->value ?? ['technical' => 20, 'on_page' => 25, 'content' => 10, 'indexability' => 20, 'schema' => 5, 'internal_linking' => 10, 'images' => 10];
        $categories = [];
        $weighted = 0;
        $totalWeight = 0;
        foreach ($weights as $category => $weight) {
            $applicable = array_filter($checks, fn ($c) => ($c['category'] ?? '') === $category && $c['severity'] !== 'information');
            if (! $applicable) {
                continue;
            }$score = round(count(array_filter($applicable, fn ($c) => $c['passed'])) / count($applicable) * 100, 1);
            $categories[$category] = $score;
            $weighted += $score * $weight;
            $totalWeight += $weight;
        }

        return ['overall' => $totalWeight ? round($weighted / $totalWeight, 1) : null, 'categories' => $categories, 'weights' => $weights, 'method' => 'Weighted category pass rates; informational checks excluded.'];
    }
}
