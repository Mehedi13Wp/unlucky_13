@extends('layouts.app')

@section('title', 'New Campaign')
@section('heading', 'New Campaign')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/campaigns.css') }}">
@endpush

@section('content')
    <section class="campaign-form-hero">
        <div>
            <p class="eyebrow">Campaign setup</p>
            <h2>Create a focused target</h2>
            <p>Define the offer, market, geography, qualification rules, and review limits before discovery begins.</p>
        </div>

        <div class="form-progress">
            <span class="complete">1</span>
            <span>2</span>
            <span>3</span>
            <span>4</span>
        </div>
    </section>

    <form method="POST" action="{{ route('campaigns.store') }}">
        @csrf
        @include('campaigns.partials.form', ['campaign' => null])
    </form>
@endsection
