@extends('layouts.app')
@section('title','Add client subscription')
@section('description','Set up a service period and generate its initial invoice.')
@section('action')<a class="btn-secondary" href="{{ route('client-subscriptions.index') }}">Back to subscriptions</a>@endsection
@section('content')
@if($clients->isEmpty() || $plans->isEmpty())<div class="card"><p class="mb-4 text-sm text-gray-500">You need a client without a subscription and an active client SEO plan.</p><div class="flex gap-3"><a class="btn" href="{{ route('client-plans.index') }}">Manage SEO plans</a><a class="btn-secondary" href="{{ route('clients.index') }}">Manage clients</a></div></div>@else
<form class="card max-w-3xl" method="POST" action="{{ route('client-subscriptions.store') }}" x-data="{ saving:false }" @submit="saving=true">@csrf<div class="grid gap-5 sm:grid-cols-2">
<div><label class="label" for="client_id">Client</label><select class="input" id="client_id" name="client_id" required>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('client_id')==$client->id)>{{ $client->name }}</option>@endforeach</select></div>
<div><label class="label" for="client_plan_id">SEO plan</label><select class="input" id="client_plan_id" name="client_plan_id" required>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(old('client_plan_id')==$plan->id)>{{ $plan->name }} · {{ $plan->currency }} {{ number_format($plan->price_minor/100,2) }}</option>@endforeach</select></div>
@foreach(['starts_at'=>'Service starts','expires_at'=>'Service expires'] as $key=>$label)<div><label for="{{ $key }}" class="label">{{ $label }} (agency timezone)</label><input class="input" type="datetime-local" id="{{ $key }}" name="{{ $key }}" value="{{ old($key) }}" required></div>@endforeach
<div><label class="label" for="expiry_mode">Expiry behavior</label><select class="input" id="expiry_mode" name="expiry_mode">@foreach(['read_only'=>'Read only','grace'=>'Grace period','suspend_operations'=>'Suspend operations','full_suspension'=>'Full service suspension'] as $value=>$label)<option value="{{ $value }}" @selected(old('expiry_mode')===$value)>{{ $label }}</option>@endforeach</select></div><div><label class="label" for="grace_days">Grace period days</label><input class="input" type="number" min="0" max="365" name="grace_days" id="grace_days" value="{{ old('grace_days',0) }}" required></div>
<div class="sm:col-span-2"><label class="label" for="notes">Notes</label><textarea class="input" name="notes" id="notes" rows="3">{{ old('notes') }}</textarea></div></div><button class="btn mt-6" :disabled="saving" x-text="saving ? 'Creating…' : 'Create subscription and invoice'">Create subscription and invoice</button></form>
@endif
@endsection
