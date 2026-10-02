@extends('layouts.app')

@section('title', 'Edit Campaign')
@section('heading', 'Edit Campaign')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/campaigns.css') }}">
@endpush

@section('topbar-actions')
    <a class="button button-secondary" href="{{ route('campaigns.show', $campaign) }}">Back to campaign</a>
@endsection

@section('content')
    <section class="campaign-form-hero">
        <div>
            <p class="eyebrow">Campaign settings</p>
            <h2>{{ $campaign->name }}</h2>
            <p>Update targeting and qualification settings without losing any existing lead history.</p>
        </div>

        <span class="status-tag {{ $campaign->status->value }}">{{ $campaign->status->value }}</span>
    </section>

    <form method="POST" action="{{ route('campaigns.update', $campaign) }}">
        @csrf
        @method('PUT')
        @include('campaigns.partials.form', ['campaign' => $campaign])
    </form>
@endsection
