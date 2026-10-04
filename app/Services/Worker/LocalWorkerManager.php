<?php

namespace App\Services\Worker;

use RuntimeException;

class LocalWorkerManager
{
    private const HEARTBEAT_STALE_SECONDS = 30;

    public function status(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [
                'supported' => false,
                'running' => false,
                'state' => 'unsupported',
                'pid' => null,
                'message' => 'Dashboard worker control is currently configured for Windows.',
                'heartbeat_at' => null,
                'heartbeat_age_seconds' => null,
                'last_cycle_at' => null,
                'last_cycle' => [],
            ];
        }

        $state = $this->readState();

        if ($state === null) {
            return $this->stoppedStatus();
        }

        $runtimeState = (string) ($state['state'] ?? 'stopped');
        $heartbeatAt = $this->timestamp($state['heartbeat_at'] ?? null);
        $heartbeatAge = $heartbeatAt !== null ? max(0, time() - $heartbeatAt) : null;

        if (
            in_array($runtimeState, ['starting', 'running', 'stopping'], true)
            && ($heartbeatAge === null || $heartbeatAge > self::HEARTBEAT_STALE_SECONDS)
        ) {
            return $this->stoppedStatus(
                'Worker heartbeat is stale. Start it again when you are ready.',
                $state,
            );
        }

        return [
            'supported' => true,
            'running' => in_array($runtimeState, ['starting', 'running'], true),
            'state' => $runtimeState,
            'pid' => isset($state['pid']) && $state['pid'] !== null
                ? (int) $state['pid']
                : null,
            'message' => (string) ($state['message'] ?? 'Worker status updated.'),
            'started_at' => $state['started_at'] ?? null,
            'heartbeat_at' => $state['heartbeat_at'] ?? null,
            'heartbeat_age_seconds' => $heartbeatAge,
            'last_cycle_at' => $state['last_cycle_at'] ?? null,
            'last_cycle' => is_array($state['last_cycle'] ?? null)
                ? $state['last_cycle']
                : [],
        ];
    }

    public function start(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException(
                'Dashboard worker control is currently configured for Windows.',
            );
        }

        $status = $this->status();

        if (in_array($status['state'], ['starting', 'running'], true)) {
            return $status;
        }

        $this->ensureRuntimeDirectories();
        @unlink($this->stopPath());

        $workerScript = base_path('worker/run.py');
        $stdout = storage_path('logs/unlucky13-dashboard-worker.log');
        $stderr = storage_path('logs/unlucky13-dashboard-worker-error.log');

        $this->rotateLog($stdout);
        $this->rotateLog($stderr);

        $startedAt = gmdate('c');

        $this->writeState([
            'state' => 'starting',
            'pid' => null,
            'started_at' => $startedAt,
            'heartbeat_at' => $startedAt,
            'message' => 'Worker is starting.',
        ]);

        if (! is_file($workerScript)) {
            $this->writeState([
                'state' => 'stopped',
                'pid' => null,
                'heartbeat_at' => gmdate('c'),
                'message' => 'worker/run.py was not found.',
            ]);

            throw new RuntimeException('worker/run.py was not found.');
        }

        $command = sprintf(
            'cmd.exe /D /C start "" /B python -u %s work --managed 1>>%s 2>>%s',
            $this->quoteWindows($workerScript),
            $this->quoteWindows($stdout),
            $this->quoteWindows($stderr),
        );

        $handle = @popen($command, 'r');

        if ($handle === false) {
            $this->writeState([
                'state' => 'stopped',
                'pid' => null,
                'heartbeat_at' => gmdate('c'),
                'message' => 'Windows could not launch the worker process.',
            ]);

            throw new RuntimeException('Windows could not launch the worker process.');
        }

        pclose($handle);

        return [
            'supported' => true,
            'running' => true,
            'state' => 'starting',
            'pid' => null,
            'message' => 'Worker is starting in the background.',
            'started_at' => $startedAt,
            'heartbeat_at' => $startedAt,
            'heartbeat_age_seconds' => 0,
            'last_cycle_at' => $status['last_cycle_at'] ?? null,
            'last_cycle' => $status['last_cycle'] ?? [],
        ];
    }

    public function stop(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException(
                'Dashboard worker control is currently configured for Windows.',
            );
        }

        $status = $this->status();

        if ($status['state'] === 'stopped') {
            return $status;
        }

        $this->ensureRuntimeDirectories();
        file_put_contents($this->stopPath(), gmdate('c'), LOCK_EX);

        $this->writeState([
            'state' => 'stopping',
            'pid' => $status['pid'],
            'heartbeat_at' => gmdate('c'),
            'message' => 'Worker is stopping after the current operation.',
        ]);

        return [
            ...$status,
            'running' => false,
            'state' => 'stopping',
            'message' => 'Worker stop requested.',
        ];
    }

    private function stoppedStatus(
        string $message = 'Worker is stopped.',
        array $state = [],
    ): array {
        return [
            'supported' => true,
            'running' => false,
            'state' => 'stopped',
            'pid' => null,
            'message' => $message,
            'started_at' => $state['started_at'] ?? null,
            'heartbeat_at' => $state['heartbeat_at'] ?? null,
            'heartbeat_age_seconds' => null,
            'last_cycle_at' => $state['last_cycle_at'] ?? null,
            'last_cycle' => is_array($state['last_cycle'] ?? null)
                ? $state['last_cycle']
                : [],
        ];
    }

    private function readState(): ?array
    {
        $path = $this->statePath();

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        if (! is_string($contents) || trim($contents) === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function writeState(array $state): void
    {
        $this->ensureRuntimeDirectories();

        $current = $this->readState() ?? [];
        $current = array_merge($current, $state);

        file_put_contents(
            $this->statePath(),
            json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX,
        );
    }

    private function ensureRuntimeDirectories(): void
    {
        foreach ([dirname($this->statePath()), storage_path('logs')] as $directory) {
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException("Could not create runtime directory: {$directory}");
            }
        }
    }

    private function timestamp(mixed $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }

    private function statePath(): string
    {
        return storage_path('app/worker-runtime/worker-state.json');
    }

    private function stopPath(): string
    {
        return storage_path('app/worker-runtime/worker-stop.request');
    }

    private function rotateLog(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            return;
        }

        $previous = $path.'.previous';
        @unlink($previous);
        @rename($path, $previous);
    }

    private function quoteWindows(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
