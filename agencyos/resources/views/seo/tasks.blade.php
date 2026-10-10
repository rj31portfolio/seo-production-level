@extends('layouts.app')
@section('title','SEO tasks')
@section('description','Work generated from actual findings, with project-scoped access and manager review.')
@section('content')
@can('tasks.create')
<details class="card mb-6" open>
    <summary class="cursor-pointer font-semibold">Generate project tasks</summary>
    <p class="mt-3 text-sm text-gray-500">Choose a completed project run. Audit findings become tasks; AI recommendations become tasks for human review. Repeating generation keeps existing tasks.</p>
    @forelse($runs as $run)
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
        <a class="text-sm font-medium text-orange-600" href="{{ route('seo.runs.show', $run) }}">{{ $run->project->name }} · {{ \App\Services\Seo\ToolRegistry::get($run->tool)['name'] }} · Run #{{ $run->id }}</a>
        <form method="POST" action="{{ route('seo.runs.tasks', $run) }}" onsubmit="return confirm('Create tasks from this run? Review AI suggestions before implementation.')">@csrf<button class="btn-secondary">Generate tasks</button></form>
    </div>
    @empty
    <p class="mt-4 text-sm text-gray-500">Run an audit or AI tool with a project selected to generate tasks.</p>
    @endforelse
</details>
@endcan
<div class="card"><form class="mb-5 flex flex-wrap gap-3"><input class="input max-w-xs" name="q" placeholder="Search tasks" value="{{ request('q') }}" aria-label="Search tasks"><select class="input w-auto" name="status" aria-label="Task status"><option value="">All statuses</option>@foreach(['pending','in_progress','review','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select><button class="btn-secondary">Apply</button></form>
@forelse($tasks as $task)<article class="border-t border-gray-100 py-6"><div class="flex flex-wrap justify-between gap-3"><div><h2 class="font-semibold">{{ $task->title }}</h2><p class="mt-2 text-xs text-gray-400">{{ $task->project->name }} · {{ $task->assignee?->name ?? 'Unassigned' }} · Due {{ $task->due_at->format('d M Y H:i') }}</p></div><span class="badge h-fit">{{ ucfirst($task->priority) }}</span></div><p class="mt-4 whitespace-pre-wrap break-all text-sm text-gray-500">{{ $task->description }}</p><form class="mt-5 flex flex-wrap items-end gap-3" method="POST" action="{{ route('seo.tasks.update',$task) }}">@csrf @method('PATCH')<div><label class="label" for="status-{{ $task->id }}">Status</label><select class="input w-auto" name="status" id="status-{{ $task->id }}">@foreach(auth()->user()->hasPermission('tasks.assign') ? ['pending','in_progress','review','completed','cancelled'] : ['in_progress','review'] as $status)<option value="{{ $status }}" @selected($task->status===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>@can('tasks.assign')<div><label class="label" for="assignee-{{ $task->id }}">Assignee</label><select class="input w-auto" name="assigned_to" id="assignee-{{ $task->id }}"><option value="">Unassigned</option>@foreach($task->project->users as $user)<option value="{{ $user->id }}" @selected($task->assigned_to===$user->id)>{{ $user->name }}</option>@endforeach</select></div>@endcan<div class="flex-1"><label class="label" for="note-{{ $task->id }}">Completion / review note</label><input class="input" name="completion_note" id="note-{{ $task->id }}" value="{{ $task->completion_note }}" maxlength="5000"></div><button class="btn-secondary">Save task</button></form>
@can('seo_tools.delete')<form class="mt-4" method="POST" action="{{ route('seo.tasks.destroy', $task) }}" onsubmit="return confirm('Permanently delete this task?')">@csrf @method('DELETE')<button class="btn-secondary text-red-700">Delete task</button></form>@endcan
</article>@empty<p class="py-12 text-center text-sm text-gray-400">No tasks. Generate tasks from a completed project audit.</p>@endforelse<div class="mt-5">{{ $tasks->links() }}</div></div>
@endsection
