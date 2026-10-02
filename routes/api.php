<?php

use App\Http\Controllers\Api\WorkerDiscoveryController;
use App\Http\Middleware\RequireWorkerToken;
use Illuminate\Support\Facades\Route;

Route::prefix('worker')
    ->middleware([
        RequireWorkerToken::class,
        'throttle:60,1',
    ])
    ->group(function (): void {
        Route::get('/health', [WorkerDiscoveryController::class, 'health'])
            ->name('api.worker.health');

        Route::post('/campaigns/{campaign}/leads', [WorkerDiscoveryController::class, 'store'])
            ->name('api.worker.campaigns.leads.store');
    });
