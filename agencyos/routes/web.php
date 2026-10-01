<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientSubscriptionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\OperationsController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? (auth()->user()->is_super_admin ? '/super-admin' : '/dashboard') : '/login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.form', ['mode' => 'login']))->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('/register', fn () => view('auth.form', ['mode' => 'register']))->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::get('/forgot-password', fn () => view('auth.form', ['mode' => 'forgot']))->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:auth')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('auth.form', ['mode' => 'reset', 'token' => $token]))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:auth')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/agency/switch', [AuthController::class, 'switchAgency'])->name('agency.switch');
    Route::middleware('can:superadmin.manage')->group(function () {
        Route::get('/super-admin/seo-settings',[\App\Http\Controllers\SeoSettingsController::class,'index'])->name('seo.settings');
        Route::patch('/super-admin/seo-settings',[\App\Http\Controllers\SeoSettingsController::class,'update']);
        Route::post('/super-admin/seo-settings/limits',[\App\Http\Controllers\SeoSettingsController::class,'limit'])->name('seo.settings.limit');
        Route::get('/super-admin', [FoundationController::class, 'superAdmin'])->name('super-admin');
        Route::patch('/super-admin/agencies/{agency}', [FoundationController::class, 'agencyStatus'])->name('super-admin.agency-status');
        Route::get('/super-admin/expiry-settings', [ClientSubscriptionController::class, 'expirySettings'])->name('expiry.settings');
        Route::patch('/super-admin/expiry-settings', [ClientSubscriptionController::class, 'updateExpirySettings']);
    });
    Route::middleware('tenant')->group(function () {
        $seo=\App\Http\Controllers\SeoToolsController::class;
        $work=\App\Http\Controllers\SeoWorkController::class;
        $rank=\App\Http\Controllers\RankingController::class;
        Route::middleware('can:seo_tools.rankings')->group(function () use ($rank): void {
            Route::get('/seo/rankings',[$rank,'index'])->name('seo.rankings.index');
            Route::get('/seo/rankings/export',[$rank,'export'])->middleware('throttle:seo-tools')->name('seo.rankings.export');
            Route::post('/seo/rankings',[$rank,'store'])->middleware('throttle:seo-tools')->name('seo.rankings.store');
            Route::post('/seo/rankings/import',[$rank,'import'])->middleware('throttle:seo-tools')->name('seo.rankings.import');
            Route::get('/seo/rankings/{keyword}',[$rank,'show'])->name('seo.rankings.show');
            Route::post('/seo/rankings/{keyword}',[$rank,'entry'])->middleware('throttle:seo-tools')->name('seo.rankings.entry');
            Route::post('/seo/runs/{run}/keywords',[$rank,'saveRun'])->middleware('throttle:seo-tools')->name('seo.runs.keywords');
        });
        Route::get('/seo/tasks',[$work,'tasks'])->middleware('can:tasks.view')->name('seo.tasks.index');
        Route::patch('/seo/tasks/{task}',[$work,'updateTask'])->middleware('can:tasks.view')->name('seo.tasks.update');
        Route::post('/seo/runs/{run}/tasks',[$work,'generateTasks'])->middleware('can:tasks.create')->name('seo.runs.tasks');
        Route::post('/seo/runs/{run}/report',[$work,'generateReport'])->middleware('can:seo_tools.reports')->name('seo.runs.report');
        Route::get('/seo/reports',[$work,'reports'])->middleware('can:seo_tools.reports')->name('seo.reports.index');
        Route::get('/seo/reports/{report}',[$work,'report'])->middleware('can:seo_tools.reports')->name('seo.reports.show');
        Route::get('/seo/reports/{report}/html',[$work,'html'])->middleware('can:seo_tools.reports')->name('seo.reports.html');
        Route::get('/seo/reports/{report}/pdf',[$work,'pdf'])->middleware('can:seo_tools.reports')->name('seo.reports.pdf');
        Route::middleware('can:seo_tools.view')->group(function () use ($seo) {
            Route::get('/seo/tools',[$seo,'index'])->name('seo.tools.index');
            Route::get('/seo/tools/{tool}',[$seo,'form'])->name('seo.tools.form');
            Route::post('/seo/tools/{tool}',[$seo,'run'])->middleware('throttle:seo-tools')->name('seo.tools.run');
            Route::get('/seo/runs/{run}',[$seo,'show'])->name('seo.runs.show');
            Route::get('/seo/runs/{run}/status',[$seo,'status'])->name('seo.runs.status');
            Route::get('/seo/runs/{run}/export',[$seo,'export'])->name('seo.runs.export');
            Route::delete('/seo/runs/{run}',[$seo,'destroy'])->name('seo.runs.destroy');
        });
        $billing = ClientSubscriptionController::class;
        Route::get('/client-plans', [$billing, 'plans'])->middleware('can:subscriptions.view')->name('client-plans.index');
        Route::post('/client-plans', [$billing, 'storePlan'])->middleware('can:subscriptions.manage')->name('client-plans.store');
        Route::patch('/client-plans/{plan}', [$billing, 'togglePlan'])->middleware('can:subscriptions.manage')->name('client-plans.toggle');
        Route::get('/client-subscriptions', [$billing, 'index'])->middleware('can:subscriptions.view')->name('client-subscriptions.index');
        Route::get('/client-subscriptions/create', [$billing, 'create'])->middleware('can:subscriptions.create')->name('client-subscriptions.create');
        Route::post('/client-subscriptions', [$billing, 'store'])->middleware('can:subscriptions.create')->name('client-subscriptions.store');
        Route::get('/client-subscriptions/{subscription}', [$billing, 'show'])->middleware('can:subscriptions.view')->name('client-subscriptions.show');
        Route::post('/client-subscriptions/{subscription}/renew', [$billing, 'renew'])->middleware('can:subscriptions.renew')->name('client-subscriptions.renew');
        Route::patch('/client-subscriptions/{subscription}', [$billing, 'change'])->middleware('can:subscriptions.manage')->name('client-subscriptions.change');
        Route::get('/invoices', [$billing, 'invoices'])->middleware('can:billing.view')->name('invoices.index');
        Route::get('/invoices/{invoice}', [$billing, 'invoice'])->middleware('can:billing.view')->name('invoices.show');
        Route::post('/invoices/{invoice}/payments', [$billing, 'payment'])->middleware('can:billing.manage')->name('invoices.payment');
        Route::get('/notifications', [$billing, 'notifications'])->name('notifications.index');
        Route::patch('/notifications/{notification}', [$billing, 'read'])->name('notifications.read');
        foreach (['clients', 'projects', 'websites'] as $module) {
            Route::prefix($module)->name($module.'.')->group(function () use ($module) {
                $controller = OperationsController::class;
                Route::get('/', [$controller, 'index'])->defaults('module', $module)->name('index');
                Route::get('/create', [$controller, 'create'])->defaults('module', $module)->name('create');
                Route::post('/', [$controller, 'store'])->defaults('module', $module)->name('store');
                Route::get('/{record}', [$controller, 'show'])->whereNumber('record')->defaults('module', $module)->name('show');
                Route::get('/{record}/edit', [$controller, 'edit'])->whereNumber('record')->defaults('module', $module)->name('edit');
                Route::put('/{record}', [$controller, 'update'])->whereNumber('record')->defaults('module', $module)->name('update');
                Route::delete('/{record}', [$controller, 'destroy'])->whereNumber('record')->defaults('module', $module)->name('destroy');
            });
        }
        Route::middleware('can:employees.manage')->group(function () {
            Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::patch('/employees/{user}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::delete('/employees/{user}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
        });
        Route::get('/dashboard', [FoundationController::class, 'dashboard'])->name('dashboard');
        Route::get('/agency/settings', [FoundationController::class, 'settings'])->middleware('can:agency.manage')->name('agency.settings');
        Route::patch('/agency/settings', [FoundationController::class, 'updateSettings'])->middleware('can:agency.manage');
        Route::get('/activity', [FoundationController::class, 'activity'])->middleware('can:activity.view')->name('activity');
    });
});
