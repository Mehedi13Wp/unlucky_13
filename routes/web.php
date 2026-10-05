<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardLiveStatusController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OutreachDraftController;
use App\Http\Controllers\ReviewQueueController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SenderAccountController;
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
    Route::view('/settings', 'settings.index')->name('settings.index');
    Route::get('/worker', [WorkerWorkspaceController::class, 'index'])->name('worker.index');
    Route::get('/worker/live-status', [WorkerWorkspaceController::class, 'live'])->name('worker.live-status');
    Route::get('/reports', ReportsController::class)->name('reports.index');
    Route::get('/sender-accounts', [SenderAccountController::class, 'index'])->name('sender-accounts.index');
    Route::post('/sender-accounts', [SenderAccountController::class, 'store'])->name('sender-accounts.store');
    Route::post('/sender-accounts/import', [SenderAccountController::class, 'import'])->name('sender-accounts.import');
    Route::patch('/sender-accounts/{senderAccount}/toggle', [SenderAccountController::class, 'toggle'])->name('sender-accounts.toggle');
    Route::post('/sender-accounts/{senderAccount}/verify', [SenderAccountController::class, 'verify'])
        ->middleware('throttle:5,1')
        ->name('sender-accounts.verify');
    Route::get('/sender-accounts/template.csv', [SenderAccountController::class, 'template'])->name('sender-accounts.template');
    Route::post('/sender-accounts/automation/start', [SenderAccountController::class, 'startAutomation'])->name('sender-accounts.automation.start');
    Route::post('/sender-accounts/automation/stop', [SenderAccountController::class, 'stopAutomation'])->name('sender-accounts.automation.stop');
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
