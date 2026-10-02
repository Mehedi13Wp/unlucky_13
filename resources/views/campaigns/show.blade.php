@extends('layouts.app')

@section('title', $campaign->name)
@section('heading', 'Campaign Detail')

@section('topbar-actions')
    <a class="button button-secondary" href="{{ route('campaigns.edit', $campaign) }}">Edit campaign</a>
@endsection

@section('content')
    <div class="page-intro">
        <div>
            <p class="eyebrow">{{ $campaign->target_industry ?: 'General targeting' }}</p>
            <h2>{{ $campaign->name }}</h2>
            <p>{{ implode(', ', array_filter(array_merge($campaign->target_cities ?? [], $campaign->target_countries ?? []))) ?: 'No geography restriction configured' }}</p>
        </div>
        <span class="status-tag {{ $campaign->status->value }}">{{ $campaign->status->value }}</span>
    </div>

    <div class="stats-grid">
        <article class="stat-card">
            <span>Leads</span>
            <strong>{{ number_format($campaign->leads_count) }}</strong>
            <small>Total discovered</small>
        </article>
        <article class="stat-card">
            <span>Qualified</span>
            <strong>{{ number_format($campaign->qualified_leads_count) }}</strong>
            <small>Passed current criteria</small>
        </article>
        <article class="stat-card accent">
            <span>Interested</span>
            <strong>{{ number_format($campaign->interested_leads_count) }}</strong>
            <small>Needs human follow-up</small>
        </article>
        <article class="stat-card">
            <span>Daily limit</span>
            <strong>{{ $campaign->daily_outreach_limit }}</strong>
            <small>Maximum outreach/day</small>
        </article>
        <article class="stat-card">
            <span>Review</span>
            <strong>{{ $campaign->review_required ? 'ON' : 'OFF' }}</strong>
            <small>Auto-send remains locked</small>
        </article>
    </div>

    <div class="detail-grid" style="margin-top:18px">
        <div class="detail-stack">
            <section class="detail-card">
                <p class="eyebrow">Service offer</p>
                <h3>What this campaign sells</h3>
                <p>{{ $campaign->service_offer }}</p>
            </section>

            <section class="detail-card">
                <p class="eyebrow">Target rules</p>
                <h3>Qualification policy</h3>
                <div class="detail-list">
                    <div class="detail-item">
                        <span>Minimum score</span>
                        <strong>{{ $campaign->qualification_rules['minimum_score'] ?? 60 }}/100</strong>
                    </div>
                    <div class="detail-item">
                        <span>Website required</span>
                        <strong>{{ ($campaign->qualification_rules['requires_website'] ?? true) ? 'Yes' : 'No' }}</strong>
                    </div>
                    <div class="detail-item">
                        <span>Public contact required</span>
                        <strong>{{ ($campaign->qualification_rules['requires_public_contact'] ?? true) ? 'Yes' : 'No' }}</strong>
                    </div>
                </div>
            </section>
        </div>

        <div class="detail-stack">
            <section class="detail-card">
                <p class="eyebrow">Campaign control</p>
                <h3>Status</h3>

                <div class="inline-actions">
                    @if ($campaign->status->value !== 'active')
                        <form method="POST" action="{{ route('campaigns.status', $campaign) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <button class="button button-primary button-small" type="submit">Activate</button>
                        </form>
                    @endif

                    @if ($campaign->status->value === 'active')
                        <form method="POST" action="{{ route('campaigns.status', $campaign) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="paused">
                            <button class="button button-secondary button-small" type="submit">Pause</button>
                        </form>
                    @endif

                    @if (! in_array($campaign->status->value, ['completed', 'archived'], true))
                        <form method="POST" action="{{ route('campaigns.status', $campaign) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button class="button button-secondary button-small" type="submit">Complete</button>
                        </form>
                    @endif
                </div>
            </section>

            @if ($campaign->status->value !== 'archived')
                <section class="detail-card">
                    <p class="eyebrow">History protection</p>
                    <h3>Archive campaign</h3>
                    <p>Archiving stops normal campaign use while preserving its leads and history.</p>
                    <form method="POST" action="{{ route('campaigns.archive', $campaign) }}">
                        @csrf
                        @method('PATCH')
                        <button class="button button-danger button-small" type="submit">Archive</button>
                    </form>
                </section>
            @endif
        </div>
    </div>
@endsection
