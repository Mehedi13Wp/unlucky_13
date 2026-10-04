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

        return view('worker.index', [
            'workerStatus' => $workerStatus,
            'activity' => $activity,
            'stdoutLines' => $this->tailLog(storage_path('logs/unlucky13-dashboard-worker.log')),
            'stderrLines' => $this->tailLog(storage_path('logs/unlucky13-dashboard-worker-error.log')),
        ]);
    }

    public function live(
        Request $request,
        LocalWorkerManager $workerManager,
        DashboardActivityService $activityService,
    ): JsonResponse {
        $workerStatus = $workerManager->status();

        return response()->json([
            'worker' => $workerStatus,
            'activity' => $activityService->snapshot($request->user(), $workerStatus),
            'stdout' => $this->tailLog(storage_path('logs/unlucky13-dashboard-worker.log')),
            'stderr' => $this->tailLog(storage_path('logs/unlucky13-dashboard-worker-error.log')),
            'refreshed_at' => now()->toISOString(),
        ]);
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
