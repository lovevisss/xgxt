<?php

use Illuminate\Support\Facades\Route;
use Zufedfc\LaravelCas\Http\Controllers\CasAuthController;

Route::prefix(config('cas.routes.prefix', 'sso'))
    ->middleware(config('cas.routes.middleware', ['web']))
    ->group(function (): void {
        Route::get('/login', [CasAuthController::class, 'login'])
            ->name(config('cas.routes.names.login', 'cas.login'));
        Route::get('/logout', [CasAuthController::class, 'logout'])
            ->name(config('cas.routes.names.logout', 'cas.logout'));
        Route::match(['GET', 'POST'], '/slo', [CasAuthController::class, 'slo'])
            ->name(config('cas.routes.names.slo', 'cas.slo'));
        Route::post('/userOnlineDetect', [CasAuthController::class, 'userOnlineDetect'])
            ->name(config('cas.routes.names.user_online_detect', 'cas.user-online-detect'));
    });
