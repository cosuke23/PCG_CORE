@extends('layouts.admin')

@section('title', 'Admin accounts')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Administrator accounts</h2>
                <p>Every account signs in with a password and a code from an authenticator app. All changes are recorded in the <a href="{{ route('admin.accounts.audit') }}">audit log</a>.</p>
            </div>
        </div>

        @if (session('admin_bootstrap'))
            <div class="pcg-alert pcg-alert--warning" role="status">
                You are using the setup account from <code>.env</code>. Create a <strong>super admin</strong> account for yourself below,
                then sign out and sign in with it. The setup account stops working once any admin account exists.
            </div>
        @endif

        <div class="pcg-grid">
            <section class="pcg-card" aria-labelledby="admins-heading">
                <div class="pcg-card__header"><h3 id="admins-heading">Accounts ({{ $admins->count() }})</h3></div>

                @if ($admins->isEmpty())
                    <p class="pcg-empty">No admin accounts yet. Create the first super admin with the form.</p>
                @else
                    <div class="pcg-table-wrap">
                        <table class="pcg-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Role and scope</th>
                                    <th scope="col">Security</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($admins as $admin)
                                    <tr>
                                        <td>
                                            <strong>{{ $admin->name }}</strong>
                                            <span class="pcg-table__muted">{{ $admin->email }}</span>
                                            <span class="pcg-table__muted">Last sign-in: {{ $admin->last_login_at?->format('M d, Y H:i') ?? 'never' }}</span>
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.accounts.role', $admin) }}" class="pcg-inline-form">
                                                @csrf
                                                <label class="pcg-visually-hidden" for="role-{{ $admin->id }}">Role of {{ $admin->email }}</label>
                                                <select class="pcg-input pcg-input--sm" id="role-{{ $admin->id }}" name="role">
                                                    @foreach (array_keys($roles) as $role)
                                                        <option value="{{ $role }}" @selected($admin->role === $role)>{{ str_replace('_', ' ', $role) }}</option>
                                                    @endforeach
                                                </select>
                                                <label class="pcg-visually-hidden" for="scope-{{ $admin->id }}">Access scope of {{ $admin->email }}</label>
                                                <select class="pcg-input pcg-input--sm" id="scope-{{ $admin->id }}" name="org_unit_id">
                                                    <option value="">All of PCG</option>
                                                    @foreach ($units as $unit)
                                                        <option value="{{ $unit->id }}" @selected((int) $admin->org_unit_id === (int) $unit->id)>{{ str_repeat('— ', $unit->depth) }}{{ $unit->code }} only</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Save</button>
                                            </form>
                                        </td>
                                        <td>
                                            @if (!$admin->is_active)
                                                <span class="pcg-badge">Inactive</span>
                                            @endif
                                            @if (\App\Support\EmailLoginCode::enabled())
                                                <span class="pcg-badge pcg-badge--success">Email code at sign-in</span>
                                            @elseif ($admin->hasTwoFactor())
                                                <span class="pcg-badge pcg-badge--success">2FA on</span>
                                            @else
                                                <span class="pcg-badge pcg-badge--warning">2FA at next sign-in</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="pcg-actions">
                                                <form method="POST" action="{{ route('admin.accounts.password', $admin) }}">
                                                    @csrf
                                                    <label class="pcg-visually-hidden" for="apw-{{ $admin->id }}">New password for {{ $admin->email }}</label>
                                                    <input class="pcg-input" type="password" id="apw-{{ $admin->id }}" name="password" minlength="12" required autocomplete="new-password" placeholder="New password">
                                                    <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Set password</button>
                                                </form>
                                                @if ($admin->hasTwoFactor())
                                                    <form method="POST" action="{{ route('admin.accounts.two-factor.reset', $admin) }}">
                                                        @csrf
                                                        <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">Reset 2FA</button>
                                                    </form>
                                                @endif
                                                @if ($admin->id !== session('admin_user_id'))
                                                    <form method="POST" action="{{ route('admin.accounts.status', $admin) }}">
                                                        @csrf
                                                        <input type="hidden" name="is_active" value="{{ $admin->is_active ? 0 : 1 }}">
                                                        <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">{{ $admin->is_active ? 'Deactivate' : 'Activate' }}</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="pcg-card" aria-labelledby="new-admin-heading">
                <div class="pcg-card__header"><h3 id="new-admin-heading">Create admin account</h3></div>
                <div class="pcg-card__body">
                    <form method="POST" action="{{ route('admin.accounts.store') }}" data-pcg-busy>
                        @csrf
                        <div class="pcg-field">
                            <label class="pcg-label" for="name">Full name</label>
                            <input class="pcg-input" id="name" name="name" value="{{ old('name') }}" required maxlength="255">
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="email">Email</label>
                            <input class="pcg-input" type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="off">
                        </div>
                        <fieldset class="pcg-field pcg-fieldset">
                            <legend class="pcg-label">Role</legend>
                            @foreach ($roles as $role => $description)
                                <label class="pcg-radio">
                                    <input type="radio" name="role" value="{{ $role }}" @checked(old('role', $admins->isEmpty() ? 'super_admin' : 'hr_admin') === $role) required>
                                    <span>{{ $description }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                        <div class="pcg-field">
                            <label class="pcg-label" for="org_unit_id">Access scope</label>
                            <select class="pcg-input" id="org_unit_id" name="org_unit_id">
                                <option value="">All of PCG (organisation-wide)</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}" @selected((string) old('org_unit_id') === (string) $unit->id)>{{ str_repeat('— ', $unit->depth) }}{{ $unit->code }} and its units only</option>
                                @endforeach
                            </select>
                            <p class="pcg-help">HR admins only. A command HR admin (e.g. MEPCOM) sees and manages only personnel assigned there.</p>
                        </div>
                        <div class="pcg-field">
                            <label class="pcg-label" for="password">Temporary password</label>
                            <input class="pcg-input" type="password" id="password" name="password" minlength="12" required autocomplete="new-password">
                            <p class="pcg-help">At least 12 characters. Share it securely; two-factor setup happens at first sign-in.</p>
                        </div>
                        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Creating…">Create account</button>
                    </form>
                </div>
            </section>
        </div>
    </div>
@endsection
