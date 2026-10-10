<?php

namespace App\Http\Controllers;

use App\Models\Backlink;
use App\Models\Client;
use App\Models\Report;
use App\Services\Seo\SpreadsheetExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientPortalController extends Controller
{
    private function client(Request $request): Client
    {
        return Client::where('portal_user_id', $request->user()->id)->firstOrFail();
    }

    private function authorizeReport(Request $request, Report $report): void
    {
        abort_unless($report->run?->client_id === $this->client($request)->id, 404);
    }

    public function reports(Request $request): View
    {
        $client = $this->client($request);
        $reports = Report::whereHas('run', fn ($query) => $query->where('client_id', $client->id))->latest()->paginate(20);

        return view('portal.reports', compact('client', 'reports'));
    }

    public function report(Request $request, Report $report): View
    {
        $this->authorizeReport($request, $report);

        return view('portal.report', compact('report'));
    }

    public function pdf(Request $request, Report $report): BinaryFileResponse
    {
        $this->authorizeReport($request, $report);
        abort_unless($report->pdf_status === 'completed' && $report->pdf_path && Storage::disk('local')->exists($report->pdf_path), 409, 'The agency has not prepared this PDF yet.');

        return response()->download(Storage::disk('local')->path($report->pdf_path), 'report-'.$report->id.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function reportExcel(Request $request, Report $report, SpreadsheetExport $excel): StreamedResponse
    {
        $this->authorizeReport($request, $report);

        return $excel->download('report-'.$report->id.'.xlsx', ['url', 'section', 'item', 'value'], function () use ($report): iterable {
            yield ['', 'Report', 'Title', $report->title];
            yield ['', 'Report', 'Source', $report->snapshot['source'] ?? ''];
            yield ['', 'Report', 'Collected at', $report->snapshot['collected_at'] ?? ''];
            foreach ($report->snapshot['rows'] ?? [] as $row) {
                foreach ($row['metrics'] ?? [] as $key => $value) {
                    yield [$row['url'] ?? '', 'Metrics', $key, is_array($value) ? json_encode($value) : $value];
                }
                foreach ($row['checks'] ?? [] as $check) {
                    yield [$row['url'] ?? '', 'Checks', $check['rule'], ($check['passed'] ? 'Passed' : $check['severity']).': '.$check['recommendation']];
                }
                foreach ($row['recommendations'] ?? [] as $index => $recommendation) {
                    yield [$row['url'] ?? '', 'Recommendations', $index + 1, $recommendation];
                }
                foreach (array_diff_key($row, array_flip(['url', 'kind', 'metrics', 'checks', 'recommendations'])) as $key => $value) {
                    if ($value !== null && $value !== []) {
                        yield [$row['url'] ?? '', 'Collected data', $key, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $value];
                    }
                }
            }
        });
    }

    public function backlinks(Request $request): View
    {
        $client = $this->client($request);
        $backlinks = Backlink::whereHas('project', fn ($query) => $query->where('client_id', $client->id))->with('project')->latest()->paginate(20);

        return view('portal.backlinks', compact('client', 'backlinks'));
    }

    public function backlinksExcel(Request $request, SpreadsheetExport $excel): StreamedResponse
    {
        $client = $this->client($request);

        return $excel->download('backlinks.xlsx', ['project', 'source_url', 'target_url', 'anchor', 'campaign', 'status'], function () use ($client): iterable {
            foreach (Backlink::whereHas('project', fn ($query) => $query->where('client_id', $client->id))->with('project')->lazyById(100) as $backlink) {
                yield [$backlink->project->name, $backlink->source_url, $backlink->target_url, $backlink->anchor, $backlink->campaign, $backlink->status];
            }
        });
    }
}
