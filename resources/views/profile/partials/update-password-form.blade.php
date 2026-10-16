@php
    $isFirstPasswordChange = $firstPasswordChange ?? false;
    $formSurface = $isFirstPasswordChange ? 'dark' : 'light';
@endphp

<section id="{{ $isFirstPasswordChange ? 'ganti-password-pertama' : 'ubah-password' }}">
    <header>
        <h2 @class([
            'font-display font-bold',
            'text-3xl tracking-tight text-white' => $isFirstPasswordChange,
            'text-lg text-slate-950' => ! $isFirstPasswordChange,
        ])>
            {{ $isFirstPasswordChange ? __('Ganti Password Pertama') : __('Ubah Password') }}
        </h2>

        <p @class(['mt-2 text-sm', 'text-slate-300' => $isFirstPasswordChange, 'text-slate-500' => ! $isFirstPasswordChange])>
            {{ $isFirstPasswordChange
                ? __('Untuk keamanan akun, ganti password sementara sebelum melanjutkan.')
                : __('Gunakan password yang panjang dan unik untuk menjaga keamanan akun Anda.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-7 space-y-5"
        @if ($isFirstPasswordChange)
            data-confirm-submit
            data-confirm-title="Simpan password baru?"
            data-confirm-text="Pastikan password baru sudah Anda ingat sebelum melanjutkan."
            data-confirm-button-text="Ya, simpan password"
            data-confirm-button-color="#f59e0b"
        @endif>
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password"
                :value="$isFirstPasswordChange ? __('Password Sementara/Password Saat Ini') : __('Password Saat Ini')"
                :surface="$formSurface" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" :surface="$formSurface"
                placeholder="Masukkan password saat ini" autocomplete="current-password" required />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" :surface="$formSurface" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('Password Baru')" :surface="$formSurface" />
            <x-text-input id="update_password_password" name="password" type="password" :surface="$formSurface"
                placeholder="Masukkan password baru" autocomplete="new-password" required />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" :surface="$formSurface" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Konfirmasi Password Baru')" :surface="$formSurface" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" :surface="$formSurface"
                placeholder="Ulangi password baru" autocomplete="new-password" required />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" :surface="$formSurface" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button :surface="$formSurface">
                {{ $isFirstPasswordChange ? __('SIMPAN PASSWORD') : __('Simpan Password') }}
            </x-primary-button>

            @if (! $isFirstPasswordChange && session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm font-medium text-emerald-700"
                >{{ __('Password berhasil diperbarui.') }}</p>
            @endif
        </div>
    </form>
</section>
