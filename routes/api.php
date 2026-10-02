<?php

use App\Http\Controllers\Api\WorkerAnalysisController;
use App\Http\Controllers\Api\WorkerCampaignController;
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

        Route::get('/campaigns', [WorkerCampaignController::class, 'index'])
            ->name('api.worker.campaigns.index');

        Route::post('/campaigns/{campaign}/leads', [WorkerDiscoveryController::class, 'store'])
            ->name('api.worker.campaigns.leads.store');

        Route::get('/analysis/queue', [WorkerAnalysisController::class, 'queue'])
            ->name('api.worker.analysis.queue');
        Route::post('/leads/{lead}/analysis/start', [WorkerAnalysisController::class, 'start'])
            ->name('api.worker.analysis.start');
        Route::post('/leads/{lead}/analysis/complete', [WorkerAnalysisController::class, 'complete'])
            ->name('api.worker.analysis.complete');
    });
