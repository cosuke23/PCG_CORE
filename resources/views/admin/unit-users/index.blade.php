@extends('layouts.admin')

@section('title', 'Unit accounts')

@section('content')
    <div class="pcg-page">
        <div class="pcg-page__header">
            <div>
                <h2>Unit checker accounts</h2>
                <p>Own-unit checkers sign in at <code>/unit/login</code> and review their unit's documents.
                    M1 checkers sign in at <code>/unit/loginM1</code> and also see all units, 201 files and disciplinary records.</p>
            </div>
        </div>

        @php $canManage = \App\Support\AdminPermissions::can('documents.restricted'); $withoutPassword = $accounts->where('is_active', 1)->where('has_password', false)->count(); @endphp
        @if ($withoutPassword)
            <div class="pcg-alert pcg-alert--warning" role="status">
                {{ $withoutPassword }} active {{ \Illuminate\Support\Str::plural('account', $withoutPassword) }} ha{{ $withoutPassword === 1 ? 's' : 've' }} no password and cannot sign in. Use "Email new password" below.
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
                                    <th scope="col">Access</th>
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
                                            @php $isM1 = $account->role === \App\Models\UnitUser::ROLE_M1; @endphp
                                            @if ($isM1)
                                                <span class="pcg-badge pcg-badge--info">M1 · all units</span>
                                            @else
                                                <span class="pcg-badge">Own unit</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (!$account->is_active)
                                                <span class="pcg-badge">Inactive</span>
                                            @elseif (!$account->has_password)
                                                <span class="pcg-badge pcg-badge--warning">No password</span>
                                            @elseif (!empty($account->must_change_password))
                                                <span class="pcg-badge pcg-badge--warning">Must choose a password</span>
                                            @else
                                                <span class="pcg-badge pcg-badge--success">Active</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="pcg-actions">
                                                @if ($canManage)
                                                <form method="POST" action="{{ route('admin.unit-users.password', $account->id) }}">
                                                    @csrf
                                                    <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm"
                                                        data-pcg-confirm-title="Email a new password?"
                                                        data-pcg-confirm="A new temporary password will be emailed to {{ $account->email }}. Their current password stops working, and they must choose their own at their next sign-in."
                                                        data-pcg-confirm-ok="Email new password">
                                                        <i class="fa-solid fa-envelope" aria-hidden="true"></i> Email new password <span class="pcg-visually-hidden">to {{ $account->email }}</span>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.unit-users.role', $account->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="role" value="{{ $isM1 ? \App\Models\UnitUser::ROLE_UNIT : \App\Models\UnitUser::ROLE_M1 }}">
                                                    <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--sm">
                                                        {{ $isM1 ? 'Remove M1 access' : 'Grant M1 access' }}
                                                    </button>
                                                </form>
                                                @endif
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

            @if ($canManage)
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
                        <p class="pcg-help">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            A temporary password is created and emailed to the checker. At their first sign-in they must choose their own password.
                        </p>
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Creating…">Create account</button>
                    </form>
                </div>
            </section>
            @else
            <p class="pcg-muted">Creating checker accounts and setting their passwords is limited to admins who may see restricted documents, because every checker account can open medical clearances.</p>
            @endif
        </div>
    </div>
@endsection
