@extends('layouts.admin')

@section('title', 'Unit accounts')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Unit checker accounts</h2>
                <p>Accounts that sign in at <code>/unit/login</code> (own unit) or <code>/unit/loginM1</code> (all units) to review documents.</p>
            </div>
        </div>

        @php $withoutPassword = $accounts->where('is_active', 1)->where('has_password', false)->count(); @endphp
        @if ($withoutPassword)
            <div class="pcg-alert pcg-alert--warning" role="status">
                {{ $withoutPassword }} active {{ \Illuminate\Support\Str::plural('account', $withoutPassword) }} ha{{ $withoutPassword === 1 ? 's' : 've' }} no password and cannot sign in. Set one below.
            </div>
        @endif

        <div class="pcg-grid">
            <section class="pcg-card" aria-labelledby="accounts-heading">
                <div class="pcg-card__header">
                    <h3 id="accounts-heading">Accounts ({{ $accounts->count() }})</h3>
                </div>

                @if ($accounts->isEmpty())
                    <p class="pcg-empty">No unit accounts yet. Create the first one with the form.</p>
                @else
                    <div class="pcg-table-wrap">
                        <table class="pcg-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Unit</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($accounts as $account)
                                    <tr>
                                        <td>
                                            <strong>{{ $account->full_name ?: '—' }}</strong>
                                            <span class="pcg-table__muted">{{ $account->email }}</span>
                                        </td>
                                        <td>
                                            {{ $account->unit_name ?: '—' }}
                                            @if ($account->unit_code && $account->unit_code !== $account->unit_name)
                                                <span class="pcg-table__muted">{{ $account->unit_code }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (!$account->is_active)
                                                <span class="pcg-badge">Inactive</span>
                                            @elseif (!$account->has_password)
                                                <span class="pcg-badge pcg-badge--warning">No password</span>
                                            @else
                                                <span class="pcg-badge pcg-badge--success">Active</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="pcg-actions">
                                                <form method="POST" action="{{ route('admin.unit-users.password', $account->id) }}">
                                                    @csrf
                                                    <label class="pcg-visually-hidden" for="pw-{{ $account->id }}">New password for {{ $account->email }}</label>
                                                    <input class="pcg-input" type="password" id="pw-{{ $account->id }}" name="password"
                                                        minlength="8" required autocomplete="new-password" placeholder="New password">
                                                    <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Set password</button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.unit-users.status', $account->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="is_active" value="{{ $account->is_active ? 0 : 1 }}">
                                                    <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">
                                                        {{ $account->is_active ? 'Deactivate' : 'Activate' }}
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

            <section class="pcg-card" aria-labelledby="create-heading">
                <div class="pcg-card__header">
                    <h3 id="create-heading">Create account</h3>
                </div>
                <div class="pcg-card__body">
                    <form method="POST" action="{{ route('unit-users.store') }}" data-pcg-busy>
                        @csrf
                        <div class="pcg-field">
                            <label class="pcg-label" for="full_name">Full name</label>
                            <input class="pcg-input" id="full_name" name="full_name" value="{{ old('full_name') }}" required maxlength="255">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="email">Email</label>
                            <input class="pcg-input" type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="unit_name">Unit</label>
                            <select class="pcg-input" id="unit_name" name="unit_name" required>
                                <option value="">Select a unit</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit }}" @selected(old('unit_name') === $unit)>{{ $unit }}</option>
                                @endforeach
                            </select>
                            <p class="pcg-help">Unit checkers see personnel whose mother unit matches. MEPCOM accounts normally use the M1 login.</p>
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="serial_number">Serial number <span class="pcg-table__muted" style="display:inline">(optional)</span></label>
                            <input class="pcg-input" id="serial_number" name="serial_number" value="{{ old('serial_number') }}" maxlength="100">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="new_password">Password</label>
                            <input class="pcg-input" type="password" id="new_password" name="password" minlength="8" required autocomplete="new-password">
                            <p class="pcg-help">At least 8 characters.</p>
                        </div>
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Creating…">Create account</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
