@extends('layouts.app')
@section('title', 'Agency settings')
@section('description', 'Make your workspace and client reports unmistakably yours.')
@section('content')
<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
<form method="POST" action="{{ route('agency.settings') }}" enctype="multipart/form-data" class="space-y-6" x-data="{ saving: false }" @submit="saving = true">@csrf @method('PATCH')
<section class="card space-y-5">
<div><span class="badge">01 / Brand identity</span><h2 class="mt-3 text-lg font-semibold">Your agency, front and center</h2><p class="mt-1 text-sm text-gray-500">Your logo and identity appear in your workspace and newly saved reports.</p></div>
<div><label for="name" class="label">Agency name</label><input id="name" name="name" class="input" required maxlength="255" value="{{ old('name', $agency->name) }}"></div>
<div><label for="tagline" class="label">Tagline</label><input id="tagline" name="tagline" class="input" maxlength="160" value="{{ old('tagline', $branding['tagline'] ?? '') }}" placeholder="A short promise to your clients"></div>
<div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-5">
<label for="logo" class="label">Agency logo</label>
@if($branding['logo_data'])<img src="{{ $branding['logo_data'] }}" alt="Current agency logo" class="mb-4 max-h-20 max-w-52 object-contain">@endif
<input id="logo" type="file" name="logo" accept="image/png,image/jpeg" class="input"><p class="mt-2 text-xs text-gray-500">PNG or JPG, up to 1 MB and 1600 × 1600 pixels. A wide logo works best in report headers.</p>
@if($branding['logo_data'])<label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1" @checked(old('remove_logo'))>Remove current logo</label>@endif
</div>
<div><label for="accent_color" class="label">Report accent color</label><div class="flex items-center gap-3"><input id="accent_color" name="accent_color" type="color" class="h-11 w-16 cursor-pointer rounded-lg border border-gray-200" value="{{ old('accent_color', $branding['accent_color']) }}"><p class="text-sm text-gray-500">Used for report headings and highlights.</p></div></div>
</section>
<section class="card space-y-5">
<div><span class="badge">02 / Contact & support</span><h2 class="mt-3 text-lg font-semibold">Give clients a direct line</h2></div>
<div><label for="address" class="label">Business address</label><textarea id="address" name="address" class="input" rows="3" maxlength="600">{{ old('address', $branding['address'] ?? '') }}</textarea></div>
<div class="grid gap-5 sm:grid-cols-2">
@foreach(['email' => ['Business email', 'email', 255], 'phone' => ['Phone', 'tel', 50], 'website' => ['Website', 'url', 255], 'support_email' => ['Support email', 'email', 255], 'support_url' => ['Support website', 'url', 255]] as $field => [$label, $type, $length])
<div><label for="{{ $field }}" class="label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" class="input" maxlength="{{ $length }}" value="{{ old($field, $branding[$field] ?? '') }}"></div>
@endforeach
</div>
<div><label for="report_footer" class="label">Report footer message</label><textarea id="report_footer" name="report_footer" class="input" rows="2" maxlength="300" placeholder="Prepared with care for your business.">{{ old('report_footer', $branding['report_footer'] ?? '') }}</textarea></div>
</section>
<section class="card space-y-5">
<div><span class="badge">03 / Regional preferences</span><h2 class="mt-3 text-lg font-semibold">Feel at home</h2></div>
<div><label for="timezone" class="label">Timezone</label><select id="timezone" name="timezone" class="input">@foreach(DateTimeZone::listIdentifiers() as $zone)<option @selected(old('timezone', $agency->timezone) === $zone)>{{ $zone }}</option>@endforeach</select></div>
<div><label for="currency" class="label">Currency code</label><input id="currency" name="currency" class="input" required minlength="3" maxlength="3" pattern="[A-Z]{3}" value="{{ old('currency', $agency->currency) }}"><p class="mt-2 text-xs text-gray-400">Three-letter code, such as INR, USD or EUR.</p></div>
</section>
<div class="flex flex-wrap items-center gap-4"><button class="btn" :disabled="saving" x-text="saving ? 'Saving…' : 'Save changes'">Save changes</button><p class="text-xs text-gray-500">Existing reports keep the branding saved with their snapshot.</p></div>
</form>
<aside class="space-y-5 xl:sticky xl:top-6">
<section class="card overflow-hidden"><span class="text-xs font-semibold uppercase tracking-widest text-gray-400">Saved brand preview</span><div class="mt-5 border-t-4 pt-5" style="border-color: {{ $branding['accent_color'] }}">
@if($branding['logo_data'])<img src="{{ $branding['logo_data'] }}" alt="{{ $agency->name }} logo" class="mb-4 max-h-16 max-w-full object-contain">@endif
<h2 class="text-xl font-semibold">{{ $agency->name }}</h2><p class="mt-1 text-sm text-gray-500">{{ $branding['tagline'] ?? 'Your next client report starts here.' }}</p>
<p class="mt-5 text-xs font-semibold uppercase tracking-widest" style="color: {{ $branding['accent_color'] }}">SEO performance report</p><div class="mt-4 grid grid-cols-3 gap-2">@foreach(['Overview', 'Findings', 'Next steps'] as $label)<div class="rounded-lg bg-gray-50 px-2 py-4 text-center text-[10px] text-gray-500">{{ $label }}</div>@endforeach</div>
<p class="mt-5 whitespace-pre-line break-words text-xs text-gray-500">{{ $branding['address'] ?? '' }}</p><p class="mt-2 break-words text-xs text-gray-500">{{ $branding['email'] ?? '' }}</p>
</div></section>
<section class="card"><h2 class="font-semibold">Connected services</h2><p class="mt-2 text-sm leading-6 text-gray-500">AI credentials and crawler limits are managed by the platform administrator.</p>@if(auth()->user()->is_super_admin)<div class="mt-4 flex flex-col gap-3 text-sm"><a class="text-orange-700 underline" href="{{ route('ai.settings') }}">API & AI provider settings</a><a class="text-orange-700 underline" href="{{ route('seo.settings') }}">SEO engine & usage limits</a></div>@endif</section>
</aside>
</div>
@endsection
