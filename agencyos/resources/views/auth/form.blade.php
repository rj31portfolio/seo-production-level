@extends('layouts.app')
@section('title', ['login'=>'Sign in', 'register'=>'Create your agency', 'forgot'=>'Reset password', 'reset'=>'Choose a password'][$mode])
@section('content')
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden flex-col justify-between bg-[#14171e] p-16 text-white lg:flex">
        <a href="/" class="text-2xl font-bold tracking-tight">SEO Agency<span class="text-orange-400">OS</span></a>
        <div><span class="rounded-full border border-orange-500/30 bg-orange-500/10 px-4 py-2 text-xs text-orange-400">BUILT FOR SEO AGENCIES</span><h1 class="mt-8 text-5xl font-semibold leading-tight tracking-tight">Your agency.<br>Your operations.<br><span class="text-orange-400">One workspace.</span></h1><p class="mt-6 max-w-md text-lg leading-8 text-gray-400">Bring your team, clients, and websites together with secure access for every agency.</p></div>
        <p class="text-sm text-gray-500">One Platform. Complete SEO Operations.</p>
    </div>
    <div class="flex items-center justify-center bg-white px-6 py-14"><div class="w-full max-w-md">
        <p class="mb-9 text-xl font-bold lg:hidden">SEO Agency<span class="text-orange-600">OS</span></p>
        <h2 class="text-3xl font-semibold tracking-tight">{{ ['login'=>'Welcome back', 'register'=>'Create your agency', 'forgot'=>'Forgot your password?', 'reset'=>'Set a new password'][$mode] }}</h2>
        <p class="mb-8 mt-3 text-sm text-gray-500">{{ ['login'=>'Sign in to your agency workspace.', 'register'=>'Set up a secure workspace for your team.', 'forgot'=>'Enter your email to request a reset link.', 'reset'=>'Use at least 12 characters, mixed case and a number.'][$mode] }}</p>
        @include('partials.messages')
        <form method="POST" action="{{ ['login'=>'/login','register'=>'/register','forgot'=>'/forgot-password','reset'=>'/reset-password'][$mode] }}" class="space-y-5" x-data="{ submitting: false }" @submit="submitting = true">@csrf
            @if($mode === 'register')
            <div><label for="name" class="label">Your name</label><input class="input" id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name"></div>
            <div><label for="agency_name" class="label">Agency name</label><input class="input" id="agency_name" name="agency_name" value="{{ old('agency_name') }}" required maxlength="255" autocomplete="organization"></div>
            @endif
            @if($mode === 'reset')<input type="hidden" name="token" value="{{ $token }}">@endif
            <div><label for="email" class="label">Email address</label><input class="input" id="email" type="email" name="email" value="{{ old('email', request('email')) }}" required autocomplete="email" maxlength="255"></div>
            @if($mode !== 'forgot')
            <div><label for="password" class="label">Password</label><input class="input" id="password" type="password" name="password" required autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}" @if($mode !== 'login') minlength="12" @endif></div>
            @endif
            @if(in_array($mode, ['register','reset']))<div><label for="password_confirmation" class="label">Confirm password</label><input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" minlength="12"></div>@endif
            @if($mode === 'login')<div class="flex justify-between text-sm"><label class="flex items-center gap-2 text-gray-500"><input type="checkbox" name="remember" value="1">Remember me</label><a href="/forgot-password" class="font-medium text-orange-600">Forgot password?</a></div>@endif
            <button class="btn w-full" :disabled="submitting" x-text="submitting ? 'Please wait…' : '{{ ['login'=>'Sign in','register'=>'Create workspace','forgot'=>'Send reset link','reset'=>'Save password'][$mode] }}'">{{ ['login'=>'Sign in','register'=>'Create workspace','forgot'=>'Send reset link','reset'=>'Save password'][$mode] }}</button>
        </form>
        <p class="mt-7 text-center text-sm text-gray-500">@if($mode === 'login')New to AgencyOS? <a href="/register" class="font-semibold text-orange-600">Create an agency</a>@else<a href="/login" class="font-semibold text-orange-600">Back to sign in</a>@endif</p>
    </div></div>
</div>
@endsection
