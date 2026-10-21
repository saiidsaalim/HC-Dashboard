@extends('layouts.portal')

@section('content')
    @php
        $workspaceCount = collect($units)->sum(
            fn (array $unit): int => count($unit['workspaces']),
        );
    @endphp

    <div class="bg-slate-50">
        <a href="#main-content"
            class="sr-only z-50 rounded-lg bg-amber-400 px-4 py-3 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:ring-2 focus:ring-sky-700">
            Lewati ke konten
        </a>

        <div data-portal-hero class="relative isolate min-h-screen overflow-hidden bg-slate-950 text-white">
            <img src="{{ asset('images/tonasa-bg.jpg') }}" alt="" aria-hidden="true"
                class="absolute inset-0 -z-20 h-full w-full object-cover object-center">
            <div aria-hidden="true"
                class="absolute inset-0 -z-10 bg-gradient-to-br from-slate-950/95 via-slate-950/75 to-sky-950/55">
            </div>

            <header data-portal-header class="relative z-20 border-b border-white/15 text-white">
                <div
                    class="mx-auto flex h-20 w-full max-w-[1440px] flex-nowrap items-center gap-3 px-4 sm:h-24 sm:gap-8 sm:px-8 xl:px-10">
                    <a href="{{ route('portal') }}" aria-label="SIG - Portal Human Capital"
                        class="relative flex shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                        <img data-portal-logo src="{{ asset('images/logo-sig-white.png') }}" alt="SIG" width="72"
                            height="72" class="h-12 w-12 object-contain brightness-0 invert sm:h-16 sm:w-16">
                    </a>

                    <nav aria-label="Navigasi portal"
                        class="hidden items-center gap-7 text-sm font-semibold text-slate-200 md:flex">
                        <a href="#workspace"
                            class="rounded-lg py-2 transition duration-200 hover:text-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                            Workspace
                        </a>
                        <a href="{{ route('profile.edit') }}"
                            class="rounded-lg py-2 transition duration-200 hover:text-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                            Profil
                        </a>
                    </nav>

                    <div class="ml-auto flex min-w-0 shrink-0 flex-nowrap items-center gap-3 sm:gap-5">
                        <img src="{{ asset('images/logo-tonasa.png') }}" alt="Logo Semen Tonasa" width="56"
                            height="56" class="hidden h-12 w-12 rounded-full object-contain sm:block">
                        <span class="hidden max-w-40 truncate text-sm font-semibold text-slate-200 lg:block">
                            {{ auth()->user()->name }}
                        </span>
                        <span aria-hidden="true"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-700 font-display text-sm font-bold text-white lg:hidden">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                            @csrf
                            <button type="submit"
                                class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-lg bg-white px-4 text-xs font-semibold text-slate-950 shadow-sm transition duration-200 hover:bg-amber-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 sm:h-11 sm:px-5 sm:text-sm">
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main id="main-content" class="relative z-10">
                <section aria-labelledby="portal-title"
                    class="mx-auto flex min-h-[calc(100svh-5rem)] w-full max-w-[1440px] sm:min-h-[calc(100svh-6rem)]">
                    <div class="flex w-full flex-col justify-center px-5 py-10 sm:px-8 sm:py-12 lg:px-12 xl:px-14">
                        <div class="max-w-3xl">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-300">
                                {{ config('portal.authenticated.eyebrow') }}
                            </p>
                            <h1 id="portal-title"
                                class="mt-3 font-display text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                                {{ config('portal.authenticated.title') }}
                            </h1>
                            <p class="mt-4 max-w-2xl text-sm leading-6 text-slate-200 sm:text-base sm:leading-7">
                                {{ config('portal.authenticated.description') }}
                            </p>
                        </div>

                        <div id="workspace" class="scroll-mt-28 pt-8" aria-labelledby="workspace-title">
                            <div class="text-center">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-300">
                                    {{ config('portal.authenticated.workspace_label') }}
                                </p>
                                <h2 id="workspace-title"
                                    class="mt-1 font-display text-xl font-bold text-white sm:text-2xl">
                                    Pilih layanan untuk mulai bekerja
                                </h2>
                            </div>

                            @if ($workspaceCount > 0)
                                <div data-workspace-list data-workspace-count="{{ $workspaceCount }}"
                                    class="mt-7 flex flex-wrap justify-center gap-5 lg:gap-6">
                                    @foreach ($units as $unit)
                                        @foreach ($unit['workspaces'] as $workspace)
                                            @php
                                                $workspaceIconTone = match ($workspace['tone']) {
                                                    'green' => 'bg-emerald-700',
                                                    'amber' => 'bg-amber-600',
                                                    default => 'bg-sky-700',
                                                };
                                            @endphp

                                            <article data-workspace-card data-workspace-slug="{{ $workspace['slug'] }}"
                                                class="flex w-full max-w-[22rem] flex-col rounded-3xl border border-white/60 bg-white/95 p-6 text-left text-slate-900 shadow-xl shadow-slate-950/20 backdrop-blur-sm">
                                                <span
                                                    class="{{ $workspaceIconTone }} inline-flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-lg">
                                                    <x-portal-icon :name="$workspace['icon']" class="h-7 w-7" />
                                                </span>

                                                <p class="mt-6 text-xs font-extrabold uppercase tracking-[0.12em] text-sky-700">
                                                    {{ $unit['name'] }}
                                                </p>
                                                <h3 class="mt-2 font-display text-2xl font-bold leading-tight text-slate-900">
                                                    {{ $workspace['name'] }}
                                                </h3>
                                                <p class="mt-3 flex-1 text-sm leading-6 text-slate-600">
                                                    {{ $workspace['description'] }}
                                                </p>

                                                <a href="{{ route('portal.workspace', $workspace['slug']) }}"
                                                    data-enter-workspace
                                                    class="mt-6 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-slate-900 px-5 text-sm font-bold text-white transition hover:bg-sky-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2">
                                                    <span>Masuk Workspace</span>
                                                    <x-portal-icon name="arrow" class="h-4 w-4" />
                                                </a>
                                            </article>
                                        @endforeach
                                    @endforeach
                                </div>
                            @else
                                <div
                                    class="mt-6 rounded-2xl border border-amber-300/50 bg-slate-950/75 px-6 py-8 text-center text-slate-100 backdrop-blur-sm">
                                    <p class="font-display text-lg font-semibold">Belum ada workspace yang dapat diakses.</p>
                                    <p class="mt-2 text-sm leading-6 text-slate-300">
                                        Hubungi administrator untuk memeriksa hak akses workspace akun Anda.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
            </main>
        </div>

        <section data-portal-details aria-labelledby="portal-details-title" class="bg-slate-50 px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
            <div class="mx-auto w-full max-w-6xl">
                <div class="mx-auto max-w-3xl text-center">
                    <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-sky-700">Pendukung aktivitas</p>
                    <h2 id="portal-details-title"
                        class="mt-3 font-display text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                        Satu portal untuk pekerjaan Human Capital
                    </h2>
                    <p class="mt-4 text-sm leading-6 text-slate-600 sm:text-base sm:leading-7">
                        Setiap workspace dirancang untuk membantu pekerjaan lebih terarah, mudah diakses, dan tetap
                        mengikuti kewenangan pengguna.
                    </p>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-3">
                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-sky-100 text-sky-800">
                            <x-portal-icon name="users" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 font-display text-lg font-bold text-slate-950">Akses sesuai peran</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Workspace yang tersedia menyesuaikan hak akses dan tanggung jawab setiap pengguna.
                        </p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <span
                            class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800">
                            <x-portal-icon name="target" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 font-display text-lg font-bold text-slate-950">Alur kerja terarah</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Masuk langsung ke layanan yang dibutuhkan tanpa langkah tambahan yang tidak perlu.
                        </p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <span
                            class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-800">
                            <x-portal-icon name="learning" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 font-display text-lg font-bold text-slate-950">Pengembangan berkelanjutan</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            Informasi organisasi, performa, talenta, dan pembelajaran tersusun dalam satu portal.
                        </p>
                    </article>
                </div>

                <div class="mt-8 rounded-2xl bg-slate-900 px-6 py-7 text-center text-slate-100 sm:px-8">
                    <p class="font-display text-lg font-bold">Workspace Anda belum tersedia?</p>
                    <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                        Hubungi administrator Human Capital untuk memastikan peran dan hak akses akun telah sesuai.
                    </p>
                </div>
            </div>
        </section>

        <footer class="bg-slate-950 pb-[env(safe-area-inset-bottom)] text-slate-200">
            <div
                class="mx-auto flex w-full max-w-6xl flex-col gap-4 px-4 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <div>
                    <p class="font-display font-semibold text-white">{{ $footer['organization'] }}</p>
                    <p class="mt-1 text-slate-400">{{ $footer['description'] }}</p>
                </div>
                <p class="text-xs text-slate-400 sm:text-sm">&copy; {{ now()->year }} {{ $footer['copyright'] }}</p>
            </div>
        </footer>
    </div>
@endsection
