@extends('layouts.app')
@section('title', 'Your backlinks')
@section('description', $client->name.' - Backlinks recorded by your agency.')
@section('action')<a class="btn" href="{{ route('portal.backlinks.excel') }}">Download Excel</a>@endsection
@section('content')
<div class="card"><div class="overflow-x-auto"><table class="w-full"><thead><tr>@foreach(['Project', 'Source URL', 'Target URL', 'Anchor', 'Campaign', 'Status'] as $heading)<th class="table-th">{{ $heading }}</th>@endforeach</tr></thead><tbody>
@forelse($backlinks as $backlink)<tr><td class="table-td">{{ $backlink->project->name }}</td><td class="table-td break-all">{{ $backlink->source_url }}</td><td class="table-td break-all">{{ $backlink->target_url }}</td><td class="table-td">{{ $backlink->anchor ?: 'Not supplied' }}</td><td class="table-td">{{ $backlink->campaign ?: 'Not supplied' }}</td><td class="table-td"><span class="badge">{{ ucwords(str_replace('_', ' ', $backlink->status)) }}</span></td></tr>
@empty<tr><td class="table-td" colspan="6">No backlinks have been recorded yet.</td></tr>@endforelse
</tbody></table></div><div class="mt-5">{{ $backlinks->links() }}</div></div>
@endsection
