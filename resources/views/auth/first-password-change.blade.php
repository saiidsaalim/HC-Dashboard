<x-guest-layout title="Ganti Password Pertama">
    @if (session('warning'))
        <div class="mb-5 rounded-xl border border-amber-300/40 bg-amber-300/10 px-4 py-3 text-sm font-medium text-amber-200">
            {{ session('warning') }}
        </div>
    @endif

    @include('profile.partials.update-password-form', ['firstPasswordChange' => true])

    <form method="POST" action="{{ route('logout') }}" class="mt-5 border-t border-white/15 pt-5">
        @csrf
        <button type="submit"
            class="w-full rounded-xl border border-white/25 px-5 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-white/40 hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-300 focus:ring-offset-2 focus:ring-offset-slate-900">
            Keluar
        </button>
    </form>
</x-guest-layout>
