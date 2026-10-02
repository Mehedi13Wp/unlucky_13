<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
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

    Route::resource('campaigns', CampaignController::class)
        ->except(['destroy']);
    Route::patch('/campaigns/{campaign}/status', [CampaignController::class, 'updateStatus'])
        ->name('campaigns.status');
    Route::patch('/campaigns/{campaign}/archive', [CampaignController::class, 'archive'])
        ->name('campaigns.archive');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
