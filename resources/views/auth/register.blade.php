<x-guest-layout title="Daftar Akun">
    <div class="mb-7">
        <p class="text-sm font-bold uppercase tracking-[0.22em] text-amber-300">Human Capital Management</p>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white">Buat akun</h1>
        <p class="mt-2 text-sm leading-6 text-slate-300">Lengkapi informasi berikut untuk membuat akses baru.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Nama')" surface="dark" />
            <x-text-input id="name" class="mt-2 block w-full" type="text" name="name" :value="old('name')" surface="dark" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" surface="dark" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" surface="dark" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" surface="dark" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" surface="dark" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" surface="dark" />

            <x-text-input id="password" class="mt-2 block w-full" surface="dark"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" surface="dark" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Konfirmasi Password')" surface="dark" />

            <x-text-input id="password_confirmation" class="mt-2 block w-full" surface="dark"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" surface="dark" />
        </div>

        <div class="flex flex-wrap items-center justify-end gap-4 pt-1">
            <a class="rounded-md text-sm font-medium text-slate-300 underline decoration-slate-500 underline-offset-4 transition hover:text-amber-300 focus:outline-none focus:ring-2 focus:ring-amber-300" href="{{ route('login') }}">
                {{ __('Sudah memiliki akun?') }}
            </a>

            <x-primary-button>
                {{ __('Daftar') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
