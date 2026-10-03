@extends('layouts.app')

@section('title', 'Leads')
@section('heading', 'Leads')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leads.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-primary" href="{{ route('leads.create') }}">Add lead</a>
@endsection

@section('content')
    <section class="leads-hero">
        <div>
            <p class="eyebrow">Lead workspace</p>
            <h2>Research pipeline</h2>
            <p>Review discovered businesses, qualification state, campaign source, and the signals that will drive future outreach.</p>
        </div>

        <div class="lead-mini-stats">
            <div><span>Total</span><strong>{{ number_format($stats['total']) }}</strong></div>
            <div><span>Qualified</span><strong>{{ number_format($stats['qualified']) }}</strong></div>
            <div><span>Review</span><strong>{{ number_format($stats['review']) }}</strong></div>
            <div><span>Emails sent</span><strong>{{ number_format($stats['emails_sent']) }}</strong></div>
            <div><span>Interested</span><strong>{{ number_format($stats['interested']) }}</strong></div>
        </div>
    </section>

    <form class="lead-filters" method="GET" action="{{ route('leads.index') }}">
        <label>
            <span>Search</span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Business, domain, city...">
        </label>

        <label>
            <span>Campaign</span>
            <select name="campaign">
                <option value="">All campaigns</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->uuid }}" @selected(request('campaign') === $campaign->uuid)>
                        {{ $campaign->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label>
            <span>Status</span>
            <select name="status">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ ucwords(str_replace('_', ' ', $status->value)) }}
                    </option>
                @endforeach
            </select>
        </label>

        <div class="lead-filter-actions">
            <button class="button button-secondary" type="submit">Filter</button>
            <a class="button button-secondary" href="{{ route('leads.index') }}">Reset</a>
        </div>
    </form>

    <section class="lead-table-card">
        <div class="lead-table-head">
            <strong>Lead database</strong>
            <span>{{ number_format($leads->total()) }} matching leads</span>
        </div>

        @forelse ($leads as $lead)
            <a class="lead-row" href="{{ route('leads.show', $lead) }}">
                <div class="lead-business">
                    <span class="lead-avatar">{{ strtoupper(substr($lead->business_name, 0, 1)) }}</span>
                    <div>
                        <strong>{{ $lead->business_name }}</strong>
                        <small>{{ $lead->domain ?: 'No website domain' }}</small>
                    </div>
                </div>

                <div>
                    <span class="lead-label">Campaign</span>
                    <strong>{{ $lead->campaign->name }}</strong>
                </div>

                <div>
                    <span class="lead-label">Location</span>
                    <strong>{{ implode(', ', array_filter([$lead->city, $lead->country])) ?: 'Not set' }}</strong>
                </div>

                <div>
                    <span class="status-tag {{ $lead->status->value }}">
                        {{ str_replace('_', ' ', $lead->status->value) }}
                    </span>
                    <small class="lead-score">
                        {{ $lead->qualification_score !== null ? 'Score '.$lead->qualification_score : 'Not scored' }}
                    </small>
                </div>
            </a>
        @empty
            <div class="lead-empty">
                <strong>No leads found</strong>
                <p>Add a manual lead now, or wait for the discovery API/worker in the next step.</p>
                <a class="button button-primary" href="{{ route('leads.create') }}">Add first lead</a>
            </div>
        @endforelse

        @if ($leads->hasPages())
            <div class="lead-pagination">
                @if ($leads->onFirstPage())
                    <span class="button button-secondary disabled-pagination">Previous</span>
                @else
                    <a class="button button-secondary" href="{{ $leads->previousPageUrl() }}">Previous</a>
                @endif

                <span>Page {{ $leads->currentPage() }} of {{ $leads->lastPage() }}</span>

                @if ($leads->hasMorePages())
                    <a class="button button-secondary" href="{{ $leads->nextPageUrl() }}">Next</a>
                @else
                    <span class="button button-secondary disabled-pagination">Next</span>
                @endif
            </div>
        @endif
    </section>
@endsection
