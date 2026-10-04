<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\DeliveryAutomationSetting;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\SenderAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SenderAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = $request->user()->senderAccounts()->orderBy('id')->get();
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $rows = $accounts->map(function (SenderAccount $account) use ($todayStart, $todayEnd): array {
            $sentToday = OutreachMessage::query()
                ->where('sender_account_id', $account->id)
                ->where('status', OutreachMessage::STATUS_SENT)
                ->whereBetween('sent_at', [$todayStart, $todayEnd])
                ->count();

            $remaining = max(0, $account->daily_limit - $sentToday);

            $status = ! $account->enabled
                ? 'disabled'
                : (! $account->isConfigured()
                    ? 'needs_setup'
                    : ($remaining > 0 ? 'available' : 'limit_reached'));

            return [
                'account' => $account,
                'sent_today' => $sentToday,
                'remaining_today' => $remaining,
                'status' => $status,
            ];
        });

        $automation = $request->user()->deliveryAutomationSetting
            ?? new DeliveryAutomationSetting([
                'global_daily_limit' => 20,
                'interval_minutes' => 15,
                'session_hours' => 5,
                'enabled' => false,
            ]);

        $sentTodayGlobal = OutreachMessage::query()
            ->whereHas(
                'campaign',
                fn ($query) => $query->where('user_id', $request->user()->id),
            )
            ->where('status', OutreachMessage::STATUS_SENT)
            ->whereBetween('sent_at', [$todayStart, $todayEnd])
            ->count();

        $approvedQueue = OutreachDraft::query()
            ->where('status', OutreachDraft::STATUS_APPROVED)
            ->where('channel', 'email')
            ->whereHas(
                'lead.campaign',
                fn ($query) => $query
                    ->where('user_id', $request->user()->id)
                    ->where('status', CampaignStatus::Active->value),
            )
            ->whereDoesntHave(
                'message',
                fn ($query) => $query->where('status', OutreachMessage::STATUS_SENT),
            )
            ->count();

        return view('sender-accounts.index', [
            'rows' => $rows,
            'stats' => [
                'total' => $rows->count(),
                'available' => $rows->where('status', 'available')->count(),
                'limit_reached' => $rows->where('status', 'limit_reached')->count(),
                'disabled' => $rows->where('status', 'disabled')->count(),
                'remaining_today' => $rows->sum('remaining_today'),
            ],
            'automation' => $automation,
            'automationStats' => [
                'sent_today' => $sentTodayGlobal,
                'approved_queue' => $approvedQueue,
                'remaining_today' => max(
                    0,
                    (int) $automation->global_daily_limit - $sentTodayGlobal,
                ),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:320'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_scheme' => ['required', 'in:smtp,smtps'],
            'smtp_username' => ['nullable', 'string', 'max:320'],
            'smtp_password' => ['required', 'string', 'max:1000'],
            'daily_limit' => ['required', 'integer', 'between:1,50'],
        ]);

        $email = strtolower(trim($validated['email']));

        $request->user()->senderAccounts()->updateOrCreate(
            ['email' => $email],
            [
                'from_name' => trim((string) ($validated['from_name'] ?? '')) ?: null,
                'smtp_host' => trim($validated['smtp_host']),
                'smtp_port' => (int) $validated['smtp_port'],
                'smtp_scheme' => $validated['smtp_scheme'],
                'smtp_username' => trim((string) ($validated['smtp_username'] ?? '')) ?: $email,
                'smtp_password' => $validated['smtp_password'],
                'daily_limit' => (int) $validated['daily_limit'],
                'enabled' => true,
            ],
        );

        return back()->with('success', 'Sender account saved. SMTP password is stored encrypted.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'max:4096'],
        ]);

        $handle = fopen($request->file('csv')->getRealPath(), 'rb');

        if ($handle === false) {
            return back()->with('error', 'Could not read the CSV file.');
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);
            return back()->with('error', 'CSV header row is missing.');
        }

        $headers = array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $headers,
        );

        foreach (['email', 'smtp_password'] as $requiredHeader) {
            if (! in_array($requiredHeader, $headers, true)) {
                fclose($handle);
                return back()->with('error', 'CSV must include email and smtp_password columns.');
            }
        }

        $imported = 0;
        $skipped = 0;

        while (($values = fgetcsv($handle)) !== false) {
            if (count(array_filter($values, static fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($headers as $index => $header) {
                $row[$header] = $values[$index] ?? null;
            }

            $validator = Validator::make($row, [
                'email' => ['required', 'email:rfc', 'max:320'],
                'from_name' => ['nullable', 'string', 'max:255'],
                'smtp_host' => ['nullable', 'string', 'max:255'],
                'smtp_port' => ['nullable', 'integer', 'between:1,65535'],
                'smtp_scheme' => ['nullable', 'in:smtp,smtps'],
                'smtp_username' => ['nullable', 'string', 'max:320'],
                'smtp_password' => ['required', 'string', 'max:1000'],
                'daily_limit' => ['nullable', 'integer', 'between:1,50'],
                'enabled' => ['nullable', 'in:0,1,true,false,TRUE,FALSE'],
            ]);

            if ($validator->fails()) {
                $skipped++;
                continue;
            }

            $data = $validator->validated();
            $email = strtolower(trim($data['email']));
            $enabled = ! array_key_exists('enabled', $data)
                || in_array(strtolower((string) $data['enabled']), ['1', 'true'], true);

            $request->user()->senderAccounts()->updateOrCreate(
                ['email' => $email],
                [
                    'from_name' => trim((string) ($data['from_name'] ?? '')) ?: null,
                    'smtp_host' => trim((string) ($data['smtp_host'] ?? '')) ?: 'smtp.gmail.com',
                    'smtp_port' => (int) ($data['smtp_port'] ?? 587),
                    'smtp_scheme' => $data['smtp_scheme'] ?? 'smtp',
                    'smtp_username' => trim((string) ($data['smtp_username'] ?? '')) ?: $email,
                    'smtp_password' => $data['smtp_password'],
                    'daily_limit' => (int) ($data['daily_limit'] ?? 20),
                    'enabled' => $enabled,
                ],
            );

            $imported++;
        }

        fclose($handle);

        return back()->with(
            'success',
            "Sender CSV imported: {$imported} saved, {$skipped} skipped.",
        );
    }

    public function toggle(Request $request, SenderAccount $senderAccount): RedirectResponse
    {
        abort_unless($senderAccount->user_id === $request->user()->id, 404);

        $senderAccount->update([
            'enabled' => ! $senderAccount->enabled,
        ]);

        return back()->with(
            'success',
            $senderAccount->enabled ? 'Sender enabled.' : 'Sender disabled.',
        );
    }

    public function startAutomation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'global_daily_limit' => ['required', 'integer', 'between:1,100'],
            'interval_minutes' => ['required', 'integer', 'between:5,120'],
            'session_hours' => ['required', 'integer', 'between:1,12'],
        ]);

        if (! $request->user()->senderAccounts()->where('enabled', true)->exists()) {
            return back()->with(
                'error',
                'Add and enable at least one sender account before starting automatic delivery.',
            );
        }

        $request->user()->deliveryAutomationSetting()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'enabled' => true,
                'global_daily_limit' => (int) $validated['global_daily_limit'],
                'interval_minutes' => (int) $validated['interval_minutes'],
                'session_hours' => (int) $validated['session_hours'],
                'session_started_at' => now(),
                'session_ends_at' => now()->addHours((int) $validated['session_hours']),
                'last_attempt_at' => null,
                'next_attempt_at' => null,
                'last_status' => 'active',
                'last_message' => 'Automatic delivery session started. Only human-approved email drafts can be sent.',
            ],
        );

        return back()->with(
            'success',
            'Automatic delivery session started. Review-before-send remains required.',
        );
    }

    public function stopAutomation(Request $request): RedirectResponse
    {
        $setting = $request->user()->deliveryAutomationSetting;

        if ($setting !== null) {
            $setting->update([
                'enabled' => false,
                'next_attempt_at' => null,
                'last_status' => 'stopped',
                'last_message' => 'Automatic delivery was stopped manually.',
            ]);
        }

        return back()->with('success', 'Automatic delivery stopped.');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'email',
                'from_name',
                'smtp_host',
                'smtp_port',
                'smtp_scheme',
                'smtp_username',
                'smtp_password',
                'daily_limit',
                'enabled',
            ]);

            fputcsv($handle, [
                'sender@example.com',
                'Mehedi',
                'smtp.gmail.com',
                '587',
                'smtp',
                'sender@example.com',
                'PASTE_APP_PASSWORD_HERE',
                '20',
                '1',
            ]);

            fclose($handle);
        }, 'unlucky13-sender-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
