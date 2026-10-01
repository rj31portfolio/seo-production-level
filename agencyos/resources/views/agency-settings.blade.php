@extends('layouts.app')
@section('title', 'Agency settings')
@section('description', 'Configure your agency identity and regional preferences.')
@section('content')
<form method="POST" class="card max-w-2xl space-y-5" x-data="{ saving: false }" @submit="saving = true">@csrf @method('PATCH')
<div><label for="name" class="label">Agency name</label><input id="name" name="name" class="input" required maxlength="255" value="{{ old('name', $agency->name) }}"></div>
<div><label for="timezone" class="label">Timezone</label><select id="timezone" name="timezone" class="input">@foreach(DateTimeZone::listIdentifiers() as $zone)<option @selected(old('timezone', $agency->timezone) === $zone)>{{ $zone }}</option>@endforeach</select></div>
<div><label for="currency" class="label">Currency code</label><input id="currency" name="currency" class="input" required minlength="3" maxlength="3" pattern="[A-Z]{3}" value="{{ old('currency', $agency->currency) }}"><p class="mt-2 text-xs text-gray-400">Three-letter code, such as INR, USD or EUR.</p></div>
<button class="btn" :disabled="saving" x-text="saving ? 'Saving…' : 'Save changes'">Save changes</button>
</form>
@endsection
