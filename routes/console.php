<?php

use App\Services\Outreach\DeliveryAutomationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $result = app(DeliveryAutomationService::class)->run();

    Log::info('delivery-automation tick', [
        'status' => $result['status'] ?? 'unknown',
        'users_checked' => $result['users_checked'] ?? 0,
        'sent' => $result['sent'] ?? 0,
    ]);
})
    ->name('delivery-automation')
    ->everyMinute()
    ->withoutOverlapping();
