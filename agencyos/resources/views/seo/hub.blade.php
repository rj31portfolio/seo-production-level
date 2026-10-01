@extends('layouts.app')
@section('title','SEO tools')
@section('description','Analyze your websites and content with internal tools. Results stay linked to your agency and projects.')
@section('content')
@can('seo_tools.audit')
<section class="card mb-6 border-orange-200">
    <h2 class="text-lg font-semibold">Audit another website</h2>
    <p class="mt-2 text-sm text-gray-500">Enter a website URL to start a fresh audit. Previous audits remain in Tool history below.</p>
    @if(\App\Services\Seo\ToolRegistry::enabled('audit') && (!$plan || in_array('audit', $plan->tools, true)) && (!isset($limits['audit']) || $limits['audit']->enabled))
        @can('clients.view')
        <form class="mt-5" method="POST" action="{{ route('seo.tools.run', 'audit') }}" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <label class="label" for="audit-url">Website URL</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input class="input flex-1" type="url" name="url" id="audit-url" placeholder="https://example.com/" maxlength="2048" required value="{{ old('url') }}">
                <button class="btn shrink-0" :disabled="busy" x-text="busy ? 'Starting audit…' : 'Start website audit'">Start website audit</button>
            </div>
            <a class="mt-3 inline-block text-sm font-medium text-orange-600" href="{{ route('seo.tools.form', 'audit') }}">Choose a project or page limit</a>
        </form>
        @else
        <a class="btn mt-5" href="{{ route('seo.tools.form', 'audit') }}">Start website audit</a>
        @endcan
    @else
        <p class="mt-4 text-sm text-gray-500">Website audits are unavailable under your current settings or plan.</p>
    @endif
</section>
@endcan
<div class="card mb-6 flex flex-wrap gap-6 text-sm"><p><strong>Queued / running:</strong> {{ $queued }}</p><p><strong>Failed:</strong> {{ $failed }}</p><p><strong>Tool plan:</strong> {{ $plan?->name ?? 'Agency and platform settings' }}</p>@if($plan?->monthly_pages!==null)<p><strong>Reserved crawl pages this month:</strong> {{ $usage->sum('pages') }} / {{ $plan->monthly_pages }}</p>@endif</div>
<div class="mb-7 grid gap-5 sm:grid-cols-3"><div class="card"><p class="text-sm text-gray-500">Tools permitted by your role</p><p class="mt-3 text-3xl font-semibold">{{ count($tools) }}</p></div><div class="card"><p class="text-sm text-gray-500">Your permitted runs today</p><p class="mt-3 text-3xl font-semibold">{{ $usedToday }}</p></div><div class="card"><p class="text-sm text-gray-500">Data sources</p><p class="mt-3 text-sm">Internal engine & supplied input</p><p class="mt-2 text-xs text-gray-400">External metrics appear only when supplied.</p></div></div>
<details class="card mb-6"><summary class="cursor-pointer font-medium">Monthly usage and limits</summary><div class="mt-4 overflow-x-auto"><table class="w-full"><thead><tr><th class="table-th">Tool</th><th class="table-th">Used</th><th class="table-th">Limit</th><th class="table-th">Remaining</th></tr></thead><tbody>@foreach($tools as $slug=>$tool) @php($monthlyLimit=isset($limits[$slug]) ? $limits[$slug]->monthly_runs : ($plan?->limits[$slug] ?? null)) @php($used=$usage[$slug]->runs ?? 0)<tr><td class="table-td">{{ $tool['name'] }}</td><td class="table-td">{{ $used }}</td><td class="table-td">{{ $monthlyLimit ?? 'No monthly quota' }}</td><td class="table-td">{{ $monthlyLimit===null ? '—' : max(0,$monthlyLimit-$used) }}</td></tr>@endforeach</tbody></table></div></details>
<form class="mb-6 flex flex-wrap gap-3"><input class="input max-w-xs" name="q" value="{{ request('q') }}" placeholder="Search tools" aria-label="Search tools"><select class="input w-auto" name="category" aria-label="Category"><option value="">All categories</option>@foreach(array_unique(array_column(\App\Services\Seo\ToolRegistry::all(),'category')) as $category)<option @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select><button class="btn-secondary">Filter</button><a class="btn-secondary" href="{{ route('seo.tools.index') }}">Reset</a></form>
<div class="mb-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">@forelse($tools as $slug=>$tool)<div class="card flex flex-col"><p class="text-xs font-semibold uppercase tracking-wider text-orange-600">{{ $tool['category'] }}</p><h2 class="mt-3 text-lg font-semibold">{{ $tool['name'] }}</h2><p class="mb-5 mt-2 flex-1 text-sm leading-6 text-gray-500">{{ $tool['description'] }}</p>@if(\App\Services\Seo\ToolRegistry::enabled($slug) && (!$plan || in_array($slug,$plan->tools,true)) && (!isset($limits[$slug]) || $limits[$slug]->enabled))<a class="btn-secondary w-fit" href="{{ route('seo.tools.form',$slug) }}">Open tool ↗</a>@else<span class="badge w-fit">Unavailable under current settings or plan</span>@endif</div>@empty<div class="card">No tools match your permissions or filters.</div>@endforelse</div>
<div class="card"><div class="mb-5 flex flex-wrap justify-between gap-3"><h2 class="text-lg font-semibold">Tool history</h2><form class="flex gap-2"><select class="input w-auto" name="status" aria-label="Run status"><option value="">All run statuses</option>@foreach(['queued','running','completed','failed'] as $status)<option @selected(request('status')===$status)>{{ $status }}</option>@endforeach</select><button class="btn-secondary">Apply</button></form></div><div class="overflow-x-auto"><table class="w-full"><thead><tr>@foreach(['Tool','Project','Source','Status','Started'] as $heading)<th class="table-th">{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse($runs as $run)<tr><td class="table-td"><a class="font-medium text-orange-600" href="{{ route('seo.runs.show',$run) }}">{{ \App\Services\Seo\ToolRegistry::get($run->tool)['name'] }}</a></td><td class="table-td">{{ $run->project?->name ?? 'Standalone' }}</td><td class="table-td">{{ $run->source }}</td><td class="table-td"><span class="badge">{{ ucfirst($run->status) }}</span></td><td class="table-td">{{ $run->created_at->format('d M Y H:i') }}</td></tr>@empty<tr><td class="table-td py-12 text-center" colspan="5">Run your first tool to see saved results here.</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $runs->links() }}</div></div>
@endsection
