<?php

namespace App\Services\Seo;

use App\Models\InAppNotification;
use App\Models\SeoMonitoring;
use App\Models\SeoToolRun;

class MonitoringService
{
    public function completed(SeoToolRun $run): void
    {
        $monitor = SeoMonitoring::where('pending_run_id', $run->id)->first();
        if (! $monitor) {
            return;
        }$previous = $monitor->last_run_id ? SeoToolRun::withTrashed()->find($monitor->last_run_id) : null;
        $old = $previous?->results()->where('kind', 'page')->first()?->data;
        $new = $run->results()->where('kind', 'page')->first()?->data;
        $changed = [];
        if ($old && $new) {
            foreach (['title', 'description', 'robots', 'canonicals', 'headings', 'content_hash'] as $field) {
                if (($old[$field] ?? null) !== ($new[$field] ?? null)) {
                    $changed[] = $field;
                }
            }
        }
        if ($run->status === 'failed' || $changed) {
            InAppNotification::create(['user_id' => $monitor->user_id, 'title' => $run->status === 'failed' ? 'Website monitoring check failed' : 'Website changes detected', 'message' => $run->status === 'failed' ? 'The page could not be verified. Review run #'.$run->id.'.' : 'Changed signals: '.implode(', ', $changed).'. Review run #'.$run->id.'.', 'url' => route('seo.runs.show', $run)]);
        }
        $monitor->update(['pending_run_id' => null, 'last_run_id' => $run->status === 'completed' ? $run->id : $monitor->last_run_id, 'last_checked_at' => now(), 'last_error' => $run->error]);
    }
}
