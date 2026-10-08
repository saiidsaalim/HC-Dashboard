<dialog
    x-ref="loginDialog"
    @click.self="closePortalLogin()"
    class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[min(480px,calc(100vw-2rem))] max-w-none overflow-y-auto rounded-[2rem] border border-white/25 bg-transparent p-0 text-white shadow-2xl shadow-slate-950/60 backdrop:bg-slate-950/70 backdrop:backdrop-blur-sm"
    data-login-dialog
    data-auto-open="{{ $errors->any() ? 'true' : 'false' }}"
    aria-labelledby="portal-login-title"
>
    <div class="relative bg-[linear-gradient(135deg,rgba(30,27,75,0.98),rgba(8,47,73,0.97))] px-6 py-6 backdrop-blur-2xl sm:px-8 sm:py-7">
        <button class="absolute right-4 top-4 grid h-9 w-9 place-items-center rounded-full text-slate-300 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-400" type="button" @click="closePortalLogin()" aria-label="Tutup popup login">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-linecap="round" stroke-width="1.8" />
            </svg>
        </button>

        <div class="mb-5 flex justify-center">
            <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-white/20 bg-white/10 p-2.5 shadow-lg shadow-slate-950/30">
                <img src="{{ asset('image/SM.png') }}" alt="Semen Tonasa" class="h-full w-full object-contain">
            </div>
        </div>

        <p class="text-xs font-bold uppercase tracking-[0.22em] text-amber-300">Human Capital Management</p>
        <h2 id="portal-login-title" class="mt-2 font-display text-2xl font-bold tracking-tight text-white sm:text-3xl">Selamat datang kembali</h2>
        <p class="mt-2 text-sm text-slate-300">Masuk untuk melanjutkan ke dashboard Anda.</p>

        <x-auth-session-status class="mt-5 text-sm text-emerald-300" :status="session('status')" />
        <p class="mt-4 hidden rounded-xl border border-white/10 bg-white/10 px-4 py-3 text-sm text-slate-200" data-current-workspace></p>

        <form class="mt-5 space-y-4 text-left" method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <label for="portal-email" class="block text-sm font-semibold text-slate-200">Email</label>
                <input
                    id="portal-email"
                    x-ref="loginEmail"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    autofocus
                    class="mt-2 block h-11 w-full rounded-xl border border-white/25 bg-white/10 px-4 text-white shadow-none placeholder:text-slate-400 focus:border-amber-300 focus:outline-none focus:ring-amber-300"
                    @if ($errors->has('email')) aria-invalid="true" aria-describedby="portal-email-error" @endif
                >
                @error('email')
                    <p class="mt-2 text-sm text-rose-300" id="portal-email-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="portal-password" class="block text-sm font-semibold text-slate-200">Password</label>
                <input
                    id="portal-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="mt-2 block h-11 w-full rounded-xl border border-white/25 bg-white/10 px-4 text-white shadow-none placeholder:text-slate-400 focus:border-amber-300 focus:outline-none focus:ring-amber-300"
                    @if ($errors->has('password')) aria-invalid="true" aria-describedby="portal-password-error" @endif
                >
                @error('password')
                    <p class="mt-2 text-sm text-rose-300" id="portal-password-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                <label class="inline-flex items-center gap-2.5 text-sm text-slate-300" for="portal-remember">
                    <input id="portal-remember" name="remember" type="checkbox" value="1" @checked(old('remember')) class="h-5 w-5 rounded-md border-white/30 bg-white/10 text-amber-400 focus:ring-amber-400">
                    <span>Remember me</span>
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-3">
                @if (Route::has('password.request'))
                    <a class="text-sm text-slate-300 underline decoration-slate-500 underline-offset-4 transition hover:text-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400" href="{{ route('password.request') }}">
                        Forgot your password?
                    </a>
                @endif
                <button class="inline-flex h-12 items-center justify-center rounded-2xl bg-amber-400 px-6 text-sm font-bold uppercase tracking-widest text-slate-950 shadow-lg shadow-amber-950/20 transition hover:bg-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2 focus:ring-offset-slate-900 active:bg-amber-500" type="submit">
                    Log in
                </button>
            </div>
        </form>
    </div>
</dialog>
