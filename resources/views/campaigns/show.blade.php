@extends('layouts.app')

@section('title', $campaign->name)
@section('heading', 'Campaign Detail')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/campaigns.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-secondary" href="{{ route('campaigns.index') }}">All campaigns</a>
    <a class="button button-primary" href="{{ route('campaigns.edit', $campaign) }}">Edit campaign</a>
@endsection

@section('content')
    <section class="campaign-detail-hero">
        <div class="campaign-detail-copy">
            <div class="detail-kicker">
                <span class="status-tag {{ $campaign->status->value }}">{{ $campaign->status->value }}</span>
                <span>{{ $campaign->target_industry ?: 'General targeting' }}</span>
            </div>

            <h2>{{ $campaign->name }}</h2>
            <p>{{ implode(', ', array_filter(array_merge($campaign->target_cities ?? [], $campaign->target_countries ?? []))) ?: 'No geography restriction configured' }}</p>
        </div>

        <div class="detail-policy-card">
            <span>Review policy</span>
            <strong>{{ $campaign->review_required ? 'Human approval required' : 'Manual review optional' }}</strong>
            <small>Auto-send remains locked in the current build.</small>
        </div>
    </section>

    <section class="campaign-metric-grid">
        <article>
            <span>Leads</span>
            <strong>{{ number_format($campaign->leads_count) }}</strong>
            <small>Total discovered</small>
        </article>
        <article>
            <span>Qualified</span>
            <strong>{{ number_format($campaign->qualified_leads_count) }}</strong>
            <small>Passed criteria</small>
        </article>
        <article class="attention">
            <span>Interested</span>
            <strong>{{ number_format($campaign->interested_leads_count) }}</strong>
            <small>Needs follow-up</small>
        </article>
        <article>
            <span>Daily limit</span>
            <strong>{{ $campaign->daily_outreach_limit }}</strong>
            <small>Maximum / day</small>
        </article>
    </section>

    <section class="campaign-detail-grid">
        <div class="detail-column">
            <article class="campaign-panel">
                <header>
                    <p class="eyebrow">Service offer</p>
                    <h3>What this campaign sells</h3>
                </header>
                <p class="offer-copy">{{ $campaign->service_offer }}</p>
            </article>

            <article class="campaign-panel">
                <header>
                    <p class="eyebrow">Qualification</p>
                    <h3>Lead policy</h3>
                </header>

                <div class="policy-list">
                    <div>
                        <span>Minimum score</span>
                        <strong>{{ $campaign->qualification_rules['minimum_score'] ?? 60 }}/100</strong>
                    </div>
                    <div>
                        <span>Website required</span>
                        <strong>{{ ($campaign->qualification_rules['requires_website'] ?? true) ? 'Yes' : 'No' }}</strong>
                    </div>
                    <div>
                        <span>Public contact required</span>
                        <strong>{{ ($campaign->qualification_rules['requires_public_contact'] ?? true) ? 'Yes' : 'No' }}</strong>
                    </div>
                    <div>
                        <span>Review before send</span>
                        <strong>{{ $campaign->review_required ? 'Yes' : 'No' }}</strong>
                    </div>
                </div>
            </article>

            <article class="campaign-panel">
                <header>
                    <p class="eyebrow">Geography</p>
                    <h3>Search area</h3>
                </header>

                <div class="chip-group">
                    @forelse ($campaign->target_countries ?? [] as $country)
                        <span class="campaign-chip">{{ $country }}</span>
                    @empty
                        <span class="campaign-chip muted-chip">No country restriction</span>
                    @endforelse
                </div>

                @if (! empty($campaign->target_cities))
                    <div class="chip-group chip-group-secondary">
                        @foreach ($campaign->target_cities as $city)
                            <span class="campaign-chip">{{ $city }}</span>
                        @endforeach
                    </div>
                @endif
            </article>
        </div>

        <aside class="detail-column">
            <article class="campaign-panel control-panel">
                <header>
                    <p class="eyebrow">Campaign control</p>
                    <h3>Status actions</h3>
                </header>

                <div class="control-summary">
                    <span>Current status</span>
                    <strong class="status-tag {{ $campaign->status->value }}">{{ $campaign->status->value }}</strong>
                </div>

                <div class="campaign-action-stack">
                    @if ($campaign->status->value !== 'active')
                        <form method="POST" action="{{ route('campaigns.status', $campaign) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <button class="button button-primary button-full" type="submit">Activate campaign</button>
                        </form>
                    @endif

                    @if ($campaign->status->value === 'active')
                        <form method="POST" action="{{ route('campaigns.status', $campaign) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="paused">
                            <button class="button button-secondary button-full" type="submit">Pause campaign</button>
                        </form>
                    @endif

                    @if (! in_array($campaign->status->value, ['completed', 'archived'], true))
                        <form method="POST" action="{{ route('campaigns.status', $campaign) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button class="button button-secondary button-full" type="submit">Mark completed</button>
                        </form>
                    @endif
                </div>
            </article>

            @if ($campaign->status->value !== 'archived')
                <article class="campaign-panel archive-panel">
                    <header>
                        <p class="eyebrow">History protection</p>
                        <h3>Archive campaign</h3>
                    </header>
                    <p>Archiving stops normal campaign use while preserving its leads and history.</p>
                    <form method="POST" action="{{ route('campaigns.archive', $campaign) }}">
                        @csrf
                        @method('PATCH')
                        <button class="button button-danger button-full" type="submit">Archive campaign</button>
                    </form>
                </article>
            @endif
        </aside>
    </section>
@endsection
