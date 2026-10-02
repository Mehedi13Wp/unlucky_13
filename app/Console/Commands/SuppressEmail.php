<?php

namespace App\Console\Commands;

use App\Models\Suppression;
use Illuminate\Console\Command;

class SuppressEmail extends Command
{
    protected $signature = 'unlucky13:suppress-email
                            {email : Public email address to suppress}
                            {--reason=manual : Why this address must not receive outreach}';

    protected $description = 'Add or refresh an email address on the outreach suppression list';

    public function handle(): int
    {
        $email = Suppression::normalizeEmail((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        Suppression::query()->updateOrCreate(
            [
                'type' => 'email',
                'value' => $email,
            ],
            [
                'reason' => (string) $this->option('reason'),
                'source' => 'artisan',
                'suppressed_at' => now(),
                'released_at' => null,
            ],
        );

        $this->info($email.' is suppressed from outreach delivery.');

        return self::SUCCESS;
    }
}
