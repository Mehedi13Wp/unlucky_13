<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardLiveStatusController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OutreachDraftController;
use App\Http\Controllers\ReviewQueueController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\WorkerWorkspaceController;
use App\Http\Controllers\WorkerRuntimeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', fn () => redirect()->route('dashboard'))->name('home');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/live-status', DashboardLiveStatusController::class)
        ->name('dashboard.live-status');
    Route::get('/worker', [WorkerWorkspaceController::class, 'index'])->name('worker.index');
    Route::get('/worker/live-status', [WorkerWorkspaceController::class, 'live'])->name('worker.live-status');
    Route::get('/reports', ReportsController::class)->name('reports.index');
    Route::post('/worker/start', [WorkerRuntimeController::class, 'start'])->name('worker.start');
    Route::post('/worker/stop', [WorkerRuntimeController::class, 'stop'])->name('worker.stop');

    Route::resource('campaigns', CampaignController::class)
        ->except(['destroy']);
    Route::delete('/campaigns/{campaign}/remove-permanently', [CampaignController::class, 'removePermanently'])
        ->name('campaigns.remove-permanently');
    Route::patch('/campaigns/{campaign}/status', [CampaignController::class, 'updateStatus'])
        ->name('campaigns.status');
    Route::patch('/campaigns/{campaign}/archive', [CampaignController::class, 'archive'])
        ->name('campaigns.archive');

    Route::resource('leads', LeadController::class)
        ->only(['index', 'create', 'store', 'show']);

    Route::get('/review-queue', ReviewQueueController::class)
        ->name('review-queue.index');

    Route::patch(
        '/leads/{lead}/outreach-drafts/{outreachDraft}/approve',
        [OutreachDraftController::class, 'approve'],
    )->name('outreach-drafts.approve');

    Route::post(
        '/leads/{lead}/outreach-drafts/{outreachDraft}/send',
        [OutreachDraftController::class, 'send'],
    )->name('outreach-drafts.send');

    Route::patch(
        '/leads/{lead}/outreach-drafts/{outreachDraft}/reject',
        [OutreachDraftController::class, 'reject'],
    )->name('outreach-drafts.reject');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
