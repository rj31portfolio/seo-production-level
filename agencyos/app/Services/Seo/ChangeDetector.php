<?php

namespace App\Services\Seo;

use App\Models\Project;
use App\Models\SeoToolRun;
use Illuminate\Support\Facades\Gate;

class ChangeDetector
{
    public function analyze(array $input, ?Project $project): array
    {
        $before = SeoToolRun::findOrFail($input['before_run_id']);
        $after = SeoToolRun::findOrFail($input['after_run_id']);
        foreach ([$before, $after] as $run) {
            Gate::authorize('view', $run);
            abort_unless($run->status === 'completed' && $run->source === 'Internal crawler' && $run->project_id === $project?->id, 422, 'Use completed crawler runs from the selected project.');
        }
        $old = $before->results()->where('kind', 'page')->get()->keyBy('url');
        $new = $after->results()->where('kind', 'page')->get()->keyBy('url');
        abort_unless($old->count() && $new->count(), 422, 'Both runs need collected HTML pages.');
        $changes = [];
        foreach ($old as $url => $row) {
            if (! isset($new[$url])) {
                $changes[] = ['url' => $url, 'field' => 'not_collected_in_later_sample', 'before' => 'Collected', 'after' => 'Not collected'];

                continue;
            }foreach (['title', 'description', 'headings', 'canonicals', 'robots', 'content_hash'] as $field) {
                $previous = $row->data[$field] ?? null;
                $next = $new[$url]->data[$field] ?? null;
                if ($previous !== $next) {
                    $changes[] = ['url' => $url, 'field' => $field, 'before' => $previous, 'after' => $next];
                }
            }
        }
        foreach ($new as $url => $row) {
            if (! isset($old[$url])) {
                $changes[] = ['url' => $url, 'field' => 'new_to_collected_sample', 'before' => 'Not collected', 'after' => 'Collected'];
            }
        }

        return ['metrics' => ['before_pages' => $old->count(), 'after_pages' => $new->count(), 'changes' => count($changes)], 'changes' => $changes, 'notes' => ['Changes compare stored crawl samples. A page missing from a sample is not proof it was removed from the website.', 'A changed content hash establishes a difference in extracted text, not the cause or SEO impact.']];
    }
}
