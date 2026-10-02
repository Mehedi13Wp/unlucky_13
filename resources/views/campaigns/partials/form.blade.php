<div class="campaign-form-layout">
    <div class="form-main">
        <section class="campaign-section">
            <header class="campaign-section-head">
                <span class="section-index">01</span>
                <div>
                    <p class="eyebrow">Offer</p>
                    <h3>What are you selling?</h3>
                    <p>Describe the business outcome clearly enough for qualification and future personalization.</p>
                </div>
            </header>

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
                    <small>Focus on the business result, not a generic list of technical skills.</small>
                    @error('service_offer') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </div>
        </section>

        <section class="campaign-section">
            <header class="campaign-section-head">
                <span class="section-index">02</span>
                <div>
                    <p class="eyebrow">Geography</p>
                    <h3>Where should the worker search?</h3>
                    <p>Use countries for broad targeting and cities or areas when you want a tighter local campaign.</p>
                </div>
            </header>

            <div class="form-grid">
                <label class="field">
                    <span>Countries</span>
                    <textarea name="target_countries" placeholder="United States&#10;Canada">{{ old('target_countries', $campaign ? implode("
", $campaign->target_countries ?? []) : '') }}</textarea>
                    <small>Comma-separated or one country per line.</small>
                    @error('target_countries') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Cities / areas</span>
                    <textarea name="target_cities" placeholder="New York&#10;Boston&#10;Toronto">{{ old('target_cities', $campaign ? implode("
", $campaign->target_cities ?? []) : '') }}</textarea>
                    <small>Leave empty to target the full selected countries.</small>
                    @error('target_cities') <small class="field-error">{{ $message }}</small> @enderror
                </label>
            </div>
        </section>

        <section class="campaign-section">
            <header class="campaign-section-head">
                <span class="section-index">03</span>
                <div>
                    <p class="eyebrow">Qualification</p>
                    <h3>What counts as a useful lead?</h3>
                    <p>These rules become hard inputs for analysis instead of relying on vague AI judgment.</p>
                </div>
            </header>

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
                    <small>0–100. Start around 60 and tune after observing real lead quality.</small>
                    @error('minimum_score') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="field">
                    <span>Daily outreach limit</span>
                    <input type="number" name="daily_outreach_limit" min="1" max="100" required
                           value="{{ old('daily_outreach_limit', $campaign?->daily_outreach_limit ?? 20) }}">
                    <small>Hard campaign limit. The sender will never exceed this number.</small>
                    @error('daily_outreach_limit') <small class="field-error">{{ $message }}</small> @enderror
                </label>

                <label class="campaign-toggle">
                    <input type="hidden" name="requires_website" value="0">
                    <input type="checkbox" name="requires_website" value="1" {{ $requiresWebsite ? 'checked' : '' }}>
                    <span class="toggle-visual"></span>
                    <span>
                        <strong>Website required</strong>
                        <small>Only qualify businesses with a website that can be inspected.</small>
                    </span>
                </label>

                <label class="campaign-toggle">
                    <input type="hidden" name="requires_public_contact" value="0">
                    <input type="checkbox" name="requires_public_contact" value="1" {{ $requiresPublicContact ? 'checked' : '' }}>
                    <span class="toggle-visual"></span>
                    <span>
                        <strong>Public business contact required</strong>
                        <small>Require a legitimate public contact path before outreach preparation.</small>
                    </span>
                </label>
            </div>
        </section>

        <section class="campaign-section">
            <header class="campaign-section-head">
                <span class="section-index">04</span>
                <div>
                    <p class="eyebrow">Outreach safety</p>
                    <h3>Human review control</h3>
                    <p>Automatic sending stays locked until delivery, bounce, unsubscribe, and suppression handling is complete.</p>
                </div>
            </header>

            @php
                $reviewRequired = (int) old('review_required', $campaign?->review_required ?? 1);
            @endphp

            <div class="form-grid">
                <label class="campaign-toggle featured span-2">
                    <input type="hidden" name="review_required" value="0">
                    <input type="checkbox" name="review_required" value="1" {{ $reviewRequired ? 'checked' : '' }}>
                    <span class="toggle-visual"></span>
                    <span>
                        <strong>Review before send</strong>
                        <small>Recommended. Outreach drafts wait for your approval before any future sending action.</small>
                    </span>
                </label>
            </div>
        </section>

        <footer class="campaign-form-footer">
            <a class="button button-secondary" href="{{ $campaign ? route('campaigns.show', $campaign) : route('campaigns.index') }}">Cancel</a>
            <button class="button button-primary" type="submit">{{ $campaign ? 'Save changes' : 'Create draft campaign' }}</button>
        </footer>
    </div>

    <aside class="campaign-form-aside">
        <div class="aside-card sticky-aside">
            <p class="eyebrow">Campaign principles</p>
            <h3>Keep it focused</h3>

            <div class="aside-rule">
                <span>01</span>
                <div>
                    <strong>One clear offer</strong>
                    <small>Make the business outcome obvious.</small>
                </div>
            </div>

            <div class="aside-rule">
                <span>02</span>
                <div>
                    <strong>Narrow target</strong>
                    <small>Industry and geography should be specific.</small>
                </div>
            </div>

            <div class="aside-rule">
                <span>03</span>
                <div>
                    <strong>Real signals only</strong>
                    <small>Qualification should be grounded in observed facts.</small>
                </div>
            </div>

            <div class="aside-rule">
                <span>04</span>
                <div>
                    <strong>Review first</strong>
                    <small>Human approval remains the default.</small>
                </div>
            </div>
        </div>
    </aside>
</div>
