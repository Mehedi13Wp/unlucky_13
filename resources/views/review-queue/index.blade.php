@extends('layouts.app')

@section('title', 'Review Queue')
@section('heading', 'Review Queue')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/review-queue.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-secondary" href="{{ route('leads.index') }}">All leads</a>
@endsection

@section('content')
    <section class="review-hero">
        <div>
            <p class="eyebrow">Human approval gate</p>
            <h2>Review outreach before it leaves your account</h2>
            <p>Approve or reject generated drafts, then send approved email without opening each lead separately.</p>
        </div>

        <div class="review-stats">
            <div>
                <span>Pending review</span>
                <strong>{{ number_format($stats['pending']) }}</strong>
            </div>
            <div>
                <span>Approved</span>
                <strong>{{ number_format($stats['approved']) }}</strong>
            </div>
            <div>
                <span>Email ready</span>
                <strong>{{ number_format($stats['email_ready']) }}</strong>
            </div>
            <div>
                <span>Actionable</span>
                <strong>{{ number_format($stats['total']) }}</strong>
            </div>
        </div>
    </section>

    <form class="review-filters" method="GET" action="{{ route('review-queue.index') }}">
        <label>
            <span>Search</span>
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Business, domain, recipient...">
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
            <span>Channel</span>
            <select name="channel">
                <option value="">All channels</option>
                <option value="email" @selected(request('channel') === 'email')>Email</option>
                <option value="contact_form" @selected(request('channel') === 'contact_form')>Contact form</option>
            </select>
        </label>

        <div class="review-filter-actions">
            <button class="button button-secondary" type="submit">Filter</button>
            <a class="button button-secondary" href="{{ route('review-queue.index') }}">Reset</a>
        </div>
    </form>

    <section class="review-list">
        @forelse ($drafts as $draft)
            @php($campaign = $draft->lead->campaign)
            @php($campaignIsActive = $campaign->status === \App\Enums\CampaignStatus::Active)

            <article class="review-card">
                <header class="review-card-head">
                    <div class="review-business">
                        <span class="review-avatar">{{ strtoupper(substr($draft->lead->business_name, 0, 1)) }}</span>
                        <div>
                            <div class="review-title-line">
                                <h3>{{ $draft->lead->business_name }}</h3>
                                <span class="status-tag {{ $draft->lead->status->value }}">
                                    {{ str_replace('_', ' ', $draft->lead->status->value) }}
                                </span>
                            </div>
                            <p>
                                {{ $draft->lead->domain ?: 'No website domain' }}
                                <span aria-hidden="true">·</span>
                                {{ $campaign->name }}
                            </p>
                        </div>
                    </div>

                    <div class="review-score">
                        <span>Score</span>
                        <strong>{{ $draft->lead->qualification_score ?? '—' }}</strong>
                    </div>
                </header>

                <div class="review-meta-grid">
                    <div>
                        <span>Draft status</span>
                        <strong>{{ ucwords(str_replace('_', ' ', $draft->status)) }}</strong>
                    </div>
                    <div>
                        <span>Channel</span>
                        <strong>{{ $draft->channel === 'contact_form' ? 'Contact form' : 'Email' }}</strong>
                    </div>
                    <div>
                        <span>Recipient</span>
                        <strong>{{ $draft->recipient }}</strong>
                    </div>
                    <div>
                        <span>Campaign</span>
                        <strong>{{ ucfirst($campaign->status->value) }}</strong>
                    </div>
                </div>

                @if ($draft->subject)
                    <div class="review-subject">
                        <span>Subject</span>
                        <strong>{{ $draft->subject }}</strong>
                    </div>
                @endif

                <div class="review-message">
                    <span>Message</span>
                    <p>{{ $draft->body }}</p>
                </div>

                @if (! empty($draft->personalization_notes))
                    <div class="review-notes">
                        @foreach ($draft->personalization_notes as $note)
                            <span>{{ $note }}</span>
                        @endforeach
                    </div>
                @endif

                <footer class="review-actions">
                    <a class="button button-secondary" href="{{ route('leads.show', $draft->lead) }}">Open lead</a>

                    <div class="review-actions-primary">
                        @if ($draft->status === \App\Models\OutreachDraft::STATUS_PENDING_REVIEW)
                            <form method="POST" action="{{ route('outreach-drafts.reject', [$draft->lead, $draft]) }}">
                                @csrf
                                @method('PATCH')
                                <button class="button button-secondary" type="submit">Reject</button>
                            </form>

                            <form method="POST" action="{{ route('outreach-drafts.approve', [$draft->lead, $draft]) }}">
                                @csrf
                                @method('PATCH')
                                <button class="button button-primary" type="submit">Approve</button>
                            </form>
                        @elseif ($draft->status === \App\Models\OutreachDraft::STATUS_APPROVED && $draft->channel === 'email')
                            @if ($campaignIsActive)
                                <form method="POST" action="{{ route('outreach-drafts.send', [$draft->lead, $draft]) }}">
                                    @csrf
                                    <button class="button button-primary" type="submit">Send Email</button>
                                </form>
                            @else
                                <span class="review-warning">Activate this campaign before sending email.</span>
                            @endif
                        @elseif ($draft->status === \App\Models\OutreachDraft::STATUS_APPROVED && $draft->channel === 'contact_form')
                            <a class="button button-primary" href="{{ $draft->recipient }}" target="_blank" rel="noopener noreferrer">
                                Open contact form
                            </a>
                        @endif
                    </div>
                </footer>
            </article>
        @empty
            <div class="review-empty">
                <strong>Review queue is clear</strong>
                <p>No pending or approved outreach drafts match the current filters.</p>
                <a class="button button-secondary" href="{{ route('leads.index') }}">Browse leads</a>
            </div>
        @endforelse
    </section>

    @if ($drafts->hasPages())
        <div class="review-pagination">
            @if ($drafts->onFirstPage())
                <span class="button button-secondary review-disabled">Previous</span>
            @else
                <a class="button button-secondary" href="{{ $drafts->previousPageUrl() }}">Previous</a>
            @endif

            <span>Page {{ $drafts->currentPage() }} of {{ $drafts->lastPage() }}</span>

            @if ($drafts->hasMorePages())
                <a class="button button-secondary" href="{{ $drafts->nextPageUrl() }}">Next</a>
            @else
                <span class="button button-secondary review-disabled">Next</span>
            @endif
        </div>
    @endif
@endsection
