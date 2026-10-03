# Dashboard Worker Control

For the local Windows workflow, Laravel remains under the developer's direct control and the Python automation worker can be started or stopped from the authenticated dashboard.

## Normal local workflow

Start Laravel manually:

    php artisan serve --host=127.0.0.1 --port=8000

Open:

    http://127.0.0.1:8000/dashboard

The dashboard shows an Automation worker card with:

- Start Worker
- Stop Worker
- RUNNING / STOPPED state
- active campaign count
- Review Before Send state

Starting the worker launches this process in the background:

    python worker/run.py work

Stopping it terminates only the dashboard-managed worker. Campaign records and Active/Pause states are preserved.

Runtime state:

    storage/app/worker-runtime/worker.pid

Logs:

    storage/logs/unlucky13-dashboard-worker.log
    storage/logs/unlucky13-dashboard-worker-error.log

The dashboard does not start or stop Laravel itself. This is intentional: stopping Laravel from its own dashboard would make the dashboard unavailable.

## Windows auto-start

Windows Task Scheduler scripts under scripts/windows are optional. Do not install them if the preferred workflow is manual Laravel startup plus dashboard Start/Stop control.


## Live dashboard proof

The dashboard now polls a protected live-status endpoint every 10 seconds and shows:

- worker heartbeat
- last completed scheduler cycle
- last successful campaign discovery
- new leads today
- analyzed leads today
- outreach drafts generated today
- live Businesses found, Qualified, Review queue, and Active campaign counts

The managed Python worker writes heartbeat and cycle telemetry under:

    storage/app/worker-runtime/worker-state.json

This file is local runtime state and is not a source of business data. Campaign, lead, analysis, and draft counts still come from Laravel's database.
