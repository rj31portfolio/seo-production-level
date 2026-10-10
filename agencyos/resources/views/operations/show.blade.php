@extends('layouts.app')
@section('title', $record->name)
@section('description', $definition['singular'].' details and operational information.')
@section('action')<div class="flex gap-2"><a class="btn-secondary" href="{{ route($module.'.index') }}">Back to list</a>@can('update',$record)<a class="btn" href="{{ route($module.'.edit',$record) }}">Edit {{ strtolower($definition['singular']) }}</a>@endcan</div>@endsection
@section('content')
<div class="grid gap-6 xl:grid-cols-3"><div class="card xl:col-span-2"><h2 class="mb-6 font-semibold">{{ $definition['singular'] }} information</h2><dl class="grid gap-6 md:grid-cols-2">@foreach($definition['fields'] as $key=>[$label,$type])<div class="{{ $type==='textarea' ? 'md:col-span-2' : '' }}"><dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</dt><dd class="mt-2 whitespace-pre-wrap break-words text-sm">@if($type==='client'){{ $record->client?->name ?? '—' }}@elseif($type==='website'){{ $record->website?->name ?? '—' }}@elseif($record->{$key} instanceof \DateTimeInterface){{ $record->{$key}->format('d M Y') }}@else{{ $record->{$key} ?: '—' }}@endif</dd></div>@endforeach</dl></div>
<div class="space-y-6">
@if($module === 'clients') @can('update', $record)
<section class="card"><h2 class="font-semibold">Client login</h2><p class="mt-3 text-sm text-gray-500">Create a separate login for this client to view their reports and backlinks. Share the login details with the client.</p>
<form class="mt-5 space-y-4" method="POST" action="{{ route('clients.login.store', $record) }}">@csrf
<div><label class="label" for="portal-email">Login email</label><input class="input" id="portal-email" name="email" type="email" required value="{{ old('email', $record->portalUser?->email ?? $record->email) }}"></div>
<div><label class="label" for="portal-password">{{ $record->portal_user_id ? 'New password (optional)' : 'Password' }}</label><input class="input" id="portal-password" name="password" type="password" autocomplete="new-password" @required(!$record->portal_user_id)><p class="mt-2 text-xs text-gray-500">At least 12 characters with uppercase, lowercase, and a number.</p></div>
<div><label class="label" for="portal-password-confirmation">Confirm password</label><input class="input" id="portal-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$record->portal_user_id)></div>
<input type="hidden" name="enabled" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $record->portalUser?->is_active ?? true))> Login enabled</label>
<button class="btn">Save client login</button><p class="text-xs text-gray-500">Sign-in page: {{ route('login') }}</p>
</form></section>
@endcan @endif
@if($module==='projects')<div class="card"><h2 class="mb-4 font-semibold">Assigned team</h2>@forelse($record->users as $employee)<p class="border-t border-gray-100 py-3 text-sm">{{ $employee->name }}</p>@empty<p class="text-sm text-gray-400">No team members assigned.</p>@endforelse</div>@endif
@if($module==='websites')<div class="card"><h2 class="font-semibold">Ownership verification</h2><p class="mt-3 text-sm text-gray-500">{{ $record->verified_at ? 'Verified on '.$record->verified_at->format('d M Y') : 'Ownership has not been verified.' }}</p><p class="mt-4 text-xs text-gray-400">Verification token</p><code class="mt-2 block break-all rounded-lg bg-gray-50 p-3 text-xs">{{ $record->verification_token }}</code></div>@endif
@can('delete',$record)<div class="card"><h2 class="font-semibold">Remove record</h2><p class="my-4 text-sm text-gray-500">Removal preserves this record in the database for historical references.</p><form action="{{ route($module.'.destroy',$record) }}" method="POST" @submit="if (!confirm('Remove this record?')) $event.preventDefault()">@csrf @method('DELETE')<button class="btn-secondary text-red-700">Remove {{ strtolower($definition['singular']) }}</button></form></div>@endcan
</div></div>
@endsection
