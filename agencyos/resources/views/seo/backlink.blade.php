@extends('layouts.app')
@section('title','Backlink verification history')
@section('description',$backlink->project->name.' · '.$backlink->source)
@section('action')<a class="btn-secondary" href="{{ route('seo.backlinks.index') }}">Backlink list</a>@endsection
@section('content')
<div class="card mb-6"><dl class="space-y-3 text-sm"><div class="break-all"><dt class="text-gray-400">Source URL</dt><dd>{{ $backlink->source_url }}</dd></div><div class="break-all"><dt class="text-gray-400">Target URL</dt><dd>{{ $backlink->target_url }}</dd></div><div><dt class="text-gray-400">Expected anchor</dt><dd>{{ $backlink->anchor ?? 'Not supplied' }}</dd></div></dl><p class="mt-4"><span class="badge">{{ $backlink->status }}</span></p><form class="mt-5" method="POST" action="{{ route('seo.backlinks.verify',$backlink) }}">@csrf<button class="btn">Queue verification</button></form></div>
@forelse($verifications as $verification)<div class="card mb-5"><p class="text-sm font-medium">{{ ucfirst($verification->status) }} · {{ $verification->created_at->format('d M Y H:i') }}</p>@if($verification->error)<p class="mt-3 text-sm text-red-700">{{ $verification->error }}</p>@endif @if($verification->data)<p class="mt-3 text-sm">HTTP {{ $verification->data['http_status'] }} · {{ $verification->data['status'] }}</p>@foreach($verification->data['matches'] as $match)<p class="mt-3 text-sm">Anchor: {{ $match['anchor'] }} · rel: {{ $match['rel'] ?: 'No relation restriction detected' }}</p>@endforeach<p class="mt-4 text-xs leading-6 text-gray-500">{{ $verification->data['note'] }}</p>@endif</div>@empty<div class="card">No verification history. Queue a check to inspect a permitted source page.</div>@endforelse
{{ $verifications->links() }}
@endsection
