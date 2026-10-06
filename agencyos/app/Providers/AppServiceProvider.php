<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Project;
use App\Models\SeoToolRun;
use App\Models\Website;
use App\Policies\OperationalPolicy;
use App\Policies\SeoToolRunPolicy;
use App\Services\Seo\ToolRegistry;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            DevCommands::register('"'.PHP_BINARY.'" artisan serve', 'server');
            DevCommands::register('"'.PHP_BINARY.'" artisan queue:work --queue=seo,default --sleep=3 --tries=1 --timeout=1800 --memory=384', 'queue');
            DevCommands::register('"'.PHP_BINARY.'" artisan schedule:work', 'scheduler');
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
