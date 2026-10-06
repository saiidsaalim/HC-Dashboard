{{-- Popup login. Form dikirim ke proses login YANG SUDAH ADA (route 'login', method POST).
     Sesuaikan nama field (email/password/remember) bila berbeda di aplikasi Anda. --}}
<div class="p-modal" id="login-modal" role="dialog" aria-modal="true" aria-labelledby="login-title"
     @if ($errors->any()) data-auto-open @endif>
    <div class="p-box">
        <button type="button" class="p-x" data-close aria-label="Tutup">&times;</button>
        <img class="p-mlogo" src="{{ asset('images/logo-tonasa.png') }}" alt="Logo PT Semen Tonasa">
        <h3 id="login-title">Selamat Datang!</h3>
        <p class="p-sub">Silakan login untuk mengakses sistem</p>

        @if ($errors->any())
            <div class="p-msg p-err" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="p-fld">
                <i><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg></i>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" autocomplete="username" required>
            </div>
            <div class="p-fld">
                <i><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg></i>
                <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
            </div>
            <div class="p-row">
                <label><input type="checkbox" name="remember" value="1"> Stay logged in</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}">I've forgotten my password</a>
                @endif
            </div>
            <button class="p-go" type="submit">SIGN IN &rsaquo;</button>
        </form>
    </div>
</div>
