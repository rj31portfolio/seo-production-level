@extends('layouts.app')
@section('title', 'Activity log')
@section('description', 'An audit trail of changes within your agency.')
@section('content')
<div class="card"><form class="mb-5 flex gap-2"><input name="q" class="input max-w-sm" value="{{ request('q') }}" placeholder="Search actions" aria-label="Search actions"><button class="btn-secondary">Search</button></form>
<div class="overflow-x-auto"><table class="w-full"><thead><tr>@foreach(['Action','User','Date','Object'] as $head)<th class="table-th">{{ $head }}</th>@endforeach</tr></thead><tbody>@forelse($logs as $log)<tr><td class="table-td">{{ $log->action }}</td><td class="table-td">{{ $log->user?->name ?? 'System' }}</td><td class="table-td">{{ $log->created_at->timezone(app(\App\Tenancy\TenantContext::class)->agency()->timezone)->format('d M Y H:i') }}</td><td class="table-td">{{ class_basename($log->subject_type ?? '') }} {{ $log->subject_id }}</td></tr>@empty<tr><td class="table-td text-center" colspan="4">No activity found.</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $logs->links() }}</div></div>
@endsection
