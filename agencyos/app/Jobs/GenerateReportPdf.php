<?php

namespace App\Jobs;

use App\Models\Agency;
use App\Models\Report;
use App\Models\User;
use App\Tenancy\TenantContext;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateReportPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public function __construct(public int $agencyId, public int $reportId)
    {
        $this->onQueue('seo');
    }

    public function handle(TenantContext $context): void
    {
        $agency = Agency::findOrFail($this->agencyId);
        $context->run($agency, function (): void {
            $report = DB::transaction(function (): ?Report {
                $report = Report::whereKey($this->reportId)->lockForUpdate()->firstOrFail();
                if ($report->pdf_status !== 'queued') {
                    return null;
                }$report->update(['pdf_status' => 'running']);

                return $report;
            });
            if (! $report) {
                return;
            }$previous = Auth::user();
            try {
                Auth::setUser(User::findOrFail($report->pdf_requested_by));
                Gate::authorize('seo_tools.reports');
                Gate::authorize('view', $report->run);
                $options = new Options;
                $options->set('isRemoteEnabled', false);
                $options->set('isJavascriptEnabled', false);
                $options->set('isPhpEnabled', false);
                $pdf = new Dompdf($options);
                $pdf->loadHtml(view('seo.report-document', compact('report'))->render());
                $pdf->setPaper('A4');
                $pdf->render();
                $path = 'reports/'.$this->agencyId.'/'.$report->id.'/'.Str::uuid().'.pdf';
                if (! Storage::disk('local')->put($path, $pdf->output())) {
                    throw new \RuntimeException('Private PDF storage failed.');
                }$old = $report->pdf_path;
                $report->update(['pdf_status' => 'completed', 'pdf_path' => $path, 'pdf_error' => null]);
                if ($old) {
                    Storage::disk('local')->delete($old);
                }
            } catch (\Throwable) {
                $report->update(['pdf_status' => 'failed', 'pdf_error' => 'PDF generation failed. Review access and private worker logs, then retry.']);
            } finally {
                $previous ? Auth::setUser($previous) : Auth::forgetUser();
            }
        });
    }

    public function failed(?\Throwable $exception): void
    {
        $agency = Agency::find($this->agencyId);
        if (! $agency) {
            return;
        }app(TenantContext::class)->run($agency, fn () => Report::whereKey($this->reportId)->whereIn('pdf_status', ['queued', 'running'])->update(['pdf_status' => 'failed', 'pdf_error' => 'PDF worker failed or exceeded its time limit.']));
    }
}
