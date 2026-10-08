@extends('layouts.portal')

@section('content')
    <div
        x-data="{
            selectedUnitSlug: @js($units[0]['slug']),
            searchOpen: false,
            searchQuery: '',
            expandedModuleSlug: null,
            openPortalLogin() {
                this.$refs.loginDialog.showModal();
                this.$nextTick(() => this.$refs.loginEmail.focus());
            },
            closePortalLogin() {
                this.$refs.loginDialog.close();
            },
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
                this.$refs.searchInput.blur();
            },
        }"
        x-init="$nextTick(() => { if ($refs.loginDialog.dataset.autoOpen === 'true') { $refs.loginDialog.showModal(); } })">
        <a href="#main-content"
            class="sr-only z-50 rounded-lg bg-amber-400 px-4 py-3 font-semibold text-slate-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:ring-2 focus:ring-sky-700">
            Lewati ke konten
        </a>

        <header class="sticky top-0 z-40 border-b border-white/10 bg-slate-950 text-white">
            <div class="mx-auto flex h-[100px] w-full max-w-[1440px] flex-nowrap items-center justify-between gap-3 px-5 sm:gap-5 sm:px-8 xl:px-0">
                <a href="{{ route('portal') }}" aria-label="SIG - Beranda portal"
                    class="relative flex shrink-0 items-center rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                    <img src="{{ asset('images/logo-sig-header.png') }}" alt="SIG" width="120" height="70"
                        class="h-[70px] w-[120px] object-cover">
                </a>

                <div class="flex min-w-0 shrink-0 flex-nowrap items-center gap-3 sm:gap-5">
                    <img src="{{ asset('images/logo-tonasa.png') }}" alt="Logo Semen Tonasa" width="68"
                        height="68" class="h-[68px] w-[68px] rounded-full object-contain">
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
                                class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-xl bg-white px-5 text-[15px] font-semibold text-[#00314b] transition hover:bg-amber-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                                Keluar
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" @click.prevent="openPortalLogin()"
                            class="inline-flex h-11 items-center justify-center whitespace-nowrap rounded-xl bg-white px-5 text-[15px] font-semibold text-[#00314b] transition hover:bg-amber-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">
                            Masuk ke Portal
                        </a>
                    @endauth
                </div>
            </div>
        </header>

        <main id="main-content">
            <section aria-labelledby="hero-title"
                class="relative isolate flex min-h-[320px] items-center overflow-hidden bg-slate-950 bg-cover bg-center text-white sm:min-h-[56.4vw] sm:bg-[length:100%_auto] sm:bg-top"
                style="background-image: url('{{ asset('images/tonasa-bg.jpg') }}')">
                <div
                    class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/85 via-slate-950/70 to-sky-950/55">
                </div>
                <div class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 sm:py-14 lg:max-w-6xl lg:px-8">
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
                            <a href="#fitur" @click.prevent="scrollTo('fitur')"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-white/60 px-5 text-sm font-semibold text-white transition hover:border-amber-400 hover:bg-amber-400 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:w-auto">
                                Lihat Fitur
                                <x-portal-icon name="arrow" class="h-4 w-4" />
                            </a>
                        @endguest
                        @auth
                            <a href="#pilih-unit" @click.prevent="scrollTo('pilih-unit')"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-white/60 px-5 text-sm font-semibold text-white transition hover:border-amber-400 hover:bg-amber-400 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 sm:w-auto">
                                Pilih Unit
                                <x-portal-icon name="arrow" class="h-4 w-4" />
                            </a>
                        @endauth
                        <div
                            @mouseenter="searchOpen = true"
                            @mouseleave="if (!$refs.searchInput.matches(':focus')) searchOpen = false"
                            @focusin="searchOpen = true"
                            @focusout="$nextTick(() => { if (!$el.contains(document.activeElement) && !$el.matches(':hover')) searchOpen = false; })"
                            class="flex h-11 w-full overflow-hidden rounded-lg border border-white/60 bg-transparent transition-[width,border-color] duration-300 ease-out hover:border-white focus-within:border-amber-400 sm:w-11 sm:hover:w-56 sm:focus-within:w-56">
                            <button type="button"
                                @click="if (!searchOpen) { toggleSearch(); } else { $refs.searchInput.focus(); }"
                                :aria-expanded="searchOpen.toString()"
                                aria-label="{{ auth()->check() ? 'Cari workspace' : config('portal.features.search_label') }}"
                                class="flex h-full w-11 shrink-0 items-center justify-center rounded-md text-white transition-colors hover:bg-white/10 hover:text-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400">
                                <x-portal-icon name="search" class="h-5 w-5" />
                            </button>
                            <label x-cloak x-show="searchOpen" x-transition.opacity class="min-w-0 flex-1 pr-3">
                                <span class="sr-only">{{ config('portal.features.search_input_label') }}</span>
                                <input x-ref="searchInput" x-model="searchQuery" @keydown.escape.prevent="closeSearch()"
                                    type="search"
                                    placeholder="Search Modul"
                                    class="h-full w-full border-0 bg-transparent p-0 text-sm text-white placeholder:text-white/70 focus:outline-none focus:ring-0">
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            @guest
                <section aria-labelledby="about-title"
                    class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 sm:py-14 lg:max-w-6xl lg:px-8">
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
                    <div class="mx-auto w-full max-w-3xl px-4 sm:px-6 lg:max-w-6xl lg:px-8">
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
                    class="scroll-mt-20 mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 sm:py-14 lg:max-w-6xl lg:px-8">
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
                    class="scroll-mt-20 mx-auto w-full max-w-3xl px-4 pb-14 sm:px-6 sm:pb-16 lg:max-w-6xl lg:px-8">
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
            <div class="mx-auto grid w-full max-w-3xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3 md:gap-8 md:py-12 lg:max-w-6xl lg:px-8">
                <section aria-labelledby="footer-org-title">
                    <div class="mb-4">
                        <img src="{{ asset('images/logo-sig-header.png') }}" alt="SIG" width="96" height="56"
                            loading="lazy" class="h-14 w-24 object-cover">
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
                    class="mx-auto flex w-full max-w-3xl flex-col gap-2 px-4 py-5 text-xs leading-5 text-slate-400 sm:px-6 sm:text-sm lg:max-w-6xl lg:px-8">
                    <p>&copy; {{ now()->year }} {{ $footer['copyright'] }}</p>
                    @if ($heroAsset && $heroAsset['attribution'])
                        <p>{{ $footer['photo_credit_label'] }}: {{ $heroAsset['attribution'] }} — {{ $heroAsset['source'] }}</p>
                    @endif
                </div>
            </div>
        </footer>

        @guest
            <x-portal.login-modal />
        @endguest
    </div>
@endsection
