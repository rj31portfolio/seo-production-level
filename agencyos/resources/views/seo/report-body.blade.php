<style>
    .audit-report { color: #334155; line-height: 1.65; font-size: 13px; }
    .audit-report h1 { font-size: 28px; font-weight: 700; color: #0f172a; margin: 10px 0; }
    .audit-report h2 { font-size: 20px; font-weight: 700; color: #0f172a; margin: 28px 0 12px; }
    .audit-report h3 { font-size: 16px; font-weight: 600; margin: 14px 0 8px; }
    .audit-report p { margin: 8px 0; overflow-wrap: anywhere; word-wrap: break-word; }
    .audit-report .report-cover { border-bottom: 3px solid #ea580c; padding-bottom: 20px; }
    .audit-report .eyebrow { color: #c2410c; font-weight: 700; font-size: 12px; }
    .audit-report .muted { color: #64748b; font-size: 12px; }
    .audit-report .table-wrap { overflow-x: auto; margin: 12px 0; }
    .audit-report table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .audit-report th { background: #f1f5f9; color: #475569; font-weight: 600; }
    .audit-report th, .audit-report td { border-bottom: 1px solid #e2e8f0; padding: 10px; text-align: left; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
    .audit-report .kpis td { background: #fff7ed; width: 25%; }
    .audit-report .number { display: block; font-size: 24px; font-weight: 700; color: #9a3412; }
    .audit-report .issue { border-left: 3px solid #cbd5e1; padding: 4px 14px; margin: 18px 0; }
    .audit-report .priority-critical, .audit-report .priority-high { border-color: #dc2626; }
    .audit-report .priority-medium { border-color: #ea580c; }
    .audit-report .url { font-size: 11px; word-break: break-all; }
    .audit-report .page { margin-top: 24px; }
    @media print { .audit-report { font-size: 11px; } .audit-report thead { display: table-header-group; } .audit-report tr { page-break-inside: avoid; } .audit-report h2, .audit-report h3 { page-break-after: avoid; } }
</style>
<div class="audit-report">
<div class="report-cover">
    <p class="eyebrow">{{ $report->snapshot['agency'] }} / SEO REPORT</p>
    <h1>{{ $report->title }}</h1>
    <p>{{ $report->snapshot['project'] ?? 'Standalone analysis' }}</p>
    @if(!empty($report->snapshot['target_url']))<p class="url">Target: {{ $report->snapshot['target_url'] }}</p>@endif
    <p class="muted">Evidence collected: {{ $report->snapshot['collected_at'] }} · Source: {{ $report->snapshot['source'] }}</p>
    @if(!empty($report->snapshot['generated_at']))<p class="muted">Report saved: {{ $report->snapshot['generated_at'] }} · Snapshot #{{ $report->id }}</p>@endif
</div>
<h2>Executive summary</h2>
<p>This report summarizes collected evidence and recommended work. Findings apply to the analyzed sample. Review page intent before changing indexing, canonical tags or content.</p>
@if(isset($report->snapshot['analysis']))
    @php($analysis = $report->snapshot['analysis'])
    @if($analysis['analyzed_pages'] > 0)
        <div class="table-wrap"><table class="kpis"><tbody><tr>
            <td><span class="number">{{ $analysis['average_score'] ?? 'N/A' }}</span>Average diagnostic score / 100</td>
            <td><span class="number">{{ $analysis['analyzed_pages'] }}</span>Pages analyzed</td>
            <td><span class="number">{{ $analysis['actionable_findings'] }}</span>Actionable findings across URLs</td>
            <td><span class="number">{{ $analysis['fetch_failures'] }}</span>Fetch failures</td>
        </tr></tbody></table></div>
        <p>{{ $analysis['passed_checks'] }} actionable checks passed. {{ $analysis['review_findings'] }} findings need intent review or additional evidence.</p>
        <p class="muted">Scores average available page diagnostic scores. Missing scores are excluded. Counts represent unique rule/URL findings; a page can have several findings.</p>
    @endif
    @if($analysis['issues'])
        <h3>Finding priorities</h3>
        <div class="table-wrap"><table><thead><tr><th>Critical</th><th>High</th><th>Medium</th><th>Low</th><th>Review / unavailable</th></tr></thead><tbody><tr>@foreach($analysis['severity_counts'] as $count)<td>{{ $count }}</td>@endforeach</tr></tbody></table></div>
    @endif
    @if($analysis['categories'])
        <h2>Category health</h2>
        <div class="table-wrap"><table><thead><tr><th>Category</th><th>Pass rate</th><th>Passed</th><th>Failed</th><th>Review</th></tr></thead><tbody>
            @foreach($analysis['categories'] as $category => $health)<tr><td>{{ ucwords(str_replace('_', ' ', $category)) }}</td><td>{{ $health['pass_rate'] === null ? 'Not scored' : $health['pass_rate'].'%' }}</td><td>{{ $health['passed'] }}</td><td>{{ $health['failed'] }}</td><td>{{ $health['review'] }}</td></tr>@endforeach
        </tbody></table></div>
        <p class="muted">Category pass rates exclude informational checks. Cross-page duplicate findings appear in the action plan separately from page scoring.</p>
    @endif
    @if($analysis['issues'])
        <h2>Prioritized action plan</h2>
        @foreach($analysis['issues'] as $issue)
            <div class="issue priority-{{ $issue['severity'] }}">
                <h3>{{ $loop->iteration }}. {{ ucwords(str_replace('_', ' ', $issue['rule'])) }}</h3>
                <p><strong>{{ ucfirst($issue['severity']) }}</strong> · {{ $issue['affected_pages'] }} affected URL(s) · {{ ucwords(str_replace('_', ' ', $issue['category'])) }}</p>
                <p>{{ $issue['recommendation'] }}</p>
                <p class="muted">Suggested owner: {{ $issue['owner'] }} · {{ $issue['timeframe'] }}</p>
                @foreach($issue['urls'] as $url)<p class="url">{{ $url }}</p>@endforeach
            </div>
        @endforeach
    @elseif($analysis['analyzed_pages'] > 0)
        <p>No failed checks were found in the collected sample. Continue reviewing content quality and signals outside the scope of this audit.</p>
    @endif
    @if($analysis['pages'])
        <h2>Page inventory</h2>
        <div class="table-wrap"><table><thead><tr><th>Page / title</th><th>Score</th><th>Failed checks</th><th>Words</th><th>Fetch time</th></tr></thead><tbody>
            @foreach($analysis['pages'] as $page)<tr><td><p class="url">{{ $page['url'] }}</p>{{ $page['title'] ?: 'Title unavailable' }}</td><td>{{ $page['score'] ?? 'N/A' }}</td><td>{{ $page['failed_checks'] }}</td><td>{{ $page['metrics']['word_count'] ?? 'N/A' }}</td><td>{{ isset($page['metrics']['response_ms']) ? $page['metrics']['response_ms'].' ms' : 'N/A' }}</td></tr>@endforeach
        </tbody></table></div>
    @endif
@endif
<h2>Collection overview</h2>
@foreach($report->snapshot['summary'] ?? [] as $key=>$value)@if(!is_array($value))<p><strong>{{ ucwords(str_replace('_',' ',$key)) }}:</strong> {{ $value }}</p>@endif @endforeach
<h2>Detailed evidence</h2>
@foreach($report->snapshot['rows'] as $row)<section class="page"><h3>{{ $row['url'] ?? ucwords(str_replace('_', ' ', $row['kind'] ?? 'Supplied input')) }}</h3>@if($row['score'])<p>Internal diagnostic score: {{ $row['score']['overall'] ?? 'Not available' }}</p><p class="muted">{{ $row['score']['method'] ?? 'Weighted category pass rates; informational checks excluded.' }}</p>@foreach($row['score']['categories'] ?? [] as $category => $score)<p class="muted">{{ ucwords(str_replace('_', ' ', $category)) }}: {{ $score }} / 100 · weight {{ $row['score']['weights'][$category] ?? 'N/A' }}</p>@endforeach @endif
@if(array_key_exists('title', $row))<p><strong>Title:</strong> {{ $row['title'] ?: 'Missing' }}</p>@endif
@if(array_key_exists('description', $row))<p><strong>Meta description:</strong> {{ $row['description'] ?: 'Missing' }}</p>@endif
@if(array_key_exists('robots', $row))<p><strong>Robots directives:</strong> {{ $row['robots'] ?: 'None detected' }}</p>@endif
@if(array_key_exists('canonicals', $row))<p class="url"><strong>Declared canonicals:</strong> {{ implode(', ', array_filter($row['canonicals'])) ?: 'None valid' }}</p>@endif
@foreach(['duplicate_titles' => 'Duplicate titles', 'duplicate_descriptions' => 'Duplicate descriptions'] as $field => $label)@if(!empty($row[$field]))<h3>{{ $label }}</h3>@foreach($row[$field] as $text => $urls)<p><strong>{{ $text }}</strong></p>@foreach($urls as $url)<p class="url">{{ $url }}</p>@endforeach @endforeach @endif @endforeach
@foreach($row['metrics'] as $key=>$value)@if(!is_array($value))<p>{{ ucwords(str_replace('_',' ',$key)) }}: {{ $value === null ? 'Not available' : (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) }}</p>@endif @endforeach
@if($row['error'])<p>Data unavailable: {{ $row['error'] }}</p>@endif @if($row['checks'])<table><thead><tr><th>Finding</th><th>Status</th><th>Recommendation</th></tr></thead><tbody>@foreach($row['checks'] as $check)<tr><td>{{ ucwords(str_replace('_',' ',$check['rule'])) }}</td><td>{{ $check['passed'] ? 'Passed' : ucfirst($check['severity']) }}</td><td>{{ $check['recommendation'] }}</td></tr>@endforeach</tbody></table>@endif</section>@endforeach
@foreach($report->snapshot['rows'] as $row) @foreach($row['recommendations'] ?? [] as $recommendation)<p>{{ $recommendation }}</p>@endforeach @if(!empty($row['keywords']))<h2>Keyword analysis</h2><table><thead><tr><th>Keyword</th><th>Intent</th><th>Cluster</th></tr></thead><tbody>@foreach($row['keywords'] as $keyword)<tr><td>{{ $keyword['keyword'] }}</td><td>{{ $keyword['intent'] }}</td><td>{{ $keyword['cluster'] }}</td></tr>@endforeach</tbody></table>@endif @foreach($row['notes'] ?? [] as $note)<p>{{ $note }}</p>@endforeach @endforeach
<h2>Data scope and next steps</h2>@foreach($report->snapshot['missing_data'] as $note)<p>{{ $note }}</p>@endforeach<p>SEO AgencyOS Score is an internal diagnostic score and is not an official Google score. Review recommendations before implementation and rerun an audit after changes.</p>
</div>
