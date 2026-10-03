<?php

namespace App\Http\Controllers;

use App\Services\Worker\LocalWorkerManager;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class WorkerRuntimeController extends Controller
{
    public function start(LocalWorkerManager $workerManager): RedirectResponse
    {
        try {
            $status = $workerManager->start();

            return back()->with(
                'success',
                $status['running']
                    ? 'Automation worker is running. Active campaigns will be processed automatically.'
                    : $status['message'],
            );
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }
    }

    public function stop(LocalWorkerManager $workerManager): RedirectResponse
    {
        try {
            $workerManager->stop();

            return back()->with(
                'success',
                'Automation worker stopped. Campaign data and Active/Pause states were preserved.',
            );
        } catch (RuntimeException $error) {
            return back()->with('error', $error->getMessage());
        }
    }
}
