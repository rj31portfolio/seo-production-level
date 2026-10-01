<?php

namespace App\Services\Seo;

use App\Models\SeoToolRun;
use App\Tenancy\TenantContext;

class ReportBuilder
{
    /**
     * @return array{agency: string, project: ?string, source: string, collected_at: string, generated_at: string, target_url: ?string, summary: array, rows: array, analysis: array, missing_data: list<string>}
     */
    public function snapshot(SeoToolRun $run): array
    {
        $rows = [];
        foreach ($run->results()->lazyById(100) as $result) {
            $rows[] = ['url' => $result->url, 'kind' => $result->kind] + $result->data + ['metrics' => [], 'checks' => [], 'score' => null, 'error' => null, 'recommendations' => [], 'keywords' => [], 'notes' => []];
        }

        return [
            'agency' => app(TenantContext::class)->agency()->name,
            'project' => $run->project?->name,
            'source' => $run->source,
            'collected_at' => ($run->finished_at ?? $run->created_at)->toIso8601String(),
            'generated_at' => now()->toIso8601String(),
            'target_url' => $run->input['url'] ?? null,
            'summary' => $run->summary ?? [],
            'rows' => $rows,
            'analysis' => $this->analyze($rows),
            'missing_data' => [
                'Findings apply to the fetched sample, not every page on the website. Fetch failures are excluded from diagnostic scores.',
                'JavaScript is not rendered. Response time is a server fetch measurement; Core Web Vitals and browser performance were not measured.',
                'Rankings, search volumes, backlink authority and traffic are unavailable unless separately supplied.',
            ],
        ];
    }

    /**
     * @param  list<array>  $rows
     * @return array{analyzed_pages: int, fetch_failures: int, average_score: ?float, passed_checks: int, actionable_findings: int, review_findings: int, severity_counts: array<string, int>, categories: array, issues: list<array>, pages: list<array>}
     */
    private function analyze(array $rows): array
    {
        $pages = [];
        $scores = [];
        $categories = [];
        $issues = [];
        $severityCounts = array_fill_keys(['critical', 'high', 'medium', 'low', 'information'], 0);
        $passedChecks = 0;
        $fetchFailures = 0;
        $addIssue = function (string $rule, string $severity, string $category, string $recommendation, ?string $url) use (&$issues): void {
            $key = $category.'|'.$rule;
            $issues[$key] ??= ['rule' => $rule, 'severity' => $severity, 'category' => $category, 'recommendation' => $recommendation, 'urls' => []];
            $issues[$key]['urls'][$url ?? 'Supplied input'] = true;
        };

        foreach ($rows as $row) {
            if ($row['kind'] === 'fetch_error') {
                $fetchFailures++;
                $addIssue('fetch_unavailable', 'information', 'coverage', 'Review the fetch error and crawler access, then rerun this URL. No page measurements are available.', $row['url']);
            }
            $failedChecks = 0;
            foreach ($row['checks'] as $check) {
                $category = $check['category'] ?? 'seo';
                $categories[$category] ??= ['passed' => 0, 'failed' => 0, 'review' => 0, 'pass_rate' => null];
                if ($check['severity'] === 'information') {
                    if (! $check['passed']) {
                        $categories[$category]['review']++;
                    }
                } elseif ($check['passed']) {
                    $passedChecks++;
                    $categories[$category]['passed']++;
                } else {
                    $failedChecks++;
                    $categories[$category]['failed']++;
                }
                if (! $check['passed']) {
                    $addIssue($check['rule'], $check['severity'], $category, $check['recommendation'], $row['url']);
                }
            }
            if ($row['kind'] === 'page') {
                $score = $row['score']['overall'] ?? null;
                if (is_numeric($score)) {
                    $scores[] = (float) $score;
                }
                $pages[] = ['url' => $row['url'], 'title' => $row['title'] ?? '', 'description' => $row['description'] ?? '', 'score' => $score, 'failed_checks' => $failedChecks, 'metrics' => $row['metrics'], 'depth' => $row['depth'] ?? null];
            }
            foreach (['duplicate_titles' => 'Write a distinct, descriptive title for each page serving a different purpose.', 'duplicate_descriptions' => 'Write page-specific descriptions for pages with different content and intent.'] as $rule => $recommendation) {
                foreach ($row[$rule] ?? [] as $urls) {
                    foreach ($urls as $url) {
                        $addIssue($rule, 'medium', 'on_page', $recommendation, $url);
                    }
                }
            }
        }

        foreach ($categories as &$category) {
            $total = $category['passed'] + $category['failed'];
            $category['pass_rate'] = $total ? round($category['passed'] / $total * 100, 1) : null;
        }
        unset($category);
        $priority = array_flip(array_keys($severityCounts));
        foreach ($issues as &$issue) {
            $issue['urls'] = array_keys($issue['urls']);
            $issue['affected_pages'] = count($issue['urls']);
            $issue['owner'] = in_array($issue['category'], ['technical', 'indexability', 'schema', 'coverage'], true) ? 'Developer / technical SEO' : 'SEO / content team';
            $issue['timeframe'] = match ($issue['severity']) {
                'critical', 'high' => 'First: investigate blockers',
                'medium' => 'Next: improve page signals',
                'low' => 'Then: refine content and presentation',
                default => 'Review intent or collect missing evidence',
            };
            $severityCounts[$issue['severity']] += $issue['affected_pages'];
        }
        unset($issue);
        $issues = array_values($issues);
        usort($issues, fn (array $a, array $b): int => ($priority[$a['severity']] <=> $priority[$b['severity']]) ?: ($b['affected_pages'] <=> $a['affected_pages']) ?: strcmp($a['rule'], $b['rule']));
        usort($pages, fn (array $a, array $b): int => ($b['failed_checks'] <=> $a['failed_checks']) ?: strcmp((string) $a['url'], (string) $b['url']));

        return [
            'analyzed_pages' => count($pages),
            'fetch_failures' => $fetchFailures,
            'average_score' => $scores ? round(array_sum($scores) / count($scores), 1) : null,
            'passed_checks' => $passedChecks,
            'actionable_findings' => array_sum($severityCounts) - $severityCounts['information'],
            'review_findings' => $severityCounts['information'],
            'severity_counts' => $severityCounts,
            'categories' => $categories,
            'issues' => $issues,
            'pages' => $pages,
        ];
    }
}
