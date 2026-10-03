@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Command Center')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-primary" href="{{ route('campaigns.create') }}">
        <span class="button-plus">+</span>
        <span>New campaign</span>
    </a>
@endsection

@section('content')
    <section class="dashboard-hero">
        <div class="dashboard-hero-copy">
            <div class="hero-kicker">
                <span class="hero-kicker-dot"></span>
                <span>Private lead operations</span>
            </div>

            @if ($stats['campaigns'] === 0)
                <h2>Build your first focused outreach campaign.</h2>
                <p>Define the offer, target market, qualification rules, and review limits before the discovery worker starts finding businesses.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="{{ route('campaigns.create') }}">Create first campaign</a>
                    <a class="text-link" href="{{ route('campaigns.index') }}">Open campaign workspace <span>→</span></a>
                </div>
            @else
                <h2>Your lead pipeline, without the noise.</h2>
                <p>Track campaign health, qualification progress, and conversations that need your attention from one private workspace.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="{{ route('campaigns.create') }}">New campaign</a>
                    <a class="text-link" href="{{ route('campaigns.index') }}">Manage campaigns <span>→</span></a>
                </div>
            @endif
        </div>

        @php
            $runtimeState = $workerStatus['state'] ?? 'stopped';
            $runtimeTitle = match ($runtimeState) {
                'starting' => 'Starting background worker',
                'running' => 'Processing active campaigns',
                'stopping' => 'Finishing current operation',
                default => 'Stopped by you',
            };
            $workerStartDisabled = in_array($runtimeState, ['starting', 'running', 'stopping'], true)
                || ! $workerStatus['supported'];
            $workerStopDisabled = ! in_array($runtimeState, ['starting', 'running'], true)
                || ! $workerStatus['supported'];
        @endphp

        <div class="hero-control-card">
            <div class="control-card-head">
                <div>
                    <span class="control-label">Automation worker</span>
                    <strong id="worker-runtime-title">{{ $runtimeTitle }}</strong>
                </div>
                <span
                    id="worker-runtime-state"
                    class="control-state {{ in_array($runtimeState, ['starting', 'running'], true) ? 'running' : 'stopped' }}"
                >
                    {{ strtoupper($runtimeState) }}
                </span>
            </div>

            <div class="worker-actions">
                <form method="POST" action="{{ route('worker.start') }}">
                    @csrf
                    <button
                        id="worker-start-button"
                        class="button button-worker-start"
                        type="submit"
                        @if ($workerStartDisabled) disabled @endif
                    >
                        Start Worker
                    </button>
                </form>

                <form method="POST" action="{{ route('worker.stop') }}">
                    @csrf
                    <button
                        id="worker-stop-button"
                        class="button button-worker-stop"
                        type="submit"
                        @if ($workerStopDisabled) disabled @endif
                    >
                        Stop Worker
                    </button>
                </form>
            </div>

            <p class="worker-help">
                Laravel stays under your control. Start the server, open this dashboard, then start or stop automation here.
            </p>

            <div class="control-divider"></div>

            <div class="control-row">
                <span>Active campaigns</span>
                <strong id="worker-active-campaigns">{{ number_format($stats['active_campaigns']) }}</strong>
            </div>
            <div class="control-row">
                <span>Review before send</span>
                <strong>ON</strong>
            </div>
            <div class="control-row">
                <span>Auto-send</span>
                <strong>Locked</strong>
            </div>
        </div>
    </section>

    <section
        class="worker-live-strip"
        aria-label="Worker activity"
        data-live-status-url="{{ route('dashboard.live-status') }}"
        id="worker-live-strip"
    >
        <div class="worker-live-item">
            <span>Heartbeat</span>
            <strong id="worker-heartbeat">{{ $workerActivity['heartbeat_label'] }}</strong>
            <small>Auto-check every 10 seconds</small>
        </div>
        <div class="worker-live-item">
            <span>Last cycle</span>
            <strong id="worker-last-cycle">{{ $workerActivity['last_cycle_label'] }}</strong>
            <small>Discovery + analysis + contacts + drafts</small>
        </div>
        <div class="worker-live-item">
            <span>Last discovery</span>
            <strong id="worker-last-discovery">{{ $workerActivity['last_discovery_label'] }}</strong>
            <small>Per-campaign discovery interval applies</small>
        </div>
        <div class="worker-live-item">
            <span>New leads today</span>
            <strong id="worker-new-leads-today">{{ number_format($workerActivity['new_leads_today']) }}</strong>
            <small>Fresh businesses saved today</small>
        </div>
        <div class="worker-live-item">
            <span>Analyzed today</span>
            <strong id="worker-analyzed-today">{{ number_format($workerActivity['analyzed_today']) }}</strong>
            <small>Website inspections completed</small>
        </div>
        <div class="worker-live-item">
            <span>Drafts today</span>
            <strong id="worker-drafts-today">{{ number_format($workerActivity['drafts_today']) }}</strong>
            <small>Waiting for human review</small>
        </div>
    </section>

    <section class="metric-grid" aria-label="Workspace metrics">
        <article class="metric-card">
            <div class="metric-top">
                <span class="metric-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M5 12h14M5 17h8"/><circle cx="18" cy="17" r="2"/></svg>
                </span>
                <span class="metric-label">Campaigns</span>
            </div>
            <strong class="metric-value">{{ number_format($stats['campaigns']) }}</strong>
            <div class="metric-foot">
                <span>{{ number_format($stats['active_campaigns']) }} active</span>
                <span>{{ number_format($stats['draft_campaigns']) }} draft</span>
            </div>
        </article>

        <article class="metric-card">
            <div class="metric-top">
                <span class="metric-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M11 8v6M8 11h6"/></svg>
                </span>
                <span class="metric-label">Businesses found</span>
            </div>
            <strong class="metric-value" id="metric-businesses-found">{{ number_format($stats['leads']) }}</strong>
            <div class="metric-foot">
                <span>Across your campaigns</span>
            </div>
        </article>

        <article class="metric-card">
            <div class="metric-top">
                <span class="metric-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="9"/></svg>
                </span>
                <span class="metric-label">Qualified</span>
            </div>
            <strong class="metric-value" id="metric-qualified">{{ number_format($stats['qualified_leads']) }}</strong>
            <div class="metric-foot">
                <span>{{ $rates['qualification'] }}% qualification rate</span>
            </div>
        </article>

        <article class="metric-card">
            <div class="metric-top">
                <span class="metric-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </span>
                <span class="metric-label">Review queue</span>
            </div>
            <strong class="metric-value" id="metric-review-queue">{{ number_format($stats['review_queue']) }}</strong>
            <div class="metric-foot">
                <span>Waiting for approval</span>
            </div>
        </article>

        <article class="metric-card metric-card-attention">
            <div class="metric-top">
                <span class="metric-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8.4 8.4 0 0 1-9 8.5 9.4 9.4 0 0 1-4-.9L3 20l.9-4A9 9 0 1 1 20 11.5Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/></svg>
                </span>
                <span class="metric-label">Interested</span>
            </div>
            <strong class="metric-value">{{ number_format($stats['interested_leads']) }}</strong>
            <div class="metric-foot">
                <span>{{ $rates['interest'] }}% of contacted leads</span>
            </div>
        </article>
    </section>

    <section class="dashboard-main-grid">
        <article class="workspace-card pipeline-card">
            <header class="workspace-card-head">
                <div>
                    <p class="eyebrow">Lead pipeline</p>
                    <h3>Qualification flow</h3>
                </div>
                <span class="head-caption">{{ number_format($stats['leads']) }} total leads</span>
            </header>

            @php
                $pipelineMax = max(1, $stats['leads']);
                $pipelineRows = [
                    ['label' => 'Researching', 'value' => $pipeline['researching']],
                    ['label' => 'Qualified', 'value' => $pipeline['qualified']],
                    ['label' => 'Review queue', 'value' => $pipeline['review']],
                    ['label' => 'Contacted', 'value' => $pipeline['contacted']],
                    ['label' => 'Interested', 'value' => $pipeline['interested']],
                ];
            @endphp

            <div class="pipeline-list">
                @foreach ($pipelineRows as $row)
                    @php($width = $stats['leads'] > 0 ? max(4, (int) round(($row['value'] / $pipelineMax) * 100)) : 0)
                    <div class="pipeline-row">
                        <div class="pipeline-row-head">
                            <span>{{ $row['label'] }}</span>
                            <strong>{{ number_format($row['value']) }}</strong>
                        </div>
                        <div class="pipeline-track" aria-hidden="true">
                            <span style="width: {{ $width }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($stats['leads'] === 0)
                <div class="pipeline-empty">
                    <span class="empty-ring"></span>
                    <div>
                        <strong>Pipeline is ready</strong>
                        <small>Lead activity will appear here after discovery begins.</small>
                    </div>
                </div>
            @endif
        </article>

        <article class="workspace-card readiness-card">
            <header class="workspace-card-head">
                <div>
                    <p class="eyebrow">System readiness</p>
                    <h3>Build progress</h3>
                </div>
                <span class="readiness-score">2 / 5</span>
            </header>

            <div class="readiness-list">
                <div class="readiness-item complete">
                    <span class="readiness-check">✓</span>
                    <div>
                        <strong>Campaign control</strong>
                        <small>Targeting, qualification, limits, status</small>
                    </div>
                </div>
                <div class="readiness-item complete">
                    <span class="readiness-check">✓</span>
                    <div>
                        <strong>Private workspace</strong>
                        <small>Authentication and responsive dashboard</small>
                    </div>
                </div>
                <div class="readiness-item">
                    <span class="readiness-index">03</span>
                    <div>
                        <strong>Lead discovery</strong>
                        <small>Next product milestone</small>
                    </div>
                </div>
                <div class="readiness-item">
                    <span class="readiness-index">04</span>
                    <div>
                        <strong>Outreach review</strong>
                        <small>Draft approval and suppression controls</small>
                    </div>
                </div>
                <div class="readiness-item">
                    <span class="readiness-index">05</span>
                    <div>
                        <strong>Delivery & replies</strong>
                        <small>Email, bounce, unsubscribe, reply handling</small>
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="activity-grid">
        <article class="workspace-card">
            <header class="workspace-card-head">
                <div>
                    <p class="eyebrow">Campaigns</p>
                    <h3>Recent campaigns</h3>
                </div>
                <a class="head-link" href="{{ route('campaigns.index') }}">View all <span>→</span></a>
            </header>

            <div class="activity-list">
                @forelse ($recentCampaigns as $campaign)
                    <a class="activity-row" href="{{ route('campaigns.show', $campaign) }}">
                        <span class="activity-avatar">{{ strtoupper(substr($campaign->name, 0, 1)) }}</span>
                        <span class="activity-main">
                            <strong>{{ $campaign->name }}</strong>
                            <small>{{ $campaign->target_industry ?: 'General targeting' }} · {{ $campaign->leads_count }} leads</small>
                        </span>
                        <span class="status-tag {{ $campaign->status->value }}">{{ $campaign->status->value }}</span>
                    </a>
                @empty
                    <div class="rich-empty">
                        <span class="rich-empty-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M5 12h14M5 17h8"/><circle cx="18" cy="17" r="2"/></svg>
                        </span>
                        <strong>No campaigns yet</strong>
                        <p>Create a focused campaign to tell the worker exactly what kind of business opportunity to look for.</p>
                        <a class="button button-secondary button-small" href="{{ route('campaigns.create') }}">Create campaign</a>
                    </div>
                @endforelse
            </div>
        </article>

        <article class="workspace-card">
            <header class="workspace-card-head">
                <div>
                    <p class="eyebrow">Lead activity</p>
                    <h3>Recently discovered</h3>
                </div>
                <span class="head-caption">Latest 6</span>
            </header>

            <div class="activity-list">
                @forelse ($recentLeads as $lead)
                    <div class="activity-row">
                        <span class="activity-avatar activity-avatar-lead">{{ strtoupper(substr($lead->business_name, 0, 1)) }}</span>
                        <span class="activity-main">
                            <strong>{{ $lead->business_name }}</strong>
                            <small>{{ $lead->campaign?->name ?: 'Unknown campaign' }}</small>
                        </span>
                        <span class="activity-side">
                            <span class="tag">{{ str_replace('_', ' ', $lead->status->value) }}</span>
                            @if ($lead->qualification_score !== null)
                                <small>Score {{ $lead->qualification_score }}</small>
                            @endif
                        </span>
                    </div>
                @empty
                    <div class="rich-empty">
                        <span class="rich-empty-icon">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M11 8v6M8 11h6"/></svg>
                        </span>
                        <strong>No leads discovered yet</strong>
                        <p>The discovery engine will populate this stream after the next product milestone is connected.</p>
                        <span class="empty-status">Discovery not connected yet</span>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('worker-live-strip');

    if (!root) {
        return;
    }

    const url = root.dataset.liveStatusUrl;
    const stateNode = document.getElementById('worker-runtime-state');
    const titleNode = document.getElementById('worker-runtime-title');
    const startButton = document.getElementById('worker-start-button');
    const stopButton = document.getElementById('worker-stop-button');

    const stateTitles = {
        starting: 'Starting background worker',
        running: 'Processing active campaigns',
        stopping: 'Finishing current operation',
        stopped: 'Stopped by you',
        unsupported: 'Worker control unavailable',
    };

    const setText = (id, value) => {
        const element = document.getElementById(id);

        if (element && value !== undefined && value !== null) {
            element.textContent = value;
        }
    };

    const applySnapshot = (payload) => {
        const worker = payload.worker || {};
        const activity = payload.activity || {};
        const state = worker.state || 'stopped';

        if (stateNode) {
            stateNode.textContent = state.toUpperCase();
            stateNode.classList.toggle('running', ['starting', 'running'].includes(state));
            stateNode.classList.toggle('stopped', !['starting', 'running'].includes(state));
        }

        if (titleNode) {
            titleNode.textContent = stateTitles[state] || 'Worker status updated';
        }

        if (startButton) {
            startButton.disabled = ['starting', 'running', 'stopping'].includes(state) || worker.supported === false;
        }

        if (stopButton) {
            stopButton.disabled = !['starting', 'running'].includes(state) || worker.supported === false;
        }

        setText('worker-heartbeat', activity.heartbeat_label || 'Waiting for worker');
        setText('worker-last-cycle', activity.last_cycle_label || 'No cycle yet');
        setText('worker-last-discovery', activity.last_discovery_label || 'No discovery yet');
        setText('worker-new-leads-today', activity.new_leads_today ?? 0);
        setText('worker-analyzed-today', activity.analyzed_today ?? 0);
        setText('worker-drafts-today', activity.drafts_today ?? 0);
        setText('worker-active-campaigns', activity.active_campaigns ?? 0);
        setText('metric-businesses-found', activity.businesses_found ?? 0);
        setText('metric-qualified', activity.qualified ?? 0);
        setText('metric-review-queue', activity.review_queue ?? 0);
    };

    const refresh = async () => {
        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            applySnapshot(await response.json());
        } catch (error) {
            // Keep the last known dashboard state if a poll fails.
        }
    };

    window.setInterval(refresh, 10000);
    window.setTimeout(refresh, 1500);
});
</script>
@endpush

@endsection
