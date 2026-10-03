<?php

namespace App\Services\Worker;

use Illuminate\Support\Facades\Process;
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

        $pid = $this->readPid();

        if ($pid === null) {
            return [
                'supported' => true,
                'running' => false,
                'pid' => null,
                'message' => 'Worker is stopped.',
            ];
        }

        if (! $this->isExpectedWorkerProcess($pid)) {
            $this->clearPid();

            return [
                'supported' => true,
                'running' => false,
                'pid' => null,
                'message' => 'Worker is stopped.',
            ];
        }

        return [
            'supported' => true,
            'running' => true,
            'pid' => $pid,
            'message' => 'Worker is running.',
        ];
    }

    public function start(): array
    {
        $status = $this->status();

        if (! $status['supported']) {
            throw new RuntimeException($status['message']);
        }

        if ($status['running']) {
            return $status;
        }

        $runtimeDir = storage_path('app/worker-runtime');
        $logDir = storage_path('logs');

        if (! is_dir($runtimeDir) && ! mkdir($runtimeDir, 0775, true) && ! is_dir($runtimeDir)) {
            throw new RuntimeException('Could not create worker runtime directory.');
        }

        if (! is_dir($logDir) && ! mkdir($logDir, 0775, true) && ! is_dir($logDir)) {
            throw new RuntimeException('Could not create worker log directory.');
        }

        $repoRoot = base_path();
        $workerScript = base_path('worker/run.py');
        $stdout = storage_path('logs/unlucky13-dashboard-worker.log');
        $stderr = storage_path('logs/unlucky13-dashboard-worker-error.log');

        if (! is_file($workerScript)) {
            throw new RuntimeException('worker/run.py was not found.');
        }

        $script = implode('; ', [
            '$python = (Get-Command python -ErrorAction Stop).Source',
            '$process = Start-Process'
                .' -FilePath $python'
                .' -ArgumentList @('.$this->psQuote($workerScript).", 'work')"
                .' -WorkingDirectory '.$this->psQuote($repoRoot)
                .' -WindowStyle Hidden'
                .' -RedirectStandardOutput '.$this->psQuote($stdout)
                .' -RedirectStandardError '.$this->psQuote($stderr)
                .' -PassThru',
            'Write-Output $process.Id',
        ]);

        $result = Process::timeout(15)->run([
            'powershell.exe',
            '-NoLogo',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            $script,
        ]);

        if (! $result->successful()) {
            $message = trim($result->errorOutput()) ?: trim($result->output());
            throw new RuntimeException(
                $message !== '' ? $message : 'Could not start the worker process.',
            );
        }

        $pid = (int) trim($result->output());

        if ($pid < 1) {
            throw new RuntimeException('Worker started without returning a valid process ID.');
        }

        file_put_contents($this->pidPath(), (string) $pid, LOCK_EX);
        usleep(350000);

        if (! $this->isExpectedWorkerProcess($pid)) {
            $this->clearPid();

            throw new RuntimeException(
                'Worker exited immediately. Check storage/logs/unlucky13-dashboard-worker-error.log.',
            );
        }

        return [
            'supported' => true,
            'running' => true,
            'pid' => $pid,
            'message' => 'Worker started.',
        ];
    }

    public function stop(): array
    {
        $status = $this->status();

        if (! $status['supported']) {
            throw new RuntimeException($status['message']);
        }

        if (! $status['running'] || ! $status['pid']) {
            return [
                'supported' => true,
                'running' => false,
                'pid' => null,
                'message' => 'Worker is already stopped.',
            ];
        }

        $pid = (int) $status['pid'];

        $result = Process::timeout(15)->run([
            'powershell.exe',
            '-NoLogo',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            "Stop-Process -Id {$pid} -Force -ErrorAction Stop",
        ]);

        if (! $result->successful()) {
            $message = trim($result->errorOutput()) ?: trim($result->output());
            throw new RuntimeException(
                $message !== '' ? $message : 'Could not stop the worker process.',
            );
        }

        $this->clearPid();

        return [
            'supported' => true,
            'running' => false,
            'pid' => null,
            'message' => 'Worker stopped.',
        ];
    }

    private function isExpectedWorkerProcess(int $pid): bool
    {
        if ($pid < 1) {
            return false;
        }

        $result = Process::timeout(10)->run([
            'powershell.exe',
            '-NoLogo',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            '$process = Get-CimInstance Win32_Process -Filter "ProcessId = '.$pid.'" -ErrorAction SilentlyContinue; if ($null -eq $process) { exit 1 }; Write-Output $process.CommandLine',
        ]);

        if (! $result->successful()) {
            return false;
        }

        $commandLine = strtolower(trim($result->output()));
        $workerScript = strtolower(str_replace('/', '\\', base_path('worker/run.py')));

        return str_contains(str_replace('/', '\\', $commandLine), $workerScript)
            && str_contains($commandLine, 'work');
    }

    private function readPid(): ?int
    {
        $path = $this->pidPath();

        if (! is_file($path)) {
            return null;
        }

        $pid = (int) trim((string) file_get_contents($path));

        return $pid > 0 ? $pid : null;
    }

    private function clearPid(): void
    {
        $path = $this->pidPath();

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function pidPath(): string
    {
        return storage_path('app/worker-runtime/worker.pid');
    }

    private function psQuote(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }
}
