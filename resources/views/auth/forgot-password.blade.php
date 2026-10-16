<x-guest-layout title="Lupa Password">
    <div class="mb-7">
        <p class="text-sm font-bold uppercase tracking-[0.22em] text-amber-300">Pemulihan akun</p>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white">Lupa password?</h1>
        <p class="mt-2 text-sm leading-6 text-slate-300">Masukkan email akun Anda. Kami akan mengirimkan tautan untuk membuat password baru.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" surface="dark" />
            <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" surface="dark" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" surface="dark" />
        </div>

        <div class="mt-6 flex items-center justify-end">
            <x-primary-button>
                {{ __('Kirim Tautan Reset') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
