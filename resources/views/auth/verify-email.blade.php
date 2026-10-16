<x-guest-layout title="Verifikasi Email">
    <div class="mb-7">
        <p class="text-sm font-bold uppercase tracking-[0.22em] text-amber-300">Verifikasi akun</p>
        <h1 class="mt-3 font-display text-3xl font-bold tracking-tight text-white">Periksa email Anda</h1>
        <p class="mt-2 text-sm leading-6 text-slate-300">Klik tautan verifikasi yang telah kami kirim. Jika email belum diterima, Anda dapat meminta tautan baru.</p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div role="status" class="mb-5 rounded-xl border border-emerald-300/30 bg-emerald-400/10 px-4 py-3 text-sm font-semibold text-emerald-200">
            {{ __('Tautan verifikasi baru telah dikirim ke alamat email Anda.') }}
        </div>
    @endif

    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Kirim Ulang Verifikasi') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="rounded-md text-sm font-semibold text-slate-300 underline decoration-slate-500 underline-offset-4 transition hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-300">
                {{ __('Logout') }}
            </button>
        </form>
    </div>
</x-guest-layout>
