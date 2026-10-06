<dialog
    class="portal-login-dialog"
    data-login-dialog
    data-auto-open="{{ $errors->any() ? 'true' : 'false' }}"
    aria-labelledby="portal-login-title"
>
    <div class="portal-login-dialog__content">
        <button class="portal-login-dialog__close" type="button" data-close-login aria-label="Tutup popup login">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="m5 5 10 10M15 5 5 15" />
            </svg>
        </button>

        <p class="portal-eyebrow">Dept. of Human Capital &amp; GRC</p>
        <h2 id="portal-login-title">Masuk ke Portal</h2>
        <p class="portal-login-dialog__description">Silakan login untuk mengakses sistem.</p>
        <p class="portal-login-dialog__workspace" data-current-workspace hidden></p>

        <form class="portal-login-form" method="POST" action="{{ route('login') }}">
            @csrf

            <div class="portal-field">
                <label for="portal-email">Email</label>
                <input
                    id="portal-email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    @if ($errors->has('email')) aria-invalid="true" aria-describedby="portal-email-error" @endif
                >
                @error('email')
                    <p class="portal-field__error" id="portal-email-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="portal-field">
                <label for="portal-password">Password</label>
                <input
                    id="portal-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    @if ($errors->has('password')) aria-invalid="true" aria-describedby="portal-password-error" @endif
                >
                @error('password')
                    <p class="portal-field__error" id="portal-password-error">{{ $message }}</p>
                @enderror
            </div>

            <label class="portal-remember" for="portal-remember">
                <input id="portal-remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <span>Stay logged in</span>
            </label>

            <button class="portal-button portal-button--primary portal-login-form__submit" type="submit">
                Masuk
            </button>
        </form>
    </div>
</dialog>
