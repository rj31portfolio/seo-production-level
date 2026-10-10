@extends('layouts.app')
@section('title',$report->title)
@section('description','Saved report #'.$report->id.' · Source: '.$report->snapshot['source'])
@section('action')<div class="flex flex-wrap gap-2"><a class="btn-secondary" href="{{ route('seo.reports.html',$report) }}">Download HTML</a>@if($report->pdf_status==='completed')<a class="btn" href="{{ route('seo.reports.pdf',$report) }}">Download PDF</a>@elseif(!in_array($report->pdf_status,['queued','running']))<form method="POST" action="{{ route('seo.reports.prepare-pdf',$report) }}">@csrf<button class="btn">Prepare PDF</button></form>@else<span class="badge">PDF {{ $report->pdf_status }}</span>@endif</div>@endsection
@section('content') @if(in_array($report->pdf_status,['queued','running']))<div class="card mb-5" x-data x-init="setTimeout(()=>location.reload(),5000)"><p class="text-sm text-gray-500">PDF preparation runs on the queue. This page refreshes while generation is pending.</p></div>@endif @if($report->pdf_error)<p class="card mb-5 text-sm text-red-700">{{ $report->pdf_error }}</p>@endif <div class="card">@include('seo.report-body')</div>
@can('seo_tools.delete')
@if(!in_array($report->pdf_status, ['queued', 'running']))
<form class="mt-5" method="POST" action="{{ route('seo.reports.destroy', $report) }}" onsubmit="return confirm('Permanently delete this report and its PDF?')">@csrf @method('DELETE')<button class="btn-secondary text-red-700">Delete report</button></form>
@endif
@endcan
@endsection
