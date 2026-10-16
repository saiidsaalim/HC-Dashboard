<x-guest-layout title="Reset Password">
    <div class="mb-7">
        <p class="text-sm font-bold uppercase tracking-[0.22em] text-amber-300">Keamanan akun</p>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white">Buat password baru</h1>
        <p class="mt-2 text-sm leading-6 text-slate-300">Gunakan password yang kuat, unik, dan mudah Anda ingat.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" surface="dark" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email', $request->email)" surface="dark" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" surface="dark" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password Baru')" surface="dark" />
            <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" surface="dark" required autocomplete="new-password" />
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

        <div class="flex items-center justify-end pt-1">
            <x-primary-button>
                {{ __('Simpan Password Baru') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
