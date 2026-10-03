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


## Dashboard reporting semantics

The main dashboard cards are factual totals and are refreshed from Laravel every 30 seconds while the page is open.

- Campaigns: total campaigns owned by the signed-in user
- Businesses found: total unique lead records saved
- Qualified: cumulative leads that have ever qualified (qualified_at is set), even if they later move to review/contacted/replied states
- Review queue: leads currently waiting for human review
- Interested: leads currently marked interested

The 30-second dashboard refresh is local database polling only. It does not increase public-source discovery frequency. OpenStreetMap discovery remains on the conservative per-campaign scheduler interval.


## Discovery cadence

The managed worker now uses a 30-minute discovery interval per active campaign.

- If the worker stays running, each active campaign becomes eligible for fresh discovery 30 minutes after its last successful discovery.
- If the PC or worker is stopped, no work runs in the background.
- When the worker is started again, a campaign whose last successful discovery is already 30 minutes old is due immediately on the next scheduler cycle.
- Missed intervals are not replayed in bulk; the worker performs one due discovery run and resets the campaign's next 30-minute window.
- Each due discovery run still targets up to 10 fresh businesses and global domain deduplication remains active.

Dashboard polling remains every 30 seconds and is separate from external discovery.
