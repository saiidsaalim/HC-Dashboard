<section class="space-y-6">
    <header>
        <h2 class="font-display text-lg font-bold text-slate-950">
            {{ __('Hapus Akun') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-slate-500">
            {{ __('Tindakan ini menghapus akun dan data terkait secara permanen. Pastikan informasi penting sudah diamankan terlebih dahulu.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Hapus Akun') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable
        aria-labelledby="confirm-user-deletion-title">
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 id="confirm-user-deletion-title" class="font-display text-lg font-bold text-slate-950">
                {{ __('Yakin ingin menghapus akun?') }}
            </h2>

            <p class="mt-2 text-sm leading-6 text-slate-500">
                {{ __('Setelah dihapus, akun tidak dapat dipulihkan. Masukkan password untuk mengonfirmasi tindakan ini.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full"
                    placeholder="{{ __('Password') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Batal') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Hapus Akun') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
