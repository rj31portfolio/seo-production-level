<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->routes(function (): void {
            Route::get('/up', fn (): string => 'OK');
            Route::middleware('web')->group(base_path('routes/web.php'));
        });
    }
}
