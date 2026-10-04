<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardActivityService;
use App\Services\Worker\LocalWorkerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkerWorkspaceController extends Controller
{
    public function index(
        Request $request,
        LocalWorkerManager $workerManager,
        DashboardActivityService $activityService,
    ): View {
        $workerStatus = $workerManager->status();
        $activity = $activityService->snapshot($request->user(), $workerStatus);

        $stdoutPath = storage_path('logs/unlucky13-dashboard-worker.log');
        $stderrPath = storage_path('logs/unlucky13-dashboard-worker-error.log');

        return view('worker.index', [
            'workerStatus' => $workerStatus,
            'activity' => $activity,
            'stdoutLines' => $this->tailLog($stdoutPath),
            'stderrLines' => $this->tailLog($stderrPath),
            'stdoutMeta' => $this->logMeta($stdoutPath, $workerStatus['started_at'] ?? null),
            'stderrMeta' => $this->logMeta($stderrPath, $workerStatus['started_at'] ?? null),
            'analysisTelemetry' => $this->analysisTelemetry(),
        ]);
    }

    public function live(
        Request $request,
        LocalWorkerManager $workerManager,
        DashboardActivityService $activityService,
    ): JsonResponse {
        $workerStatus = $workerManager->status();

        $stdoutPath = storage_path('logs/unlucky13-dashboard-worker.log');
        $stderrPath = storage_path('logs/unlucky13-dashboard-worker-error.log');

        return response()->json([
            'worker' => $workerStatus,
            'activity' => $activityService->snapshot($request->user(), $workerStatus),
            'stdout' => $this->tailLog($stdoutPath),
            'stderr' => $this->tailLog($stderrPath),
            'stdout_meta' => $this->logMeta($stdoutPath, $workerStatus['started_at'] ?? null),
            'stderr_meta' => $this->logMeta($stderrPath, $workerStatus['started_at'] ?? null),
            'analysis' => $this->analysisTelemetry(),
            'refreshed_at' => now()->toISOString(),
        ]);
    }

    private function analysisTelemetry(): array
    {
        $path = storage_path('app/worker-runtime/last-analysis.json');

        if (! is_file($path) || ! is_readable($path)) {
            return [
                'attempted_at' => null,
                'queued' => 0,
                'completed' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        $contents = @file_get_contents($path);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (! is_array($decoded)) {
            return [
                'attempted_at' => null,
                'queued' => 0,
                'completed' => 0,
                'skipped' => 0,
                'failed' => 0,
            ];
        }

        return [
            'attempted_at' => $decoded['attempted_at'] ?? null,
            'queued' => (int) ($decoded['queued'] ?? 0),
            'completed' => (int) ($decoded['completed'] ?? 0),
            'skipped' => (int) ($decoded['skipped'] ?? 0),
            'failed' => (int) ($decoded['failed'] ?? 0),
        ];
    }

    private function logMeta(string $path, ?string $startedAt): array
    {
        if (! is_file($path)) {
            return [
                'exists' => false,
                'modified_at' => null,
                'is_current_session' => false,
                'size_bytes' => 0,
            ];
        }

        $modified = filemtime($path);
        $started = $startedAt ? strtotime($startedAt) : false;

        return [
            'exists' => true,
            'modified_at' => $modified !== false ? date(DATE_ATOM, $modified) : null,
            'is_current_session' => $modified !== false
                && $started !== false
                && $modified >= $started,
            'size_bytes' => (int) (filesize($path) ?: 0),
        ];
    }

    private function tailLog(string $path, int $maxLines = 80, int $maxBytes = 65536): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $size = filesize($path);

        if ($size === false || $size <= 0) {
            return [];
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        try {
            $readBytes = min($size, $maxBytes);

            if ($size > $readBytes) {
                fseek($handle, -$readBytes, SEEK_END);
            }

            $contents = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        if (! is_string($contents) || trim($contents) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $lines = array_values(array_filter(
            array_map(
                fn ($line) => mb_substr(trim((string) $line), 0, 1200),
                $lines,
            ),
            fn ($line) => $line !== '',
        ));

        return array_slice($lines, -$maxLines);
    }
}
