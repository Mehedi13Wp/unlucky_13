<?php

use App\Services\Outreach\DeliveryAutomationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('unlucky13:reset-workspace {--force : Confirm destructive reset}', function (): int {
    if (! $this->option('force')) {
        $this->error('Refusing to reset without --force.');
        $this->line('This removes campaign/work data but preserves users, sender accounts, and suppressions.');

        return self::FAILURE;
    }

    DB::transaction(function (): void {
        $tables = [
            'delivery_automation_settings',
            'outreach_messages',
            'outreach_drafts',
            'contacts',
            'discovery_domains',
            'leads',
            'campaigns',
            'jobs',
            'failed_jobs',
            'job_batches',
            'cache_locks',
            'cache',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (Schema::hasTable('sender_accounts')) {
            DB::table('sender_accounts')->update([
                'last_used_at' => null,
                'updated_at' => now(),
            ]);
        }
    });

    $this->info('Workspace reset complete.');
    $this->line('Preserved: users, sender accounts/SMTP verification, and suppressions.');
    $this->line('Removed: campaigns, leads, contacts, drafts, delivery history, discovery history, automation settings, queued jobs, and cache locks.');

    return self::SUCCESS;
})->purpose('Reset campaign/work data while preserving login, verified senders, and suppressions');

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
