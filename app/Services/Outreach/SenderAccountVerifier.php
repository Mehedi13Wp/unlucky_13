<?php

namespace App\Services\Outreach;

use App\Models\SenderAccount;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SenderAccountVerifier
{
    public function __construct(
        private readonly SenderAccountMailer $senderMailer,
    ) {
    }

    public function verify(SenderAccount $account): array
    {
        if (! $account->isConfigured()) {
            $message = 'Sender account is missing required SMTP configuration.';

            $account->update([
                'smtp_verified_at' => null,
                'smtp_verification_attempted_at' => now(),
                'smtp_verification_error' => $message,
            ]);

            return [
                'ok' => false,
                'message' => $message,
            ];
        }

        try {
            $this->senderMailer->configure($account, true);

            Mail::mailer(SenderAccountMailer::NAME)->raw(
                'This message confirms that your Unlucky 13 SMTP sender account can authenticate and deliver email.',
                function ($mailMessage) use ($account): void {
                    $mailMessage->from(
                        $account->email,
                        $account->from_name ?: null,
                    );

                    $mailMessage->to($account->email)
                        ->subject('Unlucky 13 SMTP verification');
                },
            );

            $account->update([
                'smtp_verified_at' => now(),
                'smtp_verification_attempted_at' => now(),
                'smtp_verification_error' => null,
            ]);

            return [
                'ok' => true,
                'message' => 'SMTP verification succeeded. This sender is ready for live delivery.',
            ];
        } catch (Throwable $error) {
            $safeError = $this->safeErrorMessage($error, $account);

            $account->update([
                'smtp_verified_at' => null,
                'smtp_verification_attempted_at' => now(),
                'smtp_verification_error' => $safeError,
            ]);

            return [
                'ok' => false,
                'message' => 'SMTP verification failed. Check the SMTP host, port, username, app password, and provider security settings, then try again.',
            ];
        } finally {
            app('mail.manager')->purge(SenderAccountMailer::NAME);
        }
    }

    private function safeErrorMessage(
        Throwable $error,
        SenderAccount $account,
    ): string {
        $message = mb_substr($error->getMessage(), 0, 2000);

        $sensitiveValues = array_filter([
            (string) $account->smtp_password,
        ], static fn (string $value): bool => $value !== '');

        foreach ($sensitiveValues as $sensitiveValue) {
            $message = str_replace($sensitiveValue, '[redacted]', $message);
        }

        return $message !== ''
            ? $message
            : 'SMTP verification failed without a provider error message.';
    }
}
