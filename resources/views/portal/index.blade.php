@extends('layouts.portal')

@section('content')
    <div
        x-data="{
            selectedUnitSlug: @js($units[0]['slug']),
            searchOpen: false,
            searchQuery: '',
            expandedModuleSlug: null,
            unitMatches(name) {
                return name.toLocaleLowerCase().includes(this.searchQuery.trim().toLocaleLowerCase());
            },
            selectUnit(slug) {
                this.selectedUnitSlug = slug;
                this.scrollTo('workspace');
            },
            scrollTo(id) {
                const target = document.getElementById(id);

                if (target) {
                    target.scrollIntoView({
                        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                        block: 'start',
                    });
                }
            },
            toggleSearch() {
                this.searchOpen = !this.searchOpen;
                this.searchQuery = '';

                if (this.searchOpen) {
                    this.$nextTick(() => this.$refs.searchInput.focus());
                }
            },
            closeSearch() {
                this.searchOpen = false;
                this.searchQuery = '';
            },
        }">
        <a href="#main-content"
            class="sr-only z-50 rounded-lg bg-amber-400 px-4 py-3 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:ring-2 focus:ring-sky-700">
            Lewati ke konten
        </a>

        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950 text-white">
            <div class="mx-auto flex h-14 w-full max-w-6xl flex-nowrap items-center justify-between gap-2 px-4 sm:h-[70px] sm:gap-4 sm:px-6 lg:px-8">
                <a href="{{ route('portal') }}" aria-label="SIG - Beranda portal"
                    class="flex shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                    @if (file_exists(public_path('image/portal/sig-logo.png')))
                        <img src="{{ asset('image/portal/sig-logo.png') }}" alt="SIG" width="116" height="42"
                            class="h-7 w-auto max-w-[100px] object-contain sm:h-9 sm:max-w-[116px]">
                    @else
                        <span class="font-display text-xl font-bold tracking-tight text-white sm:text-2xl">SIG</span>
                    @endif
                </a>

                <div class="flex min-w-0 shrink-0 flex-nowrap items-center gap-2 sm:gap-4">
                    @if (file_exists(public_path('image/portal/semen-tonasa.png')))
                        <img src="{{ asset('image/portal/semen-tonasa.png') }}" alt="Logo Semen Tonasa" width="44"
                            height="44" class="h-8 w-8 rounded-full bg-white object-contain p-1 sm:h-10 sm:w-10">
                    @else
                        <span aria-label="Semen Tonasa"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-600 bg-slate-900 font-display text-[10px] font-bold text-white sm:h-10 sm:w-10 sm:text-xs">ST</span>
                    @endif
                    @auth
                        <span class="hidden max-w-40 truncate text-sm font-semibold text-white sm:block">
                            {{ auth()->user()->name }}
                        </span>
                        <span aria-hidden="true"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-700 font-display text-sm font-bold text-white sm:hidden">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                            @csrf
                            <button type="submit"
                                class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg bg-white px-3 text-xs font-semibold text-slate-950 transition hover:bg-amber-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 sm:px-4 sm:text-sm">
                                Keluar
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}"
                            class="inline-flex min-h-11 items-center justify-center whitespace-nowrap rounded-lg bg-white px-3 text-xs font-semibold text-slate-950 transition hover:bg-amber-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 sm:px-4 sm:text-sm">
                            Masuk ke Portal
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main id="main-content">
            <section aria-labelledby="hero-title"
                class="relative isolate flex min-h-[320px] items-center overflow-hidden bg-gradient-to-br from-slate-950 via-slate-950 to-sky-950 text-white sm:min-h-[380px] lg:min-h-[440px]">
                @if ($heroAsset && file_exists(public_path('image/portal/'.$heroAsset['path'])))
                    <img src="{{ asset('image/portal/'.$heroAsset['path']) }}" alt="Gedung perkantoran modern"
                        width="1920" height="1080" fetchpriority="high"
                        class="absolute inset-0 -z-20 h-full w-full object-cover">
                @endif
                <div
                    class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/95 via-slate-950/80 to-sky-950/60">
                </div>
                <div class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 sm:py-14 lg:px-8">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-[0.2em] text-sky-300">
                        DEPT. OF HUMAN CAPITAL &amp; GRC
                    </p>
                    <h1 id="hero-title" class="font-display text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
                        Platform terpadu
                    </h1>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-slate-200 sm:text-base sm:leading-7">
                        untuk mengintegrasikan alur kerja, layanan informasi, serta tata kelola dan pengembangan talenta
                        berkelanjutan
                    </p>
                    <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        @guest
                            <a href="{{ route('login') }}"
                                class="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-amber-400 px-5 text-sm font-bold text-slate-950 transition hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto">
                                Masuk ke Portal
                            </a>
                            <a href="#fitur" @click.prevent="scrollTo('fitur')"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-white/60 px-5 text-sm font-semibold text-white transition hover:border-amber-400 hover:bg-amber-400 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:w-auto">
                                Lihat Fitur
                                <x-portal-icon name="arrow" class="h-4 w-4" />
                            </a>
                            <button type="button" @click="toggleSearch()" :aria-expanded="searchOpen.toString()"
                                aria-label="{{ config('portal.features.search_label') }}"
                                class="flex h-11 w-full items-center justify-center rounded-lg border border-white/60 text-white transition hover:border-amber-400 hover:bg-amber-400 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:w-11 sm:rounded-full">
                                <x-portal-icon name="search" class="h-5 w-5" />
                            </button>
                        @endguest
                        @auth
                            <a href="#pilih-unit" @click.prevent="scrollTo('pilih-unit')"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-white/60 px-5 text-sm font-semibold text-white transition hover:border-amber-400 hover:bg-amber-400 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:w-auto">
                                Pilih Unit
                                <x-portal-icon name="arrow" class="h-4 w-4" />
                            </a>
                            <button type="button" @click="toggleSearch()" :aria-expanded="searchOpen.toString()"
                                aria-label="Cari workspace"
                                class="flex h-11 w-full items-center justify-center rounded-lg border border-white/60 text-white transition hover:border-amber-400 hover:bg-amber-400 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:w-11 sm:rounded-full">
                                <x-portal-icon name="search" class="h-5 w-5" />
                            </button>
                        @endauth
                        <label x-cloak x-show="searchOpen" x-transition.opacity class="w-full min-w-0 sm:max-w-xs">
                            <span class="sr-only">{{ config('portal.features.search_input_label') }}</span>
                            <input x-ref="searchInput" x-model="searchQuery" @keydown.escape="closeSearch()"
                                type="search"
                                placeholder="{{ auth()->check() ? 'Cari workspace...' : config('portal.features.search_placeholder') }}"
                                class="min-h-11 w-full rounded-lg border-white/30 bg-white/10 text-sm text-white placeholder:text-slate-300 focus:border-amber-400 focus:ring-amber-400">
                        </label>
                    </div>
                </div>
            </section>

            @guest
                <section aria-labelledby="about-title"
                    class="mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 sm:py-14 lg:px-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">
                        {{ config('portal.about.eyebrow') }}
                    </p>
                    <h2 id="about-title" class="mt-2 font-display text-2xl font-bold text-slate-950 sm:text-3xl">
                        {{ config('portal.about.title') }}
                    </h2>
                    <p class="mt-4 max-w-3xl text-sm leading-6 text-slate-700 sm:text-base sm:leading-7">
                        {{ config('portal.about.description') }}
                    </p>

                    <h3 class="mt-8 font-display text-lg font-semibold text-slate-950">
                        {{ config('portal.about.steps_title') }}
                    </h3>
                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        @foreach (config('portal.about.steps') as $step)
                            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <span
                                    class="mb-3 flex h-8 w-8 items-center justify-center rounded-full bg-amber-400 font-display text-sm font-bold text-slate-950">
                                    {{ $loop->iteration }}
                                </span>
                                <p class="font-display text-lg font-semibold text-slate-950">{{ $step['title'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ $step['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section id="fitur" aria-labelledby="features-title"
                    class="scroll-mt-20 bg-white py-12 sm:py-14">
                    <div class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">
                            {{ config('portal.features.eyebrow') }}
                        </p>
                        <h2 id="features-title" class="mt-2 font-display text-2xl font-bold text-slate-950 sm:text-3xl">
                            {{ config('portal.features.title') }}
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                            {{ config('portal.features.description') }}
                        </p>
                        <div class="mt-7 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($modules as $module)
                                <article x-show="unitMatches(@js($module['name']))"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm transition hover:border-sky-300">
                                    <button type="button"
                                        @click="expandedModuleSlug = expandedModuleSlug === @js($module['slug']) ? null : @js($module['slug'])"
                                        :aria-expanded="(expandedModuleSlug === @js($module['slug'])).toString()"
                                        aria-controls="module-details-{{ $module['slug'] }}"
                                        class="flex min-h-11 w-full items-start gap-4 rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700">
                                        <span
                                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-700 text-white">
                                            <x-portal-icon :name="$module['icon']" class="h-6 w-6" />
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="font-display text-base font-semibold text-slate-950">
                                                    {{ $module['name'] }}
                                                </span>
                                                @if ($module['coming_soon'] ?? false)
                                                    <span
                                                        class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-semibold text-amber-900">
                                                        {{ config('portal.features.coming_soon_label') }}
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="mt-2 block text-sm leading-5 text-slate-600">
                                                {{ $module['description'] }}
                                            </span>
                                        </span>
                                        <span aria-hidden="true" class="shrink-0 text-slate-500">
                                            <x-portal-icon name="arrow" class="mt-1 h-4 w-4" />
                                        </span>
                                    </button>
                                    <p id="module-details-{{ $module['slug'] }}" x-cloak
                                        x-show="expandedModuleSlug === @js($module['slug'])" x-transition.opacity
                                        class="ml-16 mt-3 border-t border-slate-200 pt-3 text-sm leading-6 text-slate-700">
                                        {{ $module['detail'] }}
                                    </p>
                                </article>
                            @endforeach
                        </div>
                        <p x-show="!@js($modules).some((module) => unitMatches(module.name))" x-cloak role="status"
                            class="py-10 text-center text-sm font-medium text-slate-600">
                            {{ config('portal.features.empty_search') }}
                        </p>
                    </div>
                </section>
            @endguest

            @auth
                <section id="pilih-unit" aria-labelledby="units-title"
                    class="scroll-mt-20 mx-auto w-full max-w-6xl px-4 py-12 sm:px-6 sm:py-14 lg:px-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">
                        {{ config('portal.authenticated.units_label') }}
                    </p>
                    <h2 id="units-title" class="mt-2 font-display text-2xl font-bold text-slate-950 sm:text-3xl">
                        {{ config('portal.authenticated.units_title') }}
                    </h2>
                    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($units as $unit)
                            <button type="button" @click="selectUnit(@js($unit['slug']))"
                                :aria-pressed="(selectedUnitSlug === @js($unit['slug'])).toString()"
                                :class="selectedUnitSlug === @js($unit['slug']) ? 'border-2 border-sky-700 bg-sky-50' : 'border border-slate-200 bg-white'"
                                class="w-full max-w-sm rounded-2xl p-5 text-left shadow-sm transition hover:border-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700">
                                <span
                                    class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    {{ $unit['status'] }}
                                </span>
                                <span class="mt-4 block break-words font-display text-lg font-semibold leading-snug text-slate-950">
                                    {{ $unit['name'] }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>

                <section id="workspace" aria-labelledby="workspace-title"
                    class="scroll-mt-20 mx-auto w-full max-w-6xl px-4 pb-14 sm:px-6 sm:pb-16 lg:px-8">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">
                        {{ config('portal.authenticated.workspace_label') }}
                    </p>
                    <h2 id="workspace-title" class="mt-2 font-display text-2xl font-bold text-slate-950 sm:text-3xl">
                        {{ config('portal.authenticated.workspace_title') }}
                    </h2>
                    <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                        @foreach ($units as $unit)
                            <div x-show="selectedUnitSlug === @js($unit['slug'])">
                                <h3
                                    class="border-b border-slate-200 pb-4 text-xs font-bold uppercase tracking-[0.14em] text-slate-600">
                                    {{ $unit['workspace_heading'] }}
                                </h3>
                                <div class="grid grid-cols-2 gap-2 pt-5 sm:gap-6 sm:pt-6">
                                    @foreach ($unit['workspaces'] as $workspace)
                                        <a href="{{ route('portal.workspace', $workspace['slug']) }}"
                                            x-show="unitMatches(@js($workspace['name']))"
                                            class="group flex min-h-40 min-w-0 flex-col items-center justify-center rounded-2xl p-2 text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 sm:min-h-44 sm:p-4">
                                            <span
                                                class="{{ $workspace['tone'] === 'blue' ? 'bg-sky-700 group-hover:bg-sky-800' : 'bg-emerald-600 group-hover:bg-emerald-700' }} flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl text-white shadow-md transition sm:h-20 sm:w-20">
                                                <x-portal-icon :name="$workspace['icon']" class="h-8 w-8 sm:h-10 sm:w-10" />
                                            </span>
                                            <span
                                                class="mt-3 max-w-full break-words text-center text-sm font-semibold leading-5 text-slate-800 group-hover:text-sky-800 sm:mt-4 sm:text-base">
                                                {{ $workspace['name'] }}
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                                <p x-show="!@js($unit['workspaces']).some((workspace) => unitMatches(workspace.name))"
                                    x-cloak role="status" class="py-10 text-center text-sm font-medium text-slate-600">
                                    {{ config('portal.authenticated.empty_search') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endauth
        </main>

        <footer class="bg-slate-950 pb-[env(safe-area-inset-bottom)] text-slate-200">
            <div class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3 md:gap-8 md:py-12 lg:px-8">
                <section aria-labelledby="footer-org-title">
                    <div class="mb-4">
                        @if (file_exists(public_path('image/portal/sig-logo.png')))
                            <img src="{{ asset('image/portal/sig-logo.png') }}" alt="SIG" width="116" height="42"
                                loading="lazy" class="h-9 w-auto max-w-[116px] object-contain">
                        @else
                            <span class="font-display text-2xl font-bold tracking-tight text-white">SIG</span>
                        @endif
                    </div>
                    <h2 id="footer-org-title" class="font-display text-base font-semibold text-white">
                        {{ $footer['organization'] }}
                    </h2>
                    <p class="mt-2 max-w-sm text-sm leading-6 text-slate-300">{{ $footer['description'] }}</p>
                </section>

                <nav aria-labelledby="footer-navigation-title">
                    <h2 id="footer-navigation-title" class="font-display text-base font-semibold text-white">
                        {{ $footer['navigation_title'] }}
                    </h2>
                    <ul class="mt-3 space-y-1 text-sm">
                        @auth
                            @foreach ($footer['authenticated_links'] as $link)
                                <li>
                                    <a href="#{{ $link['anchor'] }}"
                                        class="inline-flex min-h-11 items-center text-slate-300 transition hover:text-amber-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                        {{ $link['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        @else
                            @foreach ($footer['guest_links'] as $link)
                                <li>
                                    <a href="{{ isset($link['route']) ? route($link['route']) : '#'.$link['anchor'] }}"
                                        class="inline-flex min-h-11 items-center text-slate-300 transition hover:text-amber-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                        {{ $link['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        @endauth
                    </ul>
                </nav>

                @auth
                    <section aria-labelledby="footer-unit-title">
                        <h2 id="footer-unit-title" class="font-display text-base font-semibold text-white">
                            {{ $footer['unit_title'] }}
                        </h2>
                        <p class="mt-3 text-sm text-slate-300">{{ $footer['active_unit_name'] }}</p>
                    </section>
                @else
                    <section aria-labelledby="footer-unit-title">
                        <h2 id="footer-unit-title" class="font-display text-base font-semibold text-white">
                            {{ $footer['guest_unit_title'] }}
                        </h2>
                        <p class="mt-3 text-sm leading-6 text-slate-300">{{ $footer['guest_unit_description'] }}</p>
                    </section>
                @endauth
            </div>
            <div class="border-t border-slate-800">
                <div
                    class="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-5 text-xs leading-5 text-slate-400 sm:px-6 sm:text-sm lg:px-8">
                    <p>&copy; {{ now()->year }} {{ $footer['copyright'] }}</p>
                    @if ($heroAsset && $heroAsset['attribution'])
                        <p>{{ $footer['photo_credit_label'] }}: {{ $heroAsset['attribution'] }} — {{ $heroAsset['source'] }}</p>
                    @endif
                </div>
            </div>
        </footer>
    </div>
@endsection
