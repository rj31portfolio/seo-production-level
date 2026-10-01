<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Workspace') · SEO AgencyOS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f7f8fa] font-sans text-gray-900 antialiased" x-data="{ mobileMenu: false }">
@auth
<div class="min-h-screen lg:pl-64">
    <button x-cloak x-show="mobileMenu" @click="mobileMenu = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden" aria-label="Close navigation"></button>
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col overflow-y-auto bg-[#14171e] p-5 transition-transform lg:translate-x-0" :class="mobileMenu ? 'translate-x-0' : '-translate-x-full'">
        <a href="/" class="mb-10 flex items-center gap-3 text-white"><span class="grid h-9 w-9 place-items-center rounded-lg bg-orange-600 text-xl font-bold">S</span><span class="text-lg font-bold tracking-tight">SEO Agency<span class="text-orange-400">OS</span></span></a>
        <p class="mb-3 px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-gray-500">Workspace</p>
        <nav class="space-y-1">
            @if(auth()->user()->is_super_admin)
                <a href="{{ route('super-admin') }}" class="nav-link {{ request()->is('super-admin*') ? 'nav-active' : '' }}">◈ <span>Platform overview</span></a>
                <a href="{{ route('seo.settings') }}" class="nav-link">⚙ <span>SEO engine settings</span></a>
            @endif
            @if(app(\App\Tenancy\TenantContext::class)->agency())
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'nav-active' : '' }}">◈ <span>Overview</span></a>
                @foreach(['clients' => ['Clients', '◎'], 'projects' => ['Projects', '▣'], 'websites' => ['Websites', '◉']] as $module => [$label, $icon])
                    @if(Route::has($module.'.index')) @can($module.'.view')<a href="{{ route($module.'.index') }}" class="nav-link {{ request()->is($module.'*') ? 'nav-active' : '' }}">{{ $icon }} <span>{{ $label }}</span></a>@endcan @endif
                @endforeach
                @if(Route::has('employees.index')) @can('employees.manage')<a href="{{ route('employees.index') }}" class="nav-link {{ request()->is('employees*') ? 'nav-active' : '' }}">♧ <span>Team members</span></a>@endcan @endif
                @can('seo_tools.view')<a href="{{ route('seo.tools.index') }}" class="nav-link {{ request()->is('seo/*') ? 'nav-active' : '' }}">⌕ <span>SEO tools</span></a>@endcan
                @can('tasks.view')<a href="{{ route('seo.tasks.index') }}" class="nav-link">☑ <span>SEO tasks</span></a>@endcan
                @can('seo_tools.reports')<a href="{{ route('seo.reports.index') }}" class="nav-link">▤ <span>SEO reports</span></a>@endcan
                <p class="pb-2 pt-8 px-3 text-[10px] font-semibold uppercase tracking-[.2em] text-gray-500">Management</p>
                @can('activity.view')<a href="{{ route('activity') }}" class="nav-link {{ request()->routeIs('activity') ? 'nav-active' : '' }}">≡ <span>Activity log</span></a>@endcan
                @can('subscriptions.view')<a href="{{ route('client-subscriptions.index') }}" class="nav-link {{ request()->is('client-subscriptions*') ? 'nav-active' : '' }}">◷ <span>Client subscriptions</span></a><a href="{{ route('client-plans.index') }}" class="nav-link">▤ <span>Client SEO plans</span></a>@endcan
                @can('billing.view')<a href="{{ route('invoices.index') }}" class="nav-link">▧ <span>Client invoices</span></a>@endcan
                <a href="{{ route('notifications.index') }}" class="nav-link">♧ <span>Notifications</span></a>
                @can('agency.manage')<a href="{{ route('agency.settings') }}" class="nav-link {{ request()->routeIs('agency.settings') ? 'nav-active' : '' }}">⚙ <span>Agency settings</span></a>@endcan
            @endif
        </nav>
        <div class="mt-auto rounded-xl border border-white/10 p-4"><span class="text-xs font-semibold text-orange-400">SELF-HOSTED</span><p class="mt-2 text-xs leading-5 text-gray-400">Your agency workspace.<br>One platform. Complete SEO operations.</p></div>
        <div class="mt-5 flex items-center gap-3 border-t border-white/10 pt-5"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gray-700 text-sm font-semibold text-white">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div class="min-w-0"><p class="truncate text-sm text-white">{{ auth()->user()->name }}</p><p class="truncate text-xs text-gray-500">{{ auth()->user()->email }}</p></div></div>
    </aside>
    <header class="flex min-h-20 flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-white px-5 lg:px-9">
        <div class="flex items-center gap-3"><button @click="mobileMenu = !mobileMenu" class="btn-secondary lg:hidden" aria-label="Open navigation">☰</button><span class="text-sm text-gray-500">{{ app(\App\Tenancy\TenantContext::class)->agency()?->name ?? 'Platform administration' }}</span></div>
        <div class="flex items-center gap-4">
            @if(auth()->user()->agencies()->where('status', 'active')->exists())
            <form method="POST" action="{{ route('agency.switch') }}" class="flex items-center gap-2">@csrf
                <select name="agency_id" class="input max-w-44" aria-label="Agency">@foreach(auth()->user()->agencies()->where('status', 'active')->get() as $option)<option value="{{ $option->id }}" @selected(session('agency_id') == $option->id)>{{ $option->name }}</option>@endforeach</select><button class="btn-secondary">Switch</button>
            </form>
            @endif
            <form action="{{ route('logout') }}" method="POST">@csrf<button class="text-sm font-medium text-gray-500 hover:text-orange-600">Sign out</button></form>
        </div>
    </header>
    <main class="mx-auto max-w-[1500px] p-5 lg:p-9">
        <p class="mb-5 text-xs text-gray-400">Workspace <span class="mx-2">/</span> <span class="text-gray-700">@yield('title', 'Overview')</span></p>
        <div class="mb-7 flex flex-wrap items-start justify-between gap-4"><div><h1 class="text-3xl font-semibold tracking-tight">@yield('title', 'Overview')</h1><p class="mt-2 text-sm text-gray-500">@yield('description')</p></div>@yield('action')</div>
        @include('partials.messages')
        @yield('content')
    </main>
</div>
@else
<main class="min-h-screen">@yield('content')</main>
@endauth
</body></html>
