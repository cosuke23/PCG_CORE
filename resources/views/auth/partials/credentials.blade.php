{{-- Email + password fields. Expects $action; optional $submitLabel. --}}
<form method="POST" action="{{ $action }}" data-pcg-busy>
    @csrf

    <div class="pcg-field">
        <label class="pcg-label" for="email">Email address</label>
        <input class="pcg-input" type="email" id="email" name="email" value="{{ old('email') }}"
            autocomplete="username" required autofocus
            @error('email') aria-invalid="true" aria-describedby="form-errors" @enderror>
    </div>

    <div class="pcg-field">
        <label class="pcg-label" for="password">Password</label>
        <div class="pcg-input-group">
            <input class="pcg-input" type="password" id="password" name="password"
                autocomplete="current-password" required
                @error('password') aria-invalid="true" aria-describedby="form-errors" @enderror>
            <button type="button" class="pcg-input-group__action" data-pcg-toggle-password="password"
                aria-pressed="false" aria-label="Show password">Show</button>
        </div>
    </div>

    <button type="submit" class="pcg-btn pcg-btn--primary pcg-btn--block">{{ $submitLabel ?? 'Sign in' }}</button>
</form>
