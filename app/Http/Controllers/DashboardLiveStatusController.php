<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardActivityService;
use App\Services\Worker\LocalWorkerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardLiveStatusController extends Controller
{
    public function __invoke(
        Request $request,
        LocalWorkerManager $workerManager,
        DashboardActivityService $activityService,
    ): JsonResponse {
        $workerStatus = $workerManager->status();
        $activity = $activityService->snapshot($request->user(), $workerStatus);

        return response()->json([
            'worker' => $workerStatus,
            'activity' => $activity,
            'refreshed_at' => now()->toISOString(),
        ]);
    }
}
