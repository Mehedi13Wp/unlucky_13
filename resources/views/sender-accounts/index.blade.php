@extends('layouts.app')

@section('title', 'Sender Accounts')
@section('heading', 'Sender Accounts')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/sender-accounts.css') }}">
@endpush

@section('content')
    <section class="sender-hero">
        <div>
            <p class="eyebrow">Outbound identity pool</p>
            <h2>Manage the email accounts Unlucky 13 can send from.</h2>
            <p>Accounts are used in order. Each sender stays active until its daily limit is reached, then delivery moves to the next available sender.</p>
        </div>

        <div class="sender-stats">
            <div><span>Total</span><strong>{{ $stats['total'] }}</strong></div>
            <div><span>Available</span><strong>{{ $stats['available'] }}</strong></div>
            <div><span>Verified</span><strong>{{ $stats['verified'] }}</strong></div>
            <div><span>Capacity today</span><strong>{{ $stats['remaining_today'] }}</strong></div>
        </div>
    </section>

    @if ($dryRun)
        <div class="sender-alert sender-alert-safe">
            <strong>DRY RUN is ON</strong>
            <span>Sender-account delivery is written to the Laravel log instead of opening a real SMTP delivery connection. Database states still change like a successful send, so use test campaigns only.</span>
        </div>
    @endif

    @if (session('import_errors') && count(session('import_errors')) > 0)
        <div class="sender-alert sender-alert-errors">
            <div>
                <strong>Some CSV rows were skipped</strong>
                <ul class="sender-import-errors">
                    @foreach (session('import_errors') as $importError)
                        <li>
                            Row {{ $importError['row'] }} · {{ $importError['email'] }}:
                            {{ implode(' ', $importError['errors']) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if ($stats['total'] > 0 && $stats['available'] === 0)
        <div class="sender-alert">
            <strong>You have no delivery-ready sender emails today.</strong>
            <span>Verify and enable a sender, finish its SMTP setup, or wait until its daily limit resets.</span>
        </div>
    @endif

    <section class="sender-grid">
        <article class="sender-panel">
            <header>
                <div>
                    <p class="eyebrow">Add one sender</p>
                    <h3>SMTP account</h3>
                </div>
            </header>

            <form class="sender-form" method="POST" action="{{ route('sender-accounts.store') }}">
                @csrf

                <label>
                    <span>Email</span>
                    <input type="email" name="email" required placeholder="sender@gmail.com" value="{{ old('email') }}">
                </label>

                <label>
                    <span>From name</span>
                    <input type="text" name="from_name" placeholder="Mehedi" value="{{ old('from_name') }}">
                </label>

                <label>
                    <span>SMTP host</span>
                    <input type="text" name="smtp_host" required value="{{ old('smtp_host', 'smtp.gmail.com') }}">
                </label>

                <label>
                    <span>Port</span>
                    <input type="number" name="smtp_port" min="1" max="65535" required value="{{ old('smtp_port', 587) }}">
                </label>

                <label>
                    <span>Scheme</span>
                    <select name="smtp_scheme">
                        <option value="smtp" @selected(old('smtp_scheme', 'smtp') === 'smtp')>smtp / STARTTLS</option>
                        <option value="smtps" @selected(old('smtp_scheme') === 'smtps')>smtps</option>
                    </select>
                </label>

                <label>
                    <span>SMTP username</span>
                    <input type="text" name="smtp_username" placeholder="Defaults to email" value="{{ old('smtp_username') }}">
                </label>

                <label class="span-2">
                    <span>App password / SMTP password</span>
                    <input type="password" name="smtp_password" required autocomplete="new-password">
                    <small>Stored encrypted in the local database. For Gmail, use an app password rather than your normal Google password. Saving or importing credentials clears previous verification.</small>
                </label>

                <label>
                    <span>Daily limit</span>
                    <input type="number" name="daily_limit" min="1" max="50" required value="{{ old('daily_limit', 20) }}">
                </label>

                <div class="sender-form-actions span-2">
                    <button class="button button-primary" type="submit">Save sender</button>
                </div>
            </form>
        </article>

        <article class="sender-panel">
            <header>
                <div>
                    <p class="eyebrow">Bulk setup</p>
                    <h3>Import sender CSV</h3>
                </div>
            </header>

            <div class="sender-import">
                <p>Use CSV when you have many sender accounts. The uploaded file is read for import; passwords are stored encrypted in the database. Imported senders must be SMTP-verified before live automation can use them.</p>

                <a class="button button-secondary" href="{{ route('sender-accounts.template') }}">Download CSV template</a>

                <form method="POST" action="{{ route('sender-accounts.import') }}" enctype="multipart/form-data">
                    @csrf
                    <label>
                        <span>CSV file</span>
                        <input type="file" name="csv" accept=".csv,text/csv" required>
                    </label>
                    <button class="button button-primary" type="submit">Import CSV</button>
                </form>

                <div class="sender-security-note">
                    <strong>Security</strong>
                    <span>Keep the CSV private and remove it from shared/cloud locations after importing because it can contain SMTP app passwords. CSV validation errors never display passwords.</span>
                </div>
            </div>
        </article>
    </section>

    <section class="sender-panel sender-automation-panel">
        <header>
            <div>
                <p class="eyebrow">Controlled auto delivery</p>
                <h3>Approved queue session</h3>
            </div>
            <span class="sender-status {{ $automation->enabled ? 'available' : 'disabled' }}">
                {{ $automation->enabled ? 'Active' : 'Stopped' }}
            </span>
        </header>

        <div class="automation-body">
            <div class="automation-summary">
                <div>
                    <span>Approved queue</span>
                    <strong>{{ $automationStats['approved_queue'] }}</strong>
                </div>
                <div>
                    <span>Sent today</span>
                    <strong>{{ $automationStats['sent_today'] }}</strong>
                </div>
                <div>
                    <span>Global remaining</span>
                    <strong>{{ $automationStats['remaining_today'] }}</strong>
                </div>
                <div>
                    <span>Session ends</span>
                    <strong>{{ $automation->session_ends_at?->diffForHumans() ?? 'Not running' }}</strong>
                </div>
            </div>

            <div class="automation-message">
                <strong>{{ ucwords(str_replace('_', ' ', $automation->last_status ?: 'ready')) }}</strong>
                <span>{{ $automation->last_message ?: 'Automatic delivery is off. Start a controlled session when your sender accounts are ready.' }}</span>
            </div>

            <form class="automation-form" method="POST" action="{{ route('sender-accounts.automation.start') }}">
                @csrf

                <label>
                    <span>Global daily limit</span>
                    <input type="number" name="global_daily_limit" min="1" max="100" required
                           value="{{ old('global_daily_limit', $automation->global_daily_limit ?: 20) }}">
                </label>

                <label>
                    <span>Minutes between emails</span>
                    <input type="number" name="interval_minutes" min="5" max="120" required
                           value="{{ old('interval_minutes', $automation->interval_minutes ?: 15) }}">
                </label>

                <label>
                    <span>Session length (hours)</span>
                    <input type="number" name="session_hours" min="1" max="12" required
                           value="{{ old('session_hours', $automation->session_hours ?: 5) }}">
                </label>

                <div class="automation-actions">
                    <button class="button button-primary" type="submit">
                        {{ $automation->enabled ? 'Restart session' : ($dryRun ? 'Start dry run' : 'Start auto delivery') }}
                    </button>
                </div>
            </form>

            @if ($automation->enabled)
                <form class="automation-stop" method="POST" action="{{ route('sender-accounts.automation.stop') }}">
                    @csrf
                    <button class="button button-secondary" type="submit">Stop auto delivery</button>
                </form>
            @endif

            <p class="automation-rule">
                Auto delivery never approves a draft. In live mode it only uses enabled, fully configured, SMTP-verified senders. Campaign limits, sender limits, suppression, the global limit, retry windows and the configured interval still apply.
            </p>
        </div>
    </section>

    <section class="sender-panel sender-list-panel">
        <header>
            <div>
                <p class="eyebrow">Rotation order</p>
                <h3>Sender accounts</h3>
            </div>
            <span>{{ $stats['available'] }} available now</span>
        </header>

        @if ($rows->isEmpty())
            <div class="sender-empty">
                <strong>No sender accounts yet</strong>
                <p>Add one account or import your sender CSV. Real automation cannot start until at least one account is delivery-ready.</p>
            </div>
        @else
            <div class="sender-table-wrap">
                <table class="sender-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sender</th>
                            <th>Sent today</th>
                            <th>Daily limit</th>
                            <th>Remaining</th>
                            <th>Status</th>
                            <th>SMTP verification</th>
                            <th>Last used</th>
                            <th>Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($account = $row['account'])
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <strong>{{ $account->email }}</strong>
                                    <small>{{ $account->from_name ?: 'No from name' }}</small>
                                </td>
                                <td>{{ $row['sent_today'] }}</td>
                                <td>{{ $account->daily_limit }}</td>
                                <td>{{ $row['remaining_today'] }}</td>
                                <td>
                                    <span class="sender-status {{ $row['status'] }}">
                                        {{ ucwords(str_replace('_', ' ', $row['status'])) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($account->isVerified())
                                        <span class="sender-status available">Verified</span>
                                        <small>{{ $account->smtp_verified_at?->diffForHumans() }}</small>
                                    @else
                                        <span class="sender-status needs_verification">Not verified</span>
                                        @if ($account->smtp_verification_error)
                                            <small title="{{ $account->smtp_verification_error }}">Last test failed</small>
                                        @elseif ($account->smtp_verification_attempted_at)
                                            <small>Tested {{ $account->smtp_verification_attempted_at->diffForHumans() }}</small>
                                        @else
                                            <small>Never tested</small>
                                        @endif
                                    @endif
                                </td>
                                <td>{{ $account->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td>
                                    <div class="sender-controls">
                                        <form method="POST" action="{{ route('sender-accounts.verify', $account) }}">
                                            @csrf
                                            <button class="button button-primary button-small" type="submit" @disabled(! $account->isConfigured())>
                                                {{ $account->isVerified() ? 'Re-verify SMTP' : 'Verify SMTP' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('sender-accounts.toggle', $account) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="button button-secondary button-small" type="submit">
                                                {{ $account->enabled ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="sender-next-step">
        <strong>Delivery safety</strong>
        <p>For live delivery, verify SMTP first. The verification action sends one test message from the configured sender back to the same sender address. If the sender pool runs out, SMTP delivery fails, the session expires, or a daily limit is reached, automatic delivery stops or pauses instead of bypassing the protection.</p>
    </section>
@endsection
