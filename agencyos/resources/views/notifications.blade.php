@extends('layouts.app')
@section('title','Notifications')
@section('description','Renewal reminders and operational updates for your agency account.')
@section('content')
<div class="card">@forelse($notifications as $notification)<div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 py-5"><div><a class="font-semibold hover:text-orange-600" href="{{ $notification->url }}">{{ $notification->title }}</a><p class="mt-2 text-sm text-gray-500">{{ $notification->message }}</p><p class="mt-2 text-xs text-gray-400">{{ $notification->created_at->format('d M Y H:i') }}</p></div>@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read',$notification) }}">@csrf @method('PATCH')<button class="btn-secondary">Mark read</button></form>@else<span class="badge">Read</span>@endif</div>@empty<p class="py-12 text-center text-sm text-gray-400">You’re all caught up.</p>@endforelse<div class="mt-5">{{ $notifications->links() }}</div></div>
@endsection
