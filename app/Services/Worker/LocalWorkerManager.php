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
            ];
        }

        $state = $this->readState();

        if ($state === null) {
            return $this->stoppedStatus();
        }

        $runtimeState = (string) ($state['state'] ?? 'stopped');
        $heartbeatAt = strtotime((string) ($state['heartbeat_at'] ?? ''));

        if (
            in_array($runtimeState, ['starting', 'running', 'stopping'], true)
            && ($heartbeatAt === false || (time() - $heartbeatAt) > self::HEARTBEAT_STALE_SECONDS)
        ) {
            return $this->stoppedStatus(
                'Worker heartbeat is stale. Start it again when you are ready.',
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

        $this->writeState([
            'state' => 'starting',
            'pid' => null,
            'heartbeat_at' => gmdate('c'),
            'message' => 'Worker is starting.',
        ]);

        $workerScript = base_path('worker/run.py');
        $stdout = storage_path('logs/unlucky13-dashboard-worker.log');
        $stderr = storage_path('logs/unlucky13-dashboard-worker-error.log');

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
            'cmd.exe /D /C start "" /B python %s work --managed 1>>%s 2>>%s',
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
            'supported' => true,
            'running' => false,
            'state' => 'stopping',
            'pid' => $status['pid'],
            'message' => 'Worker stop requested.',
        ];
    }

    private function stoppedStatus(string $message = 'Worker is stopped.'): array
    {
        return [
            'supported' => true,
            'running' => false,
            'state' => 'stopped',
            'pid' => null,
            'message' => $message,
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

        file_put_contents(
            $this->statePath(),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
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

    private function statePath(): string
    {
        return storage_path('app/worker-runtime/worker-state.json');
    }

    private function stopPath(): string
    {
        return storage_path('app/worker-runtime/worker-stop.request');
    }

    private function quoteWindows(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
