@extends('layouts.app')
@section('title',$report->title)
@section('description','Saved report #'.$report->id.' · Source: '.$report->snapshot['source'])
@section('action')<div class="flex gap-2"><a class="btn-secondary" href="{{ route('seo.reports.html',$report) }}">Download HTML</a><a class="btn" href="{{ route('seo.reports.pdf',$report) }}">Download PDF</a></div>@endsection
@section('content')<div class="card">@include('seo.report-body')</div>@endsection
