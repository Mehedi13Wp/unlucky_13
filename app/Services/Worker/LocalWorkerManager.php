<?php

namespace App\Services\Worker;

use RuntimeException;

class LocalWorkerManager
{
    public function status(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [
                'supported' => false,
                'running' => false,
                'pid' => null,
                'message' => 'Dashboard worker control is currently configured for Windows.',
            ];
        }

        try {
            return $this->runControl('status');
        } catch (RuntimeException $error) {
            return [
                'supported' => true,
                'running' => false,
                'pid' => null,
                'message' => $error->getMessage(),
            ];
        }
    }

    public function start(): array
    {
        $status = $this->runControl('start');

        if (! $status['running']) {
            throw new RuntimeException($status['message'] ?: 'Could not start the worker process.');
        }

        return $status;
    }

    public function stop(): array
    {
        return $this->runControl('stop');
    }

    private function runControl(string $action): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException(
                'Dashboard worker control is currently configured for Windows.',
            );
        }

        if (! function_exists('exec')) {
            throw new RuntimeException(
                'PHP exec() is unavailable, so the local worker cannot be controlled from the dashboard.',
            );
        }

        $script = base_path('scripts/windows/dashboard-worker-control.ps1');

        if (! is_file($script)) {
            throw new RuntimeException('Dashboard worker control script was not found.');
        }

        $command = sprintf(
            'powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File %s -Action %s -RepoRoot %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($action),
            escapeshellarg(base_path()),
        );

        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        $payload = $this->decodePayload($output);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                $payload['message'] ?? (trim(implode(PHP_EOL, $output)) ?: 'Worker control command failed.'),
            );
        }

        return [
            'supported' => (bool) ($payload['supported'] ?? true),
            'running' => (bool) ($payload['running'] ?? false),
            'pid' => isset($payload['pid']) && $payload['pid'] !== null
                ? (int) $payload['pid']
                : null,
            'message' => (string) ($payload['message'] ?? ''),
        ];
    }

    private function decodePayload(array $output): array
    {
        for ($index = count($output) - 1; $index >= 0; $index--) {
            $line = trim((string) $output[$index]);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException(
            trim(implode(PHP_EOL, $output)) ?: 'Worker control returned no readable response.',
        );
    }
}
