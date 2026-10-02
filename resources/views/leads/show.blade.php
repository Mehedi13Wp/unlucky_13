@extends('layouts.app')

@section('title', $lead->business_name)
@section('heading', 'Lead Detail')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leads.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-secondary" href="{{ route('leads.index') }}">All leads</a>
@endsection

@section('content')
    @php($latestDraft = $lead->outreachDrafts->first())

    <section class="lead-detail-hero">
        <div>
            <div class="lead-detail-kicker">
                <span class="status-tag {{ $lead->status->value }}">{{ str_replace('_', ' ', $lead->status->value) }}</span>
                <span>{{ $lead->campaign->name }}</span>
            </div>
            <h2>{{ $lead->business_name }}</h2>
            <p>{{ $lead->domain ?: 'Website domain not available' }}</p>
        </div>

        <div class="lead-score-card">
            <span>Qualification score</span>
            <strong>{{ $lead->qualification_score !== null ? $lead->qualification_score.'/100' : 'Pending' }}</strong>
            <small>{{ $lead->analyzed_at ? 'Analyzed '.$lead->analyzed_at->diffForHumans() : 'Waiting for analysis' }}</small>
        </div>
    </section>

    <section class="lead-detail-grid">
        <div class="lead-detail-column">
            <article class="lead-panel">
                <header>
                    <p class="eyebrow">Business profile</p>
                    <h3>Lead information</h3>
                </header>

                <div class="lead-detail-list">
                    <div><span>Industry</span><strong>{{ $lead->industry ?: 'Not set' }}</strong></div>
                    <div><span>Location</span><strong>{{ implode(', ', array_filter([$lead->city, $lead->country])) ?: 'Not set' }}</strong></div>
                    <div><span>Source</span><strong>{{ $lead->source ?: 'Unknown' }}</strong></div>
                    <div><span>Website</span>
                        @if ($lead->website_url)
                            <a href="{{ $lead->website_url }}" target="_blank" rel="noopener noreferrer">{{ $lead->domain ?: $lead->website_url }}</a>
                        @else
                            <strong>Not available</strong>
                        @endif
                    </div>
                </div>
            </article>

            <article class="lead-panel">
                <header>
                    <p class="eyebrow">Qualification</p>
                    <h3>Analysis result</h3>
                </header>

                <p class="lead-reason">{{ $lead->qualification_reason ?: 'No qualification analysis has been recorded yet.' }}</p>
            </article>

            <article class="lead-panel">
                <header>
                    <p class="eyebrow">Public contact discovery</p>
                    <h3>Contact paths</h3>
                </header>

                @if ($lead->contacts->isNotEmpty())
                    <div class="lead-detail-list">
                        @foreach ($lead->contacts as $contact)
                            <div>
                                <span>{{ ucwords(str_replace('_', ' ', $contact->type)) }}</span>
                                <strong>
                                    @if ($contact->type === 'email')
                                        <a href="mailto:{{ $contact->value }}">{{ $contact->value }}</a>
                                    @elseif ($contact->type === 'phone')
                                        <a href="tel:{{ $contact->value }}">{{ $contact->value }}</a>
                                    @elseif ($contact->type === 'contact_page')
                                        <a href="{{ $contact->value }}" target="_blank" rel="noopener noreferrer">Open public contact page</a>
                                    @else
                                        {{ $contact->value }}
                                    @endif
                                </strong>
                            </div>
                        @endforeach
                    </div>
                @elseif ($lead->contact_discovered_at)
                    <p class="lead-reason">Contact discovery completed, but no public contact path was found on the inspected pages.</p>
                @else
                    <p class="lead-reason">Contact discovery has not run for this qualified lead yet.</p>
                @endif
            </article>

            <article class="lead-panel">
                <header>
                    <p class="eyebrow">Review before send</p>
                    <h3>Latest outreach draft</h3>
                </header>

                @if ($latestDraft)
                    <div class="lead-detail-list">
                        <div>
                            <span>Draft status</span>
                            <strong>{{ ucwords(str_replace('_', ' ', $latestDraft->status)) }}</strong>
                        </div>
                        <div>
                            <span>Channel</span>
                            <strong>{{ $latestDraft->channel === 'contact_form' ? 'Contact form' : 'Email' }}</strong>
                        </div>
                        <div>
                            <span>Recipient</span>
                            <strong>
                                @if ($latestDraft->channel === 'email')
                                    {{ $latestDraft->recipient }}
                                @else
                                    <a href="{{ $latestDraft->recipient }}" target="_blank" rel="noopener noreferrer">Open contact form</a>
                                @endif
                            </strong>
                        </div>
                        @if ($latestDraft->subject)
                            <div>
                                <span>Subject</span>
                                <strong>{{ $latestDraft->subject }}</strong>
                            </div>
                        @endif
                    </div>

                    <p class="lead-reason" style="white-space: pre-line;">{{ $latestDraft->body }}</p>

                    @if (! empty($latestDraft->personalization_notes))
                        <div class="signal-list">
                            @foreach ($latestDraft->personalization_notes as $note)
                                <span>{{ $note }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($latestDraft->status === 'pending_review')
                        <div class="lead-form-footer" style="margin: 18px -19px -19px;">
                            <form method="POST" action="{{ route('outreach-drafts.reject', [$lead, $latestDraft]) }}">
                                @csrf
                                @method('PATCH')
                                <button class="button button-secondary" type="submit">Reject draft</button>
                            </form>

                            <form method="POST" action="{{ route('outreach-drafts.approve', [$lead, $latestDraft]) }}">
                                @csrf
                                @method('PATCH')
                                <button class="button button-primary" type="submit">Approve draft</button>
                            </form>
                        </div>
                    @elseif ($latestDraft->status === 'approved')
                        <p class="lead-reason">Approved for the future sending pipeline. Nothing has been sent yet.</p>
                    @endif
                @else
                    <p class="lead-reason">No outreach draft has been generated yet.</p>
                @endif
            </article>
        </div>

        <aside class="lead-detail-column">
            <article class="lead-panel">
                <header>
                    <p class="eyebrow">Pipeline</p>
                    <h3>Lifecycle</h3>
                </header>

                <div class="lead-detail-list">
                    <div><span>Status</span><strong>{{ ucwords(str_replace('_', ' ', $lead->status->value)) }}</strong></div>
                    <div><span>Created</span><strong>{{ $lead->created_at->format('M j, Y H:i') }}</strong></div>
                    <div><span>Qualified</span><strong>{{ $lead->qualified_at?->format('M j, Y H:i') ?: 'Not yet' }}</strong></div>
                    <div><span>Contact discovery</span><strong>{{ $lead->contact_discovered_at?->format('M j, Y H:i') ?: 'Not yet' }}</strong></div>
                    <div><span>Last contacted</span><strong>{{ $lead->last_contacted_at?->format('M j, Y H:i') ?: 'Never' }}</strong></div>
                </div>
            </article>

            <article class="lead-panel">
                <header>
                    <p class="eyebrow">Observed signals</p>
                    <h3>Evidence</h3>
                </header>

                @if (! empty($lead->observed_signals))
                    <div class="signal-list">
                        @foreach ($lead->observed_signals as $signal)
                            <span>{{ is_string($signal) ? $signal : json_encode($signal) }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="lead-reason">No observed signals recorded yet. The analyzer will populate this later.</p>
                @endif
            </article>
        </aside>
    </section>
@endsection
