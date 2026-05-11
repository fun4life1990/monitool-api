<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DomainCheckController;
use App\Http\Controllers\Api\V1\DomainController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::apiResource('domains', DomainController::class);
        Route::get('domains/{domain}/checks', [DomainCheckController::class, 'index']);

        if (app()->environment('local')) {
            Route::post('domains/{domain}/check', [DomainController::class, 'check']);
        }
    });
});
