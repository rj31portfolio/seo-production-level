@extends('layouts.app')
@section('title','Client expiry rules')
@section('description','Configure service expiry labels and notification thresholds across the platform.')
@section('action')<a class="btn-secondary" href="{{ route('super-admin') }}">Back to platform</a>@endsection
@section('content')
<form class="card max-w-xl space-y-5" method="POST">@csrf @method('PATCH')@foreach(['renewal_days'=>'Renewal soon: days remaining','expiring_days'=>'Expiring: days remaining','urgent_days'=>'Urgent: days remaining'] as $key=>$label)<div><label class="label" for="{{ $key }}">{{ $label }}</label><input class="input" type="number" id="{{ $key }}" name="{{ $key }}" required min="0" max="365" value="{{ old($key,$rules[$key]) }}"></div>@endforeach<div><label class="label" for="reminders">Reminder days, comma-separated</label><input class="input" id="reminders" name="reminders" required value="{{ old('reminders',implode(',',$rules['reminders'])) }}"></div><button class="btn">Save expiry rules</button></form>
@endsection
