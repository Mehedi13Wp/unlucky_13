<?php

namespace App\Services\Outreach;

use App\Models\SenderAccount;

class SenderAccountMailer
{
    public const NAME = 'sender_pool';

    public function configure(SenderAccount $account, bool $forceSmtp = false): void
    {
        if (! $forceSmtp && config('unlucky13.delivery_dry_run')) {
            config()->set('mail.mailers.'.self::NAME, [
                'transport' => 'log',
                'channel' => config('mail.mailers.log.channel'),
            ]);

            app('mail.manager')->purge(self::NAME);

            return;
        }

        config()->set('mail.mailers.'.self::NAME, [
            'transport' => 'smtp',
            'scheme' => $account->smtp_scheme ?: 'smtp',
            'host' => $account->smtp_host,
            'port' => $account->smtp_port,
            'username' => $account->smtp_username,
            'password' => $account->smtp_password,
            'timeout' => 30,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);

        app('mail.manager')->purge(self::NAME);
    }
}
