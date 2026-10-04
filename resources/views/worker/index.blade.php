@extends('layouts.app')

@section('title', 'Worker')
@section('heading', 'Worker')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/worker.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-secondary" href="{{ route('reports.index') }}">Open reports</a>
@endsection

@section('content')
    @php
        $state = $workerStatus['state'] ?? 'stopped';
        $runningState = in_array($state, ['starting', 'running'], true);
        $startDisabled = in_array($state, ['starting', 'running', 'stopping'], true) || ! ($workerStatus['supported'] ?? false);
        $stopDisabled = ! in_array($state, ['starting', 'running'], true) || ! ($workerStatus['supported'] ?? false);
        $lastCycle = $workerStatus['last_cycle'] ?? [];
    @endphp

    <section
        class="worker-hero"
        id="worker-workspace"
        data-live-url="{{ route('worker.live-status') }}"
    >
        <div>
            <p class="eyebrow">Automation control</p>
            <h2>See exactly what the worker is doing.</h2>
            <p>Start or stop automation, inspect heartbeat and cycle health, and read recent worker output without opening another terminal.</p>
        </div>

        <div class="worker-runtime-card">
            <div class="worker-runtime-head">
                <div>
                    <span>Runtime state</span>
                    <strong id="workspace-state-label">{{ strtoupper($state) }}</strong>
                </div>
                <span id="workspace-state-dot" class="worker-runtime-dot {{ $runningState ? 'running' : 'stopped' }}"></span>
            </div>

            <p id="workspace-state-message">{{ $workerStatus['message'] ?? 'Worker status unavailable.' }}</p>

            <div class="worker-control-actions">
                <form method="POST" action="{{ route('worker.start') }}">
                    @csrf
                    <button id="workspace-start" class="button button-primary" type="submit" @disabled($startDisabled)>
                        Start Worker
                    </button>
                </form>

                <form method="POST" action="{{ route('worker.stop') }}">
                    @csrf
                    <button id="workspace-stop" class="button button-secondary" type="submit" @disabled($stopDisabled)>
                        Stop Worker
                    </button>
                </form>
            </div>
        </div>
    </section>

    <section class="worker-metric-grid">
        <article>
            <span>Heartbeat</span>
            <strong id="workspace-heartbeat">{{ $activity['heartbeat_label'] }}</strong>
            <small>PID <span id="workspace-pid">{{ $workerStatus['pid'] ?? '—' }}</span></small>
        </article>
        <article>
            <span>Last cycle</span>
            <strong id="workspace-last-cycle">{{ $activity['last_cycle_label'] }}</strong>
            <small>Scheduler health</small>
        </article>
        <article>
            <span>Last discovery</span>
            <strong id="workspace-last-discovery">{{ $activity['last_discovery_label'] }}</strong>
            <small id="workspace-discovery-detail">{{ $activity['discovery_detail'] }}</small>
        </article>
        <article>
            <span>Active campaigns</span>
            <strong id="workspace-active-campaigns">{{ number_format($activity['active_campaigns']) }}</strong>
            <small>Eligible for automation</small>
        </article>
    </section>

    <section class="worker-grid">
        <article class="worker-panel">
            <header>
                <div>
                    <p class="eyebrow">Latest scheduler cycle</p>
                    <h3>Pipeline health</h3>
                </div>
                <span id="workspace-cycle-health" class="worker-health {{ (($lastCycle['discovery_failed'] ?? 0) + ($lastCycle['pipeline_failed'] ?? 0)) > 0 ? 'attention' : 'good' }}">
                    {{ (($lastCycle['discovery_failed'] ?? 0) + ($lastCycle['pipeline_failed'] ?? 0)) > 0 ? 'Needs attention' : 'Healthy' }}
                </span>
            </header>

            <div class="worker-cycle-grid">
                <div><span>Campaigns</span><strong id="cycle-campaigns">{{ $lastCycle['active_campaigns'] ?? 0 }}</strong></div>
                <div><span>Discovery due</span><strong id="cycle-due">{{ $lastCycle['discovery_due'] ?? 0 }}</strong></div>
                <div><span>Discovery completed</span><strong id="cycle-completed">{{ $lastCycle['discovery_completed'] ?? 0 }}</strong></div>
                <div><span>Discovery failed</span><strong id="cycle-discovery-failed">{{ $lastCycle['discovery_failed'] ?? 0 }}</strong></div>
                <div><span>Pipeline failed</span><strong id="cycle-pipeline-failed">{{ $lastCycle['pipeline_failed'] ?? 0 }}</strong></div>
            </div>

            <div class="worker-today">
                <div><span>New leads today</span><strong id="worker-leads-today">{{ $activity['new_leads_today'] }}</strong></div>
                <div><span>Analyzed today</span><strong id="worker-analyzed-today">{{ $activity['analyzed_today'] }}</strong></div>
                <div><span>Drafts today</span><strong id="worker-drafts-today">{{ $activity['drafts_today'] }}</strong></div>
            </div>
        </article>

        <article class="worker-panel">
            <header>
                <div>
                    <p class="eyebrow">Discovery snapshot</p>
                    <h3>Last attempt</h3>
                </div>
            </header>

            <div class="worker-cycle-grid worker-cycle-grid-four">
                <div><span>Scanned</span><strong id="discovery-scanned">{{ $activity['discovery_candidates_scanned'] }}</strong></div>
                <div><span>Fresh</span><strong id="discovery-fresh">{{ $activity['discovery_fresh'] }}</strong></div>
                <div><span>Duplicates</span><strong id="discovery-duplicates">{{ $activity['discovery_duplicates_skipped'] }}</strong></div>
                <div><span>Errors</span><strong id="discovery-errors">{{ $activity['discovery_failed'] }}</strong></div>
            </div>

            <p class="worker-note">
                Worker automation handles discovery, website analysis, contact discovery, and draft generation. Approved email sending remains a human action.
            </p>
        </article>

        <article class="worker-panel">
            <header>
                <div>
                    <p class="eyebrow">Analysis snapshot</p>
                    <h3>Last analysis batch</h3>
                </div>
                <span class="worker-health {{ ($analysisTelemetry['failed'] ?? 0) > 0 ? 'attention' : 'good' }}" id="analysis-health">
                    {{ ($analysisTelemetry['failed'] ?? 0) > 0 ? 'System errors' : 'Healthy' }}
                </span>
            </header>

            <div class="worker-cycle-grid worker-cycle-grid-four">
                <div><span>Queued</span><strong id="analysis-queued">{{ $analysisTelemetry['queued'] ?? 0 }}</strong></div>
                <div><span>Completed</span><strong id="analysis-completed">{{ $analysisTelemetry['completed'] ?? 0 }}</strong></div>
                <div><span>Skipped sites</span><strong id="analysis-skipped">{{ $analysisTelemetry['skipped'] ?? 0 }}</strong></div>
                <div><span>System errors</span><strong id="analysis-failed">{{ $analysisTelemetry['failed'] ?? 0 }}</strong></div>
            </div>

            <p class="worker-note">
                A blocked, broken, unreachable, or certificate-mismatched website is counted as Skipped, not as a worker failure. Only API or internal processing problems count as System errors.
            </p>
        </article>
    </section>

    <section class="worker-log-grid">
        <article class="worker-panel worker-log-panel">
            <header>
                <div>
                    <p class="eyebrow">Worker output</p>
                    <h3>Recent activity log</h3>
                </div>
                <div class="worker-log-tools">
                    <span class="worker-live-badge"><span></span> Live</span>
                    <button class="button button-secondary button-small" type="button" id="worker-refresh">Refresh now</button>
                </div>
            </header>
            <div class="worker-log-status">
                <span id="worker-last-refreshed">Auto-refreshing every 5 seconds</span>
                <span id="worker-stdout-status">
                    {{ ($stdoutMeta['is_current_session'] ?? false) ? 'Current worker session' : 'No current-session output yet' }}
                </span>
            </div>
            <pre id="worker-stdout">{{ empty($stdoutLines) ? 'No worker output has been recorded yet.' : implode("\n", $stdoutLines) }}</pre>
        </article>

        <article class="worker-panel worker-log-panel">
            <header>
                <div>
                    <p class="eyebrow">Worker errors</p>
                    <h3>Recent error log</h3>
                </div>
            </header>
            <div class="worker-log-status">
                <span>Error stream</span>
                <span id="worker-stderr-status">
                    @if (empty($stderrLines))
                        No errors in the current log
                    @elseif ($stderrMeta['is_current_session'] ?? false)
                        Current-session errors detected
                    @else
                        Historical errors from an older run
                    @endif
                </span>
            </div>
            <pre id="worker-stderr">{{ empty($stderrLines) ? 'No worker errors recorded.' : implode("\n", $stderrLines) }}</pre>
        </article>
    </section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('worker-workspace');

    if (!root) {
        return;
    }

    const liveUrl = root.dataset.liveUrl;
    const refreshButton = document.getElementById('worker-refresh');
    const refreshedNode = document.getElementById('worker-last-refreshed');

    const setText = (id, value) => {
        const node = document.getElementById(id);
        if (node && value !== undefined && value !== null) {
            node.textContent = value;
        }
    };

    const renderLines = (id, lines, emptyText) => {
        const node = document.getElementById(id);
        if (!node) return;
        node.textContent = Array.isArray(lines) && lines.length ? lines.join('\n') : emptyText;
        node.scrollTop = node.scrollHeight;
    };

    const apply = (payload) => {
        const worker = payload.worker || {};
        const activity = payload.activity || {};
        const cycle = worker.last_cycle || {};
        const state = worker.state || 'stopped';
        const running = ['starting', 'running'].includes(state);

        setText('workspace-state-label', state.toUpperCase());
        setText('workspace-state-message', worker.message || 'Worker status updated.');
        setText('workspace-pid', worker.pid || '—');
        setText('workspace-heartbeat', activity.heartbeat_label || 'Waiting for worker');
        setText('workspace-last-cycle', activity.last_cycle_label || 'No cycle yet');
        setText('workspace-last-discovery', activity.last_discovery_label || 'No discovery yet');
        setText('workspace-discovery-detail', activity.discovery_detail || '');
        setText('workspace-active-campaigns', activity.active_campaigns ?? 0);
        setText('cycle-campaigns', cycle.active_campaigns ?? 0);
        setText('cycle-due', cycle.discovery_due ?? 0);
        setText('cycle-completed', cycle.discovery_completed ?? 0);
        setText('cycle-discovery-failed', cycle.discovery_failed ?? 0);
        setText('cycle-pipeline-failed', cycle.pipeline_failed ?? 0);
        setText('worker-leads-today', activity.new_leads_today ?? 0);
        setText('worker-analyzed-today', activity.analyzed_today ?? 0);
        setText('worker-drafts-today', activity.drafts_today ?? 0);
        setText('discovery-scanned', activity.discovery_candidates_scanned ?? 0);
        setText('discovery-fresh', activity.discovery_fresh ?? 0);
        setText('discovery-duplicates', activity.discovery_duplicates_skipped ?? 0);
        setText('discovery-errors', activity.discovery_failed ?? 0);

        const analysis = payload.analysis || {};
        setText('analysis-queued', analysis.queued ?? 0);
        setText('analysis-completed', analysis.completed ?? 0);
        setText('analysis-skipped', analysis.skipped ?? 0);
        setText('analysis-failed', analysis.failed ?? 0);

        const analysisHealth = document.getElementById('analysis-health');
        if (analysisHealth) {
            const analysisFailures = Number(analysis.failed || 0);
            analysisHealth.textContent = analysisFailures > 0 ? 'System errors' : 'Healthy';
            analysisHealth.classList.toggle('attention', analysisFailures > 0);
            analysisHealth.classList.toggle('good', analysisFailures === 0);
        }

        const dot = document.getElementById('workspace-state-dot');
        if (dot) {
            dot.classList.toggle('running', running);
            dot.classList.toggle('stopped', !running);
        }

        const start = document.getElementById('workspace-start');
        const stop = document.getElementById('workspace-stop');
        if (start) start.disabled = ['starting', 'running', 'stopping'].includes(state) || worker.supported === false;
        if (stop) stop.disabled = !['starting', 'running'].includes(state) || worker.supported === false;

        const failures = Number(cycle.discovery_failed || 0) + Number(cycle.pipeline_failed || 0);
        const health = document.getElementById('workspace-cycle-health');
        if (health) {
            health.textContent = failures > 0 ? 'Needs attention' : 'Healthy';
            health.classList.toggle('attention', failures > 0);
            health.classList.toggle('good', failures === 0);
        }

        renderLines('worker-stdout', payload.stdout, 'No worker output has been recorded yet.');
        renderLines('worker-stderr', payload.stderr, 'No worker errors recorded.');

        const stdoutMeta = payload.stdout_meta || {};
        const stderrMeta = payload.stderr_meta || {};
        setText(
            'worker-stdout-status',
            stdoutMeta.is_current_session ? 'Current worker session' : 'No current-session output yet'
        );
        setText(
            'worker-stderr-status',
            Array.isArray(payload.stderr) && payload.stderr.length
                ? (stderrMeta.is_current_session ? 'Current-session errors detected' : 'Historical errors from an older run')
                : 'No errors in the current log'
        );

        if (refreshedNode) {
            const now = new Date();
            refreshedNode.textContent = 'Updated ' + now.toLocaleTimeString();
        }
    };

    const refresh = async () => {
        if (refreshButton) {
            refreshButton.disabled = true;
            refreshButton.textContent = 'Refreshing...';
        }
        try {
            const response = await fetch(liveUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });
            if (response.ok) {
                apply(await response.json());
            }
        } catch (error) {
            // Keep the last successful snapshot visible.
        } finally {
            if (refreshButton) {
                refreshButton.disabled = false;
                refreshButton.textContent = 'Refresh now';
            }
        }
    };

    if (refreshButton) refreshButton.addEventListener('click', refresh);
    window.setInterval(refresh, 5000);
    window.setTimeout(refresh, 1200);
});
</script>
@endpush
