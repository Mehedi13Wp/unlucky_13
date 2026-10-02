<div class="form-card">
    <section class="form-section">
        <div class="form-section-head">
            <p class="eyebrow">Offer</p>
            <h3>What are you selling?</h3>
            <p>Describe the business outcome clearly enough for qualification and future personalization.</p>
        </div>

        <div class="form-grid">
            <label class="field">
                <span>Campaign name</span>
                <input type="text" name="name" maxlength="120" required
                       value="{{ old('name', $campaign?->name) }}"
                       placeholder="US Restaurant Booking Systems">
                @error('name') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Target industry</span>
                <input type="text" name="target_industry" maxlength="100"
                       value="{{ old('target_industry', $campaign?->target_industry) }}"
                       placeholder="Restaurants">
                @error('target_industry') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field span-2">
                <span>Service / offer</span>
                <textarea name="service_offer" required placeholder="I build online booking systems that help restaurants accept reservations directly...">{{ old('service_offer', $campaign?->service_offer) }}</textarea>
                <small>Use a concrete outcome, not a generic list of development skills.</small>
                @error('service_offer') <small class="field-error">{{ $message }}</small> @enderror
            </label>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <p class="eyebrow">Geography</p>
            <h3>Where should the worker search?</h3>
            <p>Enter comma-separated values or one item per line. Leave cities empty to target the full selected countries.</p>
        </div>

        <div class="form-grid">
            <label class="field">
                <span>Countries</span>
                <textarea name="target_countries" placeholder="United States&#10;Canada">{{ old('target_countries', $campaign ? implode("
", $campaign->target_countries ?? []) : '') }}</textarea>
                @error('target_countries') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Cities / areas</span>
                <textarea name="target_cities" placeholder="New York&#10;Boston&#10;Toronto">{{ old('target_cities', $campaign ? implode("
", $campaign->target_cities ?? []) : '') }}</textarea>
                @error('target_cities') <small class="field-error">{{ $message }}</small> @enderror
            </label>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <p class="eyebrow">Qualification</p>
            <h3>What counts as a useful lead?</h3>
            <p>These rules become hard inputs for the analysis worker instead of relying on vague AI judgment.</p>
        </div>

        @php
            $rules = $campaign?->qualification_rules ?? [];
            $minimumScore = old('minimum_score', $rules['minimum_score'] ?? 60);
            $requiresWebsite = (int) old('requires_website', isset($rules['requires_website']) ? $rules['requires_website'] : 1);
            $requiresPublicContact = (int) old('requires_public_contact', isset($rules['requires_public_contact']) ? $rules['requires_public_contact'] : 1);
        @endphp

        <div class="form-grid">
            <label class="field">
                <span>Minimum qualification score</span>
                <input type="number" name="minimum_score" min="0" max="100" required value="{{ $minimumScore }}">
                <small>0–100. Start around 60 and tune it after observing real leads.</small>
                @error('minimum_score') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="field">
                <span>Daily outreach limit</span>
                <input type="number" name="daily_outreach_limit" min="1" max="100" required
                       value="{{ old('daily_outreach_limit', $campaign?->daily_outreach_limit ?? 20) }}">
                <small>Hard campaign limit. The sender will not exceed this value.</small>
                @error('daily_outreach_limit') <small class="field-error">{{ $message }}</small> @enderror
            </label>

            <label class="toggle-card">
                <input type="hidden" name="requires_website" value="0">
                <input type="checkbox" name="requires_website" value="1" {{ $requiresWebsite ? 'checked' : '' }}>
                <span>
                    <strong>Website required</strong>
                    <small>Only qualify businesses with a website that can be inspected.</small>
                </span>
            </label>

            <label class="toggle-card">
                <input type="hidden" name="requires_public_contact" value="0">
                <input type="checkbox" name="requires_public_contact" value="1" {{ $requiresPublicContact ? 'checked' : '' }}>
                <span>
                    <strong>Public business contact required</strong>
                    <small>Require a legitimate public business contact path before outreach preparation.</small>
                </span>
            </label>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-head">
            <p class="eyebrow">Outreach safety</p>
            <h3>Human review control</h3>
            <p>Automatic sending stays locked until the delivery, bounce, unsubscribe, and suppression pipeline is built.</p>
        </div>

        @php
            $reviewRequired = (int) old('review_required', $campaign?->review_required ?? 1);
        @endphp

        <div class="form-grid">
            <label class="toggle-card span-2">
                <input type="hidden" name="review_required" value="0">
                <input type="checkbox" name="review_required" value="1" {{ $reviewRequired ? 'checked' : '' }}>
                <span>
                    <strong>Review before send</strong>
                    <small>Recommended. Drafts will wait for your approval before any future sending action.</small>
                </span>
            </label>
        </div>
    </section>

    <footer class="form-footer">
        <a class="button button-secondary" href="{{ $campaign ? route('campaigns.show', $campaign) : route('campaigns.index') }}">Cancel</a>
        <button class="button button-primary" type="submit">{{ $campaign ? 'Save changes' : 'Create draft campaign' }}</button>
    </footer>
</div>
