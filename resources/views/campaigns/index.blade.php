@extends('layouts.app')

@section('title', 'Campaigns')
@section('heading', 'Campaigns')

@section('topbar-actions')
    <a class="button button-primary" href="{{ route('campaigns.create') }}">New campaign</a>
@endsection

@section('content')
    <div class="page-intro">
        <div>
            <p class="eyebrow">Targeting control</p>
            <h2>Campaign workspace</h2>
            <p>Define exactly who the worker should research and keep outreach limits under your control.</p>
        </div>
    </div>

    <section class="campaign-list">
        @forelse ($campaigns as $campaign)
            <a class="campaign-row" href="{{ route('campaigns.show', $campaign) }}">
                <div>
                    <strong>{{ $campaign->name }}</strong>
                    <small>{{ $campaign->target_industry ?: 'General business targeting' }}</small>
                </div>
                <div>
                    <span class="status-tag {{ $campaign->status->value }}">{{ $campaign->status->value }}</span>
                    <small>{{ $campaign->review_required ? 'Review required' : 'Manual review optional' }}</small>
                </div>
                <div>
                    <strong>{{ number_format($campaign->leads_count) }}</strong>
                    <small>Leads</small>
                </div>
                <div class="align-right">
                    <strong>{{ $campaign->daily_outreach_limit }}/day</strong>
                    <small>Outreach limit</small>
                </div>
            </a>
        @empty
            <div class="empty-state">
                <strong>No campaigns yet</strong>
                <span>Create your first targeted campaign to define what the worker should look for.</span>
                <div style="margin-top:16px">
                    <a class="button button-primary" href="{{ route('campaigns.create') }}">Create first campaign</a>
                </div>
            </div>
        @endforelse
    </section>
@endsection
