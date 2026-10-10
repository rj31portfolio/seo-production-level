@extends('layouts.app')
@section('title', 'Your reports')
@section('description', $client->name.' - Reports shared by your agency.')
@section('content')
<div class="card">
@forelse($reports as $report)
<div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 py-5">
<div><a class="font-semibold text-orange-600" href="{{ route('portal.reports.show', $report) }}">{{ $report->title }}</a><p class="mt-2 text-xs text-gray-500">{{ $report->created_at->format('d M Y') }}</p></div>
<a class="btn-secondary" href="{{ route('portal.reports.excel', $report) }}">Download Excel</a>
</div>
@empty<p class="py-8 text-sm text-gray-500">Your agency has not shared any reports yet.</p>@endforelse
<div class="mt-5">{{ $reports->links() }}</div>
</div>
@endsection
