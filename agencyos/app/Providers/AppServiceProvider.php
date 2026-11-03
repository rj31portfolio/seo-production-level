<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Project;
use App\Models\SeoToolRun;
use App\Models\Website;
use App\Policies\OperationalPolicy;
use App\Policies\SeoToolRunPolicy;
use App\Services\Seo\ToolRegistry;
use App\Services\WebInstaller;
use App\Services\ViteAssets;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
        $this->app->singleton(\Illuminate\Foundation\Vite::class, ViteAssets::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Validator::extend('extensions', fn (string $attribute, mixed $value, array $extensions): bool => $value instanceof UploadedFile
            && in_array(strtolower($value->getClientOriginalExtension()), $extensions, true), 'The :attribute must have one of these extensions: :extensions.');
        Validator::replacer('extensions', fn (string $message, string $attribute, string $rule, array $extensions): string => str_replace(':extensions', implode(', ', $extensions), $message));

        if (app(WebInstaller::class)->enabled()) {
            config(['cache.limiter' => 'file']);
        }
        foreach ([Client::class, Project::class, Website::class] as $model) {
            Gate::policy($model, OperationalPolicy::class);
        }
        foreach (array_unique(array_merge(config('agencyos.billing_permissions'), ...array_values(config('agencyos.roles')))) as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        }
        Gate::define('superadmin.manage', fn ($user) => $user->hasPermission('superadmin.manage'));
        foreach (array_unique(array_merge(...array_values(config('seo_tools.role_permissions')))) as $permission) {
            Gate::define($permission, fn ($user) => $user->hasPermission($permission));
        }
        Gate::policy(SeoToolRun::class, SeoToolRunPolicy::class);
        RateLimiter::for('seo-tools', fn ($request) => Limit::perMinute(ToolRegistry::settings()['runs_per_minute'])->by($request->user()->id.'|'.$request->session()->get('agency_id')));
        RateLimiter::for('auth', fn ($request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
    }
}
