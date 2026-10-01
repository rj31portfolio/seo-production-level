<?php

namespace App\Services\Seo;

class LocalAnalyzer
{
    public function analyze(string $tool, array $input): array
    {
        return match ($tool) {
            'url-analyzer' => $this->url($input['url']),
            'content-analyzer' => $this->content($input['content'], $input['keyword'] ?? ''),
        };
    }

    public function content(string $content, string $keyword = ''): array
    {
        $clean = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $content);
        $clean = preg_replace('~</?(?:p|div|h[1-6]|li|ul|ol|section|article|br|table|tr|td)\b[^>]*>~i', ' ', $clean);
        $text = html_entity_decode(strip_tags($clean), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('/[\p{L}\p{N}]+(?:[’\x27-][\p{L}\p{N}]+)*/u', $text, $words);
        $paragraphs = preg_split('/\n\s*\n/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        preg_match_all('~<h([1-6])\b[^>]*>(.*?)</h\1>~is', $content, $headings, PREG_SET_ORDER);
        $occurrences = $keyword !== '' ? preg_match_all('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?![\p{L}\p{N}])/iu', $text) : null;
        $frequency = array_count_values(array_map(fn ($w) => mb_strtolower($w), $words[0]));
        arsort($frequency);
        $terms = [];
        foreach ($frequency as $term => $count) {
            if (mb_strlen((string) $term) >= 4) {
                $terms[] = ['term' => (string) $term, 'occurrences' => $count];
            } if (count($terms) >= 25) {
                break;
            }
        }

        return ['metrics' => ['word_count' => count($words[0]), 'paragraph_count' => preg_match_all('~<p\b~i', $content) ?: count($paragraphs), 'character_count' => mb_strlen($text), 'keyword_occurrences' => $occurrences, 'heading_count' => count($headings), 'image_count' => preg_match_all('~<img\b~i', $content), 'link_count' => preg_match_all('~<a\b~i', $content)], 'headings' => array_map(fn ($h) => ['level' => (int) $h[1], 'text' => trim(strip_tags($h[2]))], $headings), 'terms' => $terms, 'notes' => ['Counts describe the supplied content only. Keyword density and word count are not official Google ranking requirements.', 'Extracted terms are frequency-based topic candidates, not search-volume or entity measurements.']];
    }

    public function url(string $url): array
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $checks = [
            ['rule' => 'https', 'passed' => ($parts['scheme'] ?? '') === 'https', 'severity' => 'high', 'recommendation' => 'Use HTTPS for secure transport.'],
            ['rule' => 'parameters', 'passed' => empty($parts['query']), 'severity' => 'information', 'recommendation' => 'Review parameter URLs for duplicate content and crawl scope.'],
            ['rule' => 'uppercase_path', 'passed' => $path === mb_strtolower($path), 'severity' => 'low', 'recommendation' => 'Keep URL path conventions consistent.'],
            ['rule' => 'url_length', 'passed' => mb_strlen($url) <= 100, 'severity' => 'information', 'recommendation' => 'Prefer descriptive URLs. This length threshold is a diagnostic heuristic.'],
            ['rule' => 'underscores', 'passed' => ! str_contains($path, '_'), 'severity' => 'low', 'recommendation' => 'Consider readable hyphen-separated words.'],
            ['rule' => 'encoded_characters', 'passed' => ! str_contains($path, '%'), 'severity' => 'information', 'recommendation' => 'Review encoded path characters; they are not inherently invalid.'],
        ];

        return ['metrics' => ['url_length' => mb_strlen($url), 'host' => $parts['host'] ?? '', 'path' => $path, 'https' => ($parts['scheme'] ?? '') === 'https', 'has_query' => isset($parts['query'])], 'checks' => $checks, 'notes' => ['This tool inspects the supplied URL string; it does not establish HTTP status or indexing behavior.']];
    }
}
