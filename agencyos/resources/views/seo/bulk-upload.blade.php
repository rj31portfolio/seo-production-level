@extends('layouts.app')
@section('title', 'Bulk SEO uploads')
@section('description', 'Import backlinks, ranking observations, and task progress from CSV or Excel.')
@section('content')
<p class="mb-6 text-sm text-gray-500">Upload up to 500 rows per file, maximum 256 KB. Use plain values in the first worksheet and keep the template headers. Dates must be YYYY-MM-DD text.</p>
<div class="grid gap-6 xl:grid-cols-2">
@foreach(['backlinks' => ['Backlinks', 'seo_tools.backlinks', 'seo.backlinks.import'], 'rankings' => ['Keyword ranking observations', 'seo_tools.rankings', 'seo.rankings.import'], 'tasks' => ['Task work progress', 'tasks.assign', 'seo.bulk.tasks.import']] as $type => [$title, $permission, $importRoute])
@can($permission)
<section class="card"><h2 class="text-lg font-semibold">{{ $title }}</h2>
<p class="mt-3 text-sm text-gray-500">@if($type === 'backlinks')Supply source and target URLs, plus optional anchor text and campaign. Exact URL pairs are imported once per project.@elseif($type === 'rankings')Supply keywords, observed dates, positions, countries, locations, devices and search engines.@else Download the project's current tasks, update status and completion notes, then upload the file. Task IDs must belong to the selected project. No new tasks are created.@endif</p>
<a class="mt-4 inline-block text-sm font-medium text-orange-600" href="{{ route('seo.bulk.template', $type) }}">Download Excel template</a>
@if($type === 'tasks')
<form class="mt-5 space-y-3" method="GET" action="{{ route('seo.bulk.tasks.export') }}"><label class="label" for="export-project">Project to export</label><select class="input" id="export-project" name="project_id" required><option value="">Select project</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select><button class="btn-secondary">Download current task progress</button></form>
<p class="mt-4 text-xs text-gray-500">Statuses: pending, in_progress, review, completed, cancelled. A blank completion note clears the previous note.</p>
@endif
<form class="mt-5 space-y-4" method="POST" enctype="multipart/form-data" action="{{ route($importRoute) }}">@csrf
<div><label class="label" for="project-{{ $type }}">Project</label><select class="input" id="project-{{ $type }}" name="project_id" required><option value="">Select project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>@endforeach</select></div>
<div><label class="label" for="file-{{ $type }}">CSV or Excel file</label><input class="input" id="file-{{ $type }}" name="file" type="file" accept=".csv,.xlsx" required></div>
<button class="btn">Import {{ strtolower($title) }}</button></form>
</section>
@endcan
@endforeach
</div>
@endsection
