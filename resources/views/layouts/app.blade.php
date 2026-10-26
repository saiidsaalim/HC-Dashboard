<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>HCM | @yield('page-title', 'Dashboard')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|space-grotesk:500,600,700&display=swap"
        rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php
    $activeWorkspace = collect(config('portal.units'))
        ->flatMap(fn (array $unit) => $unit['workspaces'])
        ->firstWhere('slug', session('active_workspace'));

    if ($activeWorkspace !== null && ! auth()->user()->hasPermission($activeWorkspace['permission'])) {
        $activeWorkspace = null;
    }

    $workspaceHomeRoute = $activeWorkspace['home_route'] ?? 'dashboard';
@endphp

<body data-app-shell x-data="{ sidebarOpen: false, sidebarExpanded: false, isScrolled: false }"
    @scroll.window="isScrolled = (window.pageYOffset > 200)" @keydown.escape.window="sidebarOpen = false"
    :class="{ 'overflow-hidden': sidebarOpen }"
    class="app-shell overflow-x-clip font-sans text-slate-900 antialiased">
    <a href="#main-content"
        class="fixed left-4 top-4 z-[100] -translate-y-24 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-xl transition focus:translate-y-0">
        Lewati ke konten utama
    </a>
    <div class="relative min-h-screen md:flex">
        <aside x-cloak @mouseenter="sidebarExpanded = true" @mouseleave="sidebarExpanded = false"
            :class="{
                '-translate-x-[calc(100%+2rem)]': !sidebarOpen,
                'translate-x-0': sidebarOpen,
                'md:w-16 md:px-2': !sidebarExpanded,
                'md:w-64 md:px-4': sidebarExpanded
            }"
            class="app-sidebar fixed inset-y-0 left-0 z-[60] m-3 flex h-[calc(100dvh-1.5rem)] w-64 max-w-[calc(100vw-1.5rem)] transform flex-col justify-between overflow-hidden rounded-[2rem] bg-slate-950 px-4 py-3 text-slate-300 transition-all duration-300 ease-in-out sm:m-4 sm:h-[calc(100dvh-2rem)] sm:max-w-[calc(100vw-2rem)] md:top-0 md:bottom-auto md:left-0 md:translate-x-0">
            <div class="mb-1 flex shrink-0 items-center gap-1 rounded-2xl border border-slate-800 bg-slate-900 p-2">
                <a href="{{ route($workspaceHomeRoute) }}" title="HC Dashboard"
                    class="flex min-w-0 flex-1 items-center gap-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-500 font-display text-xs font-bold text-white">
                        HC</div>
                    <div x-show="sidebarExpanded || sidebarOpen"
                        x-transition:enter="transition-opacity delay-200 duration-100"
                        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity duration-75"
                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="min-w-0">
                        <p class="truncate font-semibold text-white">HC Dashboard</p>
                        <p class="truncate text-xs text-slate-400">{{ $activeWorkspace['name'] ?? 'Human Capital' }}</p>
                    </div>
                </a>
                <button type="button" @click="sidebarOpen = false" aria-label="Tutup menu"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-amber-400 md:hidden">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
                    </svg>
                </button>
            </div>
            <div class="my-2 border-t border-slate-700"></div>
            <nav aria-label="Navigasi utama" tabindex="0"
                class="min-h-0 flex-1 space-y-0 overflow-x-hidden overflow-y-auto overscroll-contain scrollbar-hidden focus:outline-none">
                <x-sidebar-nav-link :href="route('portal')" label="Kembali ke Portal" icon="arrows" outlined
                    class="mb-2" />
                @php
                    $workspaceNavigation = $activeWorkspace === null
                        ? null
                        : config('portal.workspace_navigation.'.$activeWorkspace['slug']);
                @endphp
                @unless (auth()->user()->must_change_password)
                    @php($navigation = $workspaceNavigation ?? [['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'], ['route' => 'data-pegawai', 'label' => 'Data Pegawai', 'icon' => 'users', 'permission' => 'employees.view'], ['route' => 'wla', 'label' => 'WLA', 'icon' => 'chart', 'permission' => 'wla.view'], ['route' => 'fit-proper', 'label' => 'Fit & Proper', 'icon' => 'clipboard', 'permission' => 'fit-proper.view'], ['route' => 'mutasi', 'label' => 'Mutasi', 'icon' => 'arrows', 'permission' => 'mutations.view'], ['route' => 'promosi', 'label' => 'Promosi', 'icon' => 'chart', 'permission' => 'promotions.view'], ['route' => 'demosi', 'label' => 'Demosi', 'icon' => 'demosi', 'permission' => 'demotions.view'], ['route' => 'formasi', 'label' => 'Formasi', 'icon' => 'formasi', 'permission' => 'formation.view'], ['route' => 'definitif', 'label' => 'Definitif', 'icon' => 'clipboard', 'permission' => 'definitive.view'], ['route' => 'laporan', 'label' => 'Laporan', 'icon' => 'document', 'permission' => 'reports.view']])
                    @foreach ($navigation as $item)
                        @php($canViewStaffWla = $item['route'] === 'wla' && auth()->user()->hasPermission('wla.staff.view'))
                        @if (auth()->user()->hasPermission($item['permission']) || $canViewStaffWla)
                            @php($navigationRoute = $item['route'] === 'wla' && !auth()->user()->hasPermission('wla.view') ? 'wla.staff.index' : $item['route'])
                            @php($navigationLabel = $navigationRoute === 'wla.staff.index' ? 'WLA Saya' : $item['label'])
                            @php($isNavigationActive = request()->routeIs($item['active'] ?? ($item['route'] === 'wla' ? 'wla*' : $item['route'])))
                            <x-sidebar-nav-link :href="route($navigationRoute)" :label="$navigationLabel"
                                :icon="$item['icon']" :active="$isNavigationActive" />
                        @endif
                    @endforeach
                    @if (auth()->user()->hasPermission('organization.view'))
                        <x-sidebar-nav-link :href="route('organization.index')" label="Master Organization" icon="users"
                            :active="request()->routeIs('organization.*')" />
                    @endif
                    @if (auth()->user()->hasPermission('work-schedules.view'))
                        <x-sidebar-nav-link :href="route('work-schedules.index')" label="Work Schedules" icon="clipboard"
                            :active="request()->routeIs('work-schedules.*')" />
                    @endif
                    @if (auth()->user()->hasPermission('work-calendars.view'))
                        <x-sidebar-nav-link :href="route('work-calendars.index')" label="Work Calendars" icon="document"
                            :active="request()->routeIs('work-calendars.*')" />
                    @endif
                @endunless
                @if ($workspaceNavigation === null && auth()->user()->hasPermission('users.view'))
                    <x-sidebar-nav-link :href="route('konfigurasi-user')" label="Konfigurasi User" icon="users"
                        :active="request()->routeIs('konfigurasi-user*')" />
                @endif
            </nav>
        </aside>
        <div x-cloak x-show="sidebarOpen" x-transition:enter="transition-opacity duration-200"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-150" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm md:hidden"></div>
        <main class="min-h-screen min-w-0 flex-1 md:ml-20">
            <header class="app-topbar sticky top-0 z-30 border-b border-slate-200 px-4 py-3 sm:px-6 sm:py-4 lg:px-10">
                <div class="app-content grid min-w-0 grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-3 md:grid-cols-[minmax(0,1fr)_auto] md:gap-x-6">
                    <button type="button" @click="sidebarOpen = true" aria-label="Buka menu"
                        class="col-start-1 row-start-1 inline-flex h-11 w-11 items-center justify-center rounded-lg text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-400 md:hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <a href="{{ route($workspaceHomeRoute) }}"
                        class="col-start-2 row-start-1 w-fit rounded-lg font-display text-lg font-bold text-slate-950 focus:outline-none focus:ring-2 focus:ring-amber-400 md:hidden">
                        HCM
                    </a>
                    <div class="col-span-3 row-start-2 min-w-0 md:col-span-1 md:col-start-1 md:row-start-1">
                        <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-2 text-xs font-semibold text-slate-400">
                            <a href="{{ route($workspaceHomeRoute) }}" class="shrink-0 transition hover:text-sky-700">{{ $activeWorkspace['name'] ?? 'Beranda' }}</a>
                            <span class="shrink-0" aria-hidden="true">/</span>
                            <span aria-current="page" class="truncate text-slate-500">@yield('page-title', 'Dashboard')</span>
                        </nav>
                        <h1 class="mt-1.5 break-words font-display text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">@yield('page-title', 'Dashboard')</h1>
                    </div>
                    <div class="col-start-3 row-start-1 flex shrink-0 items-center gap-3 md:col-start-2">
                        <div class="hidden text-right lg:block">
                            <p class="text-sm font-semibold text-slate-700">{{ now()->translatedFormat('l, d F Y') }}</p>
                            <p class="text-xs text-slate-400">Panel administrasi internal</p>
                        </div>
                        <x-dropdown align="right" width="64" contentClasses="border border-slate-200 bg-white p-2">
                            <x-slot name="trigger">
                                <button type="button" aria-label="Buka menu akun" aria-haspopup="menu"
                                    :aria-expanded="open.toString()"
                                    class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-left shadow-sm transition hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-sky-400">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-950 font-display text-sm font-bold text-white">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                    </span>
                                    <span class="hidden min-w-0 sm:block">
                                        <span class="block max-w-36 truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</span>
                                        <span class="block max-w-36 truncate text-xs text-slate-400">{{ auth()->user()->role ?? auth()->user()->email }}</span>
                                    </span>
                                    <svg class="hidden h-4 w-4 text-slate-400 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="border-b border-slate-100 px-3 py-2 sm:hidden">
                                    <p class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ auth()->user()->email }}</p>
                                </div>
                                <x-dropdown-link :href="route('profile.edit')">Profil akun</x-dropdown-link>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" role="menuitem"
                                        class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-300">
                                        Logout
                                    </button>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
            </header>
            <section id="main-content" data-app-content tabindex="-1"
                class="app-content px-4 py-6 outline-none sm:px-6 sm:py-8 lg:px-10">@yield('content')</section>
        </main>
    </div>
    <button type="button" x-cloak x-show="isScrolled" @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2"
        aria-label="Kembali ke atas" title="Kembali ke atas"
        class="fixed bottom-6 right-6 z-50 rounded-full bg-blue-600 p-3 text-white shadow-lg transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
            stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l7-7m0 0l7 7m-7-7v14" />
        </svg>
    </button>
</body>

</html>
