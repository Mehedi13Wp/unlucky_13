@extends('layouts.app')

@section('title', 'Sender Pool')
@section('heading', 'Sender Pool')

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
            <div><span>Limit reached</span><strong>{{ $stats['limit_reached'] }}</strong></div>
            <div><span>Capacity today</span><strong>{{ $stats['remaining_today'] }}</strong></div>
        </div>
    </section>

    @if ($stats['total'] > 0 && $stats['available'] === 0)
        <div class="sender-alert">
            <strong>You have no available sender emails today.</strong>
            <span>Add/update a sender account, enable one, or wait until its daily limit resets.</span>
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
                    <small>Stored encrypted in the local database. For Gmail, use an app password rather than your normal Google password.</small>
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
                <p>Use CSV when you have many sender accounts. The uploaded file is read for import; passwords are stored encrypted in the database.</p>

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
                    <span>Keep the CSV private and remove it from shared/cloud locations after importing because it can contain SMTP app passwords.</span>
                </div>
            </div>
        </article>
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
                <p>Add one account or import your CSV. Until then, existing legacy mail configuration continues to work.</p>
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
                                <td>{{ $account->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td>
                                    <form method="POST" action="{{ route('sender-accounts.toggle', $account) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="button button-secondary button-small" type="submit">
                                            {{ $account->enabled ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="sender-next-step">
        <strong>Current phase</strong>
        <p>Sender rotation is connected to approved email delivery. Timed automatic queue sending is the next phase; review-before-send remains unchanged for now.</p>
    </section>
@endsection
