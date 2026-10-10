@extends('layouts.app')
@section('title', $report->title)
@section('description', 'Your saved SEO report.')
@section('action')<div class="flex flex-wrap gap-3"><a class="btn-secondary" href="{{ route('portal.reports.index') }}">All reports</a><a class="btn" href="{{ route('portal.reports.excel', $report) }}">Download Excel</a>@if($report->pdf_status === 'completed')<a class="btn-secondary" href="{{ route('portal.reports.pdf', $report) }}">Download PDF</a>@endif</div>@endsection
@section('content')<div class="card">@include('seo.report-body')</div>@endsection
