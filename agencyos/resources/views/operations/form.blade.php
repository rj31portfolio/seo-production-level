@extends('layouts.app')
@section('title', ($record->exists ? 'Edit ' : 'Add ').strtolower($definition['singular']))
@section('description', $definition['description'])
@section('action')<a class="btn-secondary" href="{{ route($module.'.index') }}">Back to {{ strtolower($definition['label']) }}</a>@endsection
@section('content')
<form class="card max-w-4xl" method="POST" action="{{ $record->exists ? route($module.'.update',$record) : route($module.'.store') }}" x-data="{ saving: false }" @submit="saving = true">@csrf @if($record->exists) @method('PUT') @endif
<div class="grid gap-5 md:grid-cols-2">
@foreach($definition['fields'] as $key=>[$label,$type,$rules])
@php($value=old($key, $record->{$key} instanceof \DateTimeInterface ? $record->{$key}->format('Y-m-d') : $record->{$key}))
<div class="{{ $type==='textarea' ? 'md:col-span-2' : '' }}"><label for="{{ $key }}" class="label">{{ $label }} @if(str_contains($rules,'required'))<span class="text-orange-600">*</span>@endif</label>
@if($type==='textarea')<textarea class="input" name="{{ $key }}" id="{{ $key }}" rows="3">{{ $value }}</textarea>
@elseif($type==='status')<select class="input" name="{{ $key }}" id="{{ $key }}">@foreach(['active','paused','completed','archived'] as $status)<option value="{{ $status }}" @selected(($value ?? 'active')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
@elseif(in_array($type,['client','website']))<select class="input" name="{{ $key }}" id="{{ $key }}" required><option value="">Select {{ $type }}</option>@foreach($type==='client' ? $clients : $websites as $option)<option value="{{ $option->id }}" @selected($value==$option->id)>{{ $option->name }}</option>@endforeach</select>@if($type==='website')<p class="mt-1 text-xs text-gray-400">Select a website belonging to the selected client.</p>@endif
@else<input class="input" type="{{ $type }}" name="{{ $key }}" id="{{ $key }}" value="{{ $value ?? ($key==='type' ? 'seo' : ($key==='start_date' ? now()->format('Y-m-d') : '')) }}" @required(str_contains($rules,'required'))>
@endif
</div>
@endforeach
@if($module==='projects')<div class="md:col-span-2"><label class="label" for="employees">Assigned team</label><select class="input" name="employees[]" id="employees" multiple size="5">@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(in_array($employee->id,old('employees',$record->exists ? $record->users()->pluck('users.id')->all() : [])))>{{ $employee->name }}</option>@endforeach</select><p class="mt-2 text-xs text-gray-400">Hold Ctrl or Command to select multiple people. Employees see only assigned projects.</p></div>@endif
</div><div class="mt-7 flex gap-3"><button class="btn" :disabled="saving" x-text="saving ? 'Saving…' : 'Save {{ strtolower($definition['singular']) }}'">Save {{ strtolower($definition['singular']) }}</button><a class="btn-secondary" href="{{ route($module.'.index') }}">Cancel</a></div>
</form>
@endsection
