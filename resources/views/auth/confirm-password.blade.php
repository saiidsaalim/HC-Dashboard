<x-guest-layout title="Konfirmasi Password">
    <div class="mb-7">
        <p class="text-sm font-bold uppercase tracking-[0.22em] text-amber-300">Area aman</p>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white">Konfirmasi password</h1>
        <p class="mt-2 text-sm leading-6 text-slate-300">Masukkan kembali password Anda sebelum melanjutkan ke tindakan sensitif.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" surface="dark" />

            <x-text-input id="password" class="mt-2 block w-full" surface="dark"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" surface="dark" />
        </div>

        <div class="mt-6 flex justify-end">
            <x-primary-button>
                {{ __('Konfirmasi') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
