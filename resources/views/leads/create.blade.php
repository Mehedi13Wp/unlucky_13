@extends('layouts.app')

@section('title', 'Add Lead')
@section('heading', 'Add Lead')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/leads.css') }}">
@endpush

@section('content')
    <section class="lead-form-hero">
        <div>
            <p class="eyebrow">Manual intake</p>
            <h2>Add a business to the pipeline</h2>
            <p>Manual entry is useful for testing the lead workflow before the automated discovery worker is connected.</p>
        </div>
    </section>

    <form class="lead-form-card" method="POST" action="{{ route('leads.store') }}">
        @csrf

        <div class="lead-form-grid">
            <label class="field span-2">
                <span>Campaign</span>
                <select name="campaign_uuid" required>
                    <option value="">Select campaign</option>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->uuid }}"
                            @selected(old('campaign_uuid', $selectedCampaign) === $campaign->uuid)>
                            {{ $campaign->name }}
                        </option>
                    @endforeach
                </select>
                @error('campaign_uuid') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field span-2">
                <span>Business name</span>
                <input type="text" name="business_name" value="{{ old('business_name') }}" required maxlength="180" placeholder="Example Restaurant">
                @error('business_name') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field span-2">
                <span>Website</span>
                <input type="text" name="website_url" value="{{ old('website_url') }}" placeholder="example.com">
                <small>The domain will be normalized automatically for duplicate protection.</small>
                @error('website_url') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field span-2">
                <span>Contact email <small>(optional)</small></span>
                <input type="email" name="contact_email" value="{{ old('contact_email') }}" placeholder="your-test-inbox@gmail.com">
                <small>Use this for a known public contact or a controlled test inbox. A manually entered email skips contact rediscovery for this lead.</small>
                @error('contact_email') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Industry</span>
                <input type="text" name="industry" value="{{ old('industry') }}" placeholder="Restaurants">
                @error('industry') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Source</span>
                <input type="text" name="source" value="{{ old('source', 'manual') }}" placeholder="manual">
                @error('source') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Country</span>
                <input type="text" name="country" value="{{ old('country') }}" placeholder="United States">
                @error('country') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>City</span>
                <input type="text" name="city" value="{{ old('city') }}" placeholder="New York">
                @error('city') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field span-2">
                <span>Source URL</span>
                <input type="url" name="source_url" value="{{ old('source_url') }}" placeholder="https://source.example/business">
                @error('source_url') <small class="field-error">{{ $message }}</small> @enderror
            </label>
        </div>

        <footer class="lead-form-footer">
            <a class="button button-secondary" href="{{ route('leads.index') }}">Cancel</a>
            <button class="button button-primary" type="submit">Add lead</button>
        </footer>
    </form>
@endsection
