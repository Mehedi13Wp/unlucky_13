@extends('layouts.app')

@section('title', 'Campaigns')
@section('heading', 'Campaigns')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/campaigns.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-primary" href="{{ route('campaigns.create') }}">
        <span class="campaign-plus">+</span>
        <span>New campaign</span>
    </a>
@endsection

@section('content')
    @php
        $activeCount = $campaigns->filter(fn ($campaign) => $campaign->status->value === 'active')->count();
        $draftCount = $campaigns->filter(fn ($campaign) => $campaign->status->value === 'draft')->count();
        $totalLeads = $campaigns->sum('leads_count');
    @endphp

    <section class="campaign-hero">
        <div>
            <p class="eyebrow">Targeting control</p>
            <h2>Campaign workspace</h2>
            <p>Build focused outreach campaigns with clear targeting, qualification rules, and human review controls.</p>
        </div>

        <div class="campaign-hero-actions">
            <div class="mini-stat">
                <span>Total</span>
                <strong>{{ number_format($campaigns->count()) }}</strong>
            </div>
            <div class="mini-stat">
                <span>Active</span>
                <strong>{{ number_format($activeCount) }}</strong>
            </div>
            <div class="mini-stat">
                <span>Leads</span>
                <strong>{{ number_format($totalLeads) }}</strong>
            </div>
        </div>
    </section>

    <section class="campaign-toolbar">
        <div>
            <strong>All campaigns</strong>
            <span>{{ number_format($draftCount) }} draft · {{ number_format($activeCount) }} active</span>
        </div>
        <a class="button button-secondary button-small" href="{{ route('campaigns.create') }}">Create campaign</a>
    </section>

    <section class="campaign-cards">
        @forelse ($campaigns as $campaign)
            <a class="campaign-card" href="{{ route('campaigns.show', $campaign) }}">
                <div class="campaign-card-top">
                    <div class="campaign-card-title">
                        <span class="campaign-initial">{{ strtoupper(substr($campaign->name, 0, 1)) }}</span>
                        <div>
                            <strong>{{ $campaign->name }}</strong>
                            <small>{{ $campaign->target_industry ?: 'General business targeting' }}</small>
                        </div>
                    </div>

                    <span class="status-tag {{ $campaign->status->value }}">
                        {{ $campaign->status->value }}
                    </span>
                </div>

                <div class="campaign-card-body">
                    <p>{{ str($campaign->service_offer)->limit(115) }}</p>

                    <div class="campaign-meta-grid">
                        <div>
                            <span>Leads</span>
                            <strong>{{ number_format($campaign->leads_count) }}</strong>
                        </div>
                        <div>
                            <span>Daily limit</span>
                            <strong>{{ $campaign->daily_outreach_limit }}</strong>
                        </div>
                        <div>
                            <span>Review</span>
                            <strong>{{ $campaign->review_required ? 'Required' : 'Optional' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="campaign-card-foot">
                    <span>
                        {{ implode(', ', array_slice($campaign->target_countries ?? [], 0, 2)) ?: 'No country limit' }}
                    </span>
                    <strong>Open campaign →</strong>
                </div>
            </a>
        @empty
            <div class="campaign-empty">
                <div class="campaign-empty-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M5 12h14M5 17h8"/><circle cx="18" cy="17" r="2"/></svg>
                </div>
                <p class="eyebrow">No campaigns yet</p>
                <h3>Create your first focused campaign</h3>
                <p>Define who the worker should research, what signal counts as a qualified lead, and how much outreach is allowed each day.</p>
                <a class="button button-primary" href="{{ route('campaigns.create') }}">Create first campaign</a>
            </div>
        @endforelse
    </section>
@endsection
