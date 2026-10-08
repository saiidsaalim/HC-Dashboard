<x-guest-layout>
    <div class="mb-8">
        <p class="text-sm font-bold uppercase tracking-[0.25em] text-amber-300">Human Capital Management</p>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl">Selamat datang kembali</h1>
        <p class="mt-2 text-base text-slate-300">Masuk untuk melanjutkan ke dashboard Anda.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-2 block h-[52px] w-full rounded-2xl border border-white/25 bg-white/10 px-4 text-white shadow-none focus:border-amber-300 focus:ring-amber-300" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-5">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="mt-2 block h-[52px] w-full rounded-2xl border border-white/25 bg-white/10 px-4 text-white shadow-none focus:border-amber-300 focus:ring-amber-300"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="mt-5">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="h-5 w-5 rounded-md border-white/30 bg-white/10 text-amber-400 shadow-sm focus:ring-amber-400" name="remember" value="1" @checked(old('remember'))>
                <span class="ms-2.5 text-base text-slate-300">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="mt-7 flex flex-wrap items-center justify-end gap-x-4 gap-y-3">
            @if (Route::has('password.request'))
                <a class="text-base text-slate-300 underline decoration-slate-500 underline-offset-4 transition hover:text-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-400" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="h-12 min-w-28 justify-center rounded-2xl px-6 text-sm">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
