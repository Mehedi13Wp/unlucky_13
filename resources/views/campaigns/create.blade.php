@extends('layouts.app')

@section('title', 'New Campaign')
@section('heading', 'New Campaign')

@section('content')
    <div class="page-intro">
        <div>
            <p class="eyebrow">Campaign setup</p>
            <h2>Create a focused target</h2>
            <p>Start narrow. The worker should know what you sell, who needs it, and what counts as a qualified lead.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('campaigns.store') }}">
        @csrf
        @include('campaigns.partials.form', ['campaign' => null])
    </form>
@endsection
