@extends('layouts.app')
@section('title','SEO reports')
@section('description','Saved report snapshots generated from collected tool findings.')
@section('content')
<div class="card">@forelse($reports as $report)<div class="flex flex-wrap justify-between gap-3 border-b border-gray-100 py-4"><a class="font-semibold text-orange-600" href="{{ route('seo.reports.show',$report) }}">{{ $report->title }}</a><span class="text-sm text-gray-400">{{ $report->created_at->format('d M Y H:i') }}</span>@can('seo_tools.delete') @if(!in_array($report->pdf_status, ['queued', 'running']))<form method="POST" action="{{ route('seo.reports.destroy', $report) }}" onsubmit="return confirm('Permanently delete this report and its PDF?')">@csrf @method('DELETE')<button class="btn-secondary text-red-700">Delete report</button></form>@endif @endcan</div>@empty<p class="py-12 text-center text-sm text-gray-400">No reports yet. Generate one from a completed tool run.</p>@endforelse<div class="mt-5">{{ $reports->links() }}</div></div>
@endsection
