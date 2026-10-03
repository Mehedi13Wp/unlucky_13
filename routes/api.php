<?php

use App\Http\Controllers\Api\WorkerAnalysisController;
use App\Http\Controllers\Api\WorkerCampaignController;
use App\Http\Controllers\Api\WorkerContactDiscoveryController;
use App\Http\Controllers\Api\WorkerDeliveryController;
use App\Http\Controllers\Api\WorkerDiscoveryController;
use App\Http\Controllers\Api\WorkerOutreachDraftController;
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

        Route::post('/discovery/known-domains', [WorkerDiscoveryController::class, 'knownDomains'])
            ->name('api.worker.discovery.known-domains');

        Route::post('/campaigns/{campaign}/leads', [WorkerDiscoveryController::class, 'store'])
            ->name('api.worker.campaigns.leads.store');

        Route::get('/analysis/queue', [WorkerAnalysisController::class, 'queue'])
            ->name('api.worker.analysis.queue');
        Route::post('/leads/{lead}/analysis/start', [WorkerAnalysisController::class, 'start'])
            ->name('api.worker.analysis.start');
        Route::post('/leads/{lead}/analysis/complete', [WorkerAnalysisController::class, 'complete'])
            ->name('api.worker.analysis.complete');

        Route::get('/contact-discovery/queue', [WorkerContactDiscoveryController::class, 'queue'])
            ->name('api.worker.contact-discovery.queue');
        Route::post('/leads/{lead}/contacts/complete', [WorkerContactDiscoveryController::class, 'complete'])
            ->name('api.worker.contacts.complete');

        Route::get('/outreach-drafts/queue', [WorkerOutreachDraftController::class, 'queue'])
            ->name('api.worker.outreach-drafts.queue');
        Route::post('/leads/{lead}/outreach-drafts', [WorkerOutreachDraftController::class, 'complete'])
            ->name('api.worker.outreach-drafts.store');

        Route::get('/delivery/queue', [WorkerDeliveryController::class, 'queue'])
            ->name('api.worker.delivery.queue');
        Route::post('/outreach-drafts/{outreachDraft}/send', [WorkerDeliveryController::class, 'send'])
            ->name('api.worker.delivery.send');
    });
