@extends('layouts.app')

@section('title', 'Dashboard')
@section('heading', 'Command Center')

@section('topbar-actions')
    <a class="button button-primary" href="{{ route('campaigns.create') }}">New campaign</a>
@endsection

@section('content')
    <div class="hero-card">
        <div>
            <p class="eyebrow">Unlucky 13 worker</p>
            <h2>Research carefully. Qualify first. Outreach second.</h2>
            <p>Your review-first lead engine is ready for targeted campaign management and worker integration.</p>
        </div>
        <div class="hero-badge">
            <strong>Review Before Send</strong>
            <span>Default safety mode enabled</span>
        </div>
    </div>

    <div class="stats-grid">
        <article class="stat-card">
            <span>Total campaigns</span>
            <strong>{{ number_format($stats['campaigns']) }}</strong>
            <small>{{ number_format($stats['active_campaigns']) }} active</small>
        </article>
        <article class="stat-card">
            <span>Businesses found</span>
            <strong>{{ number_format($stats['leads']) }}</strong>
            <small>All discovered leads</small>
        </article>
        <article class="stat-card">
            <span>Qualified</span>
            <strong>{{ number_format($stats['qualified_leads']) }}</strong>
            <small>Passed qualification</small>
        </article>
        <article class="stat-card">
            <span>Review queue</span>
            <strong>{{ number_format($stats['review_queue']) }}</strong>
            <small>Waiting for approval</small>
        </article>
        <article class="stat-card accent">
            <span>Interested</span>
            <strong>{{ number_format($stats['interested_leads']) }}</strong>
            <small>Human follow-up required</small>
        </article>
    </div>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Campaigns</p>
                    <h3>Recent campaigns</h3>
                </div>
                <a class="muted" href="{{ route('campaigns.index') }}">View all</a>
            </div>

            @forelse ($recentCampaigns as $campaign)
                <a class="list-row" href="{{ route('campaigns.show', $campaign) }}">
                    <div>
                        <strong>{{ $campaign->name }}</strong>
                        <small>{{ $campaign->target_industry ?: 'General targeting' }}</small>
                    </div>
                    <div class="row-meta">
                        <span class="tag">{{ $campaign->status->value }}</span>
                        <small>{{ $campaign->leads_count }} leads</small>
                    </div>
                </a>
            @empty
                <div class="empty-state">
                    <strong>No campaigns yet</strong>
                    <span>Create a focused campaign to define what the worker should research.</span>
                </div>
            @endforelse
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <p class="eyebrow">Lead activity</p>
                    <h3>Recently discovered</h3>
                </div>
                <span class="muted">Latest 6</span>
            </div>

            @forelse ($recentLeads as $lead)
                <div class="list-row">
                    <div>
                        <strong>{{ $lead->business_name }}</strong>
                        <small>{{ $lead->campaign?->name ?: 'Unknown campaign' }}</small>
                    </div>
                    <div class="row-meta">
                        <span class="tag">{{ $lead->status->value }}</span>
                        @if ($lead->qualification_score !== null)
                            <small>Score {{ $lead->qualification_score }}</small>
                        @endif
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <strong>No leads discovered yet</strong>
                    <span>The discovery worker will populate this feed.</span>
                </div>
            @endforelse
        </section>
    </div>
@endsection
