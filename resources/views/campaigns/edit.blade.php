@extends('layouts.app')

@section('title', 'Edit Campaign')
@section('heading', 'Edit Campaign')

@section('content')
    <div class="page-intro">
        <div>
            <p class="eyebrow">Campaign settings</p>
            <h2>{{ $campaign->name }}</h2>
            <p>Update targeting and qualification rules without losing the campaign's lead history.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('campaigns.update', $campaign) }}">
        @csrf
        @method('PUT')
        @include('campaigns.partials.form', ['campaign' => $campaign])
    </form>
@endsection
