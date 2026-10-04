@extends('layouts.app')

@section('title', 'Reports')
@section('heading', 'Reports')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-secondary" href="{{ route('worker.index') }}">Worker activity</a>
@endsection

@section('content')
    <section class="reports-hero">
        <div>
            <p class="eyebrow">Performance overview</p>
            <h2>Know what your campaigns are actually producing.</h2>
            <p>Track lead volume, qualification, review load, email delivery, and campaign-level performance from the data already stored in Unlucky 13.</p>
        </div>

        <div class="reports-health">
            <span>Email delivery success</span>
            <strong>{{ $summary['send_rate'] }}%</strong>
            <small>{{ number_format($summary['emails_sent']) }} sent · {{ number_format($summary['emails_failed']) }} failed</small>
        </div>
    </section>

    <section class="reports-summary-grid">
        <article><span>Total leads</span><strong>{{ number_format($summary['leads']) }}</strong><small>All campaigns</small></article>
        <article><span>Ever qualified</span><strong>{{ number_format($summary['qualified']) }}</strong><small>{{ $summary['qualification_rate'] }}% qualification rate</small></article>
        <article><span>Review queue</span><strong>{{ number_format($summary['review']) }}</strong><small>Waiting for your decision</small></article>
        <article><span>Emails sent</span><strong>{{ number_format($summary['emails_sent']) }}</strong><small>Successful deliveries</small></article>
        <article><span>Interested</span><strong>{{ number_format($summary['interested']) }}</strong><small>Current interested leads</small></article>
    </section>

    <section class="reports-grid">
        <article class="reports-panel">
            <header>
                <div>
                    <p class="eyebrow">Today</p>
                    <h3>Current activity</h3>
                </div>
            </header>
            <div class="reports-activity-grid">
                <div><span>Leads added</span><strong>{{ number_format($activity['leads_today']) }}</strong></div>
                <div><span>Analyzed</span><strong>{{ number_format($activity['analyzed_today']) }}</strong></div>
                <div><span>Drafts generated</span><strong>{{ number_format($activity['drafts_today']) }}</strong></div>
                <div><span>Emails sent</span><strong>{{ number_format($activity['sent_today']) }}</strong></div>
            </div>
        </article>

        <article class="reports-panel">
            <header>
                <div>
                    <p class="eyebrow">Last 7 days</p>
                    <h3>Weekly activity</h3>
                </div>
            </header>
            <div class="reports-activity-grid">
                <div><span>Leads added</span><strong>{{ number_format($activity['leads_7d']) }}</strong></div>
                <div><span>Qualified</span><strong>{{ number_format($activity['qualified_7d']) }}</strong></div>
                <div><span>Drafts generated</span><strong>{{ number_format($activity['drafts_7d']) }}</strong></div>
                <div><span>Emails sent</span><strong>{{ number_format($activity['sent_7d']) }}</strong></div>
            </div>
        </article>
    </section>

    <section class="reports-panel reports-campaign-panel">
        <header>
            <div>
                <p class="eyebrow">Campaign comparison</p>
                <h3>Performance by campaign</h3>
            </div>
            <span>{{ number_format($campaignRows->count()) }} campaigns</span>
        </header>

        @if ($campaignRows->isNotEmpty())
            <div class="reports-table-wrap">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Leads</th>
                            <th>Qualified</th>
                            <th>Rate</th>
                            <th>Review</th>
                            <th>Emails sent</th>
                            <th>Interested</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($campaignRows as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('campaigns.show', $row['campaign']) }}">{{ $row['campaign']->name }}</a>
                                    <small>{{ ucfirst($row['campaign']->status->value) }}</small>
                                </td>
                                <td>{{ number_format($row['leads']) }}</td>
                                <td>{{ number_format($row['qualified']) }}</td>
                                <td>{{ $row['qualification_rate'] }}%</td>
                                <td>{{ number_format($row['review']) }}</td>
                                <td>{{ number_format($row['emails_sent']) }}</td>
                                <td>{{ number_format($row['interested']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="reports-empty">
                <strong>No campaign data yet</strong>
                <p>Create and run a campaign to populate performance reports.</p>
            </div>
        @endif
    </section>
@endsection
