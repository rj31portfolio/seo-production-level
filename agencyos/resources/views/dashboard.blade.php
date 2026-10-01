@extends('layouts.app')
@section('title', 'Agency overview')
@section('description', 'Your team and operations at a glance. All figures come from your agency records.')
@section('action')@can('agency.manage')<a class="btn-secondary" href="{{ route('agency.settings') }}">Manage agency ↗</a>@endcan @endsection
@section('content')
<div class="mb-7 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">@foreach($counts as $label => $value)<div class="card"><p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-4 text-4xl font-semibold">{{ number_format($value) }}</p><p class="mt-3 text-xs text-gray-400">Current agency records</p></div>@endforeach</div>
<div class="grid gap-6 xl:grid-cols-3">
    <div class="card xl:col-span-2"><div class="mb-6 flex justify-between"><h2 class="font-semibold">Recent activity</h2>@can('activity.view')<a href="{{ route('activity') }}" class="text-sm text-orange-600">View all ↗</a>@endcan</div>
    @forelse($activity as $event)<div class="flex items-start gap-4 border-t border-gray-100 py-4"><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-orange-500"></span><div class="flex-1"><p class="text-sm font-medium">{{ str_replace(['.', '_'], ' ', $event->action) }}</p><p class="mt-1 text-xs text-gray-400">{{ $event->user?->name ?? 'System' }} · {{ $event->created_at->timezone($agency->timezone)->format('d M Y, H:i') }}</p></div></div>@empty<p class="py-12 text-center text-sm text-gray-400">Your agency activity will appear here.</p>@endforelse
    </div>
    <div class="card"><span class="badge">WORKSPACE STATUS</span><h2 class="mt-5 text-xl font-semibold">Ready for your team</h2><p class="mt-3 text-sm leading-6 text-gray-500">Your agency has an isolated workspace. Manage access and keep agency settings up to date.</p><div class="mt-6 space-y-4 text-sm"><div class="flex justify-between"><span class="text-gray-500">Status</span><span class="text-green-700">Active</span></div><div class="flex justify-between"><span class="text-gray-500">Timezone</span><span>{{ $agency->timezone }}</span></div><div class="flex justify-between"><span class="text-gray-500">Currency</span><span>{{ $agency->currency }}</span></div></div></div>
</div>
@endsection
