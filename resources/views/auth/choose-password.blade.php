{{--
    First sign-in with an emailed temporary password: the account chooses its own
    password (ChoosePasswordController). Expects $action, $eyebrow, $email, $logout.
--}}
@extends('layouts.auth')

@section('title', 'Choose your password')
@section('eyebrow', $eyebrow)
@section('headline', 'One last step')
@section('intro', 'You signed in with a temporary password. Choose your own before you continue.')

@section('content')
    <span class="pcg-auth__role">First sign-in</span>
    <h2>Choose your password</h2>
    <p class="pcg-auth__lead">For <strong>{{ $email }}</strong>. The temporary password from the email stops working as soon as you save.</p>

    @include('auth.partials.alerts')

    <form method="POST" action="{{ $action }}" data-pcg-busy>
        @csrf
        <input type="text" name="username" value="{{ $email }}" autocomplete="username" hidden>
        <div class="pcg-field">
            <label class="pcg-label" for="password">New password</label>
            <div class="pcg-input-group">
                <input class="pcg-input" type="password" id="password" name="password" autocomplete="new-password" minlength="12" required autofocus
                    @error('password') aria-invalid="true" aria-describedby="form-errors" @enderror>
                <button type="button" class="pcg-input-group__action" data-pcg-toggle-password="password" aria-pressed="false" aria-label="Show password">Show</button>
            </div>
            <p class="pcg-help">At least 12 characters, with letters and numbers. Different from the temporary password.</p>
        </div>
        <div class="pcg-field">
            <label class="pcg-label" for="password_confirmation">Type it again</label>
            <input class="pcg-input" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" required>
        </div>
        <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block" data-busy-label="Saving…">Save and continue</button>
    </form>

    <form method="POST" action="{{ $logout }}" class="pcg-auth__alt">
        @csrf
        <button type="submit" class="pcg-btn pcg-btn--secondary pcg-btn--block">Sign out</button>
    </form>
@endsection
