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

<body x-data="{ sidebarOpen: false, sidebarExpanded: false, isScrolled: false }" @scroll.window="isScrolled = (window.pageYOffset > 200)"
    class="overflow-x-clip bg-slate-100 font-sans text-slate-900 antialiased">
    <div class="relative min-h-screen md:flex">
        <aside x-cloak @mouseenter="sidebarExpanded = true" @mouseleave="sidebarExpanded = false"
            :class="{
                '-translate-x-[calc(100%+2rem)]': !sidebarOpen,
                'translate-x-0': sidebarOpen,
                'md:w-16 md:px-2': !sidebarExpanded,
                'md:w-64 md:px-4': sidebarExpanded
            }"
            class="absolute inset-y-0 left-0 z-50 m-4 flex h-[calc(100vh-2rem)] w-64 transform flex-col justify-between overflow-hidden rounded-[2rem] bg-slate-950 px-4 py-3 text-slate-300 shadow-2xl transition-all duration-300 ease-in-out md:fixed md:top-0 md:bottom-auto md:left-0 md:z-auto md:translate-x-0">
            <div class="mb-1 shrink-0 rounded-2xl border border-slate-800 bg-slate-900 p-2">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-500 font-display text-base font-bold text-white">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                    <div x-show="sidebarExpanded || sidebarOpen" x-transition.opacity class="min-w-0">
                        <p class="truncate font-semibold text-white">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-400">{{ auth()->user()->role ?? auth()->user()->email }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="my-2 border-t border-slate-700"></div>
            <nav aria-label="Navigasi utama" tabindex="0"
                class="min-h-0 flex-1 space-y-0 overflow-y-auto overscroll-contain scrollbar-hidden focus:outline-none">
                @php($navigation = [['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home'], ['route' => 'data-pegawai', 'label' => 'Data Pegawai', 'icon' => 'users'], ['route' => 'wla', 'label' => 'WLA', 'icon' => 'chart'], ['route' => 'fit-proper', 'label' => 'Fit & Proper', 'icon' => 'clipboard'], ['route' => 'mutasi', 'label' => 'Mutasi', 'icon' => 'arrows'], ['route' => 'promosi', 'label' => 'Promosi', 'icon' => 'chart'], ['route' => 'demosi', 'label' => 'Demosi', 'icon' => 'demosi'], ['route' => 'formasi', 'label' => 'Formasi', 'icon' => 'formasi'], ['route' => 'definitif', 'label' => 'Definitif', 'icon' => 'clipboard'], ['route' => 'laporan', 'label' => 'Laporan', 'icon' => 'document']])
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                        :class="{ 'justify-center': !sidebarExpanded && !sidebarOpen }"
                        class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium leading-5 transition {{ ($item['route'] === 'wla' ? request()->routeIs('wla*') : request()->routeIs($item['route'])) ? 'bg-amber-400 text-slate-950 shadow-lg shadow-amber-400/10' : 'text-slate-400 hover:bg-slate-900 hover:text-white' }}">
                        <x-dashboard-icon :name="$item['icon']" class="h-[22px] w-[22px]" />
                        <span x-show="sidebarExpanded || sidebarOpen" x-transition.opacity
                            class="whitespace-nowrap">{{ $item['label'] }}</span>
                    </a>
                @endforeach
                @if (auth()->user()->roleEnum()->canManageAllRoles())
                    <a href="{{ route('organization.departments.index') }}" title="Master Organization"
                        :class="{ 'justify-center': !sidebarExpanded && !sidebarOpen }"
                        class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium leading-5 transition {{ request()->routeIs('organization.*') ? 'bg-amber-400 text-slate-950 shadow-lg shadow-amber-400/10' : 'text-slate-400 hover:bg-slate-900 hover:text-white' }}">
                        <x-dashboard-icon name="users" class="h-[22px] w-[22px]" />
                        <span x-show="sidebarExpanded || sidebarOpen" x-transition.opacity
                            class="whitespace-nowrap">Master Organization</span>
                    </a>
                    <a href="{{ route('work-schedules.index') }}" title="Work Schedules"
                        :class="{ 'justify-center': !sidebarExpanded && !sidebarOpen }"
                        class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium leading-5 transition {{ request()->routeIs('work-schedules.*') ? 'bg-amber-400 text-slate-950 shadow-lg shadow-amber-400/10' : 'text-slate-400 hover:bg-slate-900 hover:text-white' }}">
                        <x-dashboard-icon name="clipboard" class="h-[22px] w-[22px]" />
                        <span x-show="sidebarExpanded || sidebarOpen" x-transition.opacity
                            class="whitespace-nowrap">Work Schedules</span>
                    </a>
                    <a href="{{ route('work-calendars.index') }}" title="Work Calendars"
                        :class="{ 'justify-center': !sidebarExpanded && !sidebarOpen }"
                        class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium leading-5 transition {{ request()->routeIs('work-calendars.*') ? 'bg-amber-400 text-slate-950 shadow-lg shadow-amber-400/10' : 'text-slate-400 hover:bg-slate-900 hover:text-white' }}">
                        <x-dashboard-icon name="document" class="h-[22px] w-[22px]" />
                        <span x-show="sidebarExpanded || sidebarOpen" x-transition.opacity
                            class="whitespace-nowrap">Work Calendars</span>
                    </a>
                @endif
                @if (auth()->user()->canManageRbac())
                    <a href="{{ route('konfigurasi-user') }}" title="Konfigurasi User"
                        :class="{ 'justify-center': !sidebarExpanded && !sidebarOpen }"
                        class="flex items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium leading-5 transition {{ request()->routeIs('konfigurasi-user*') ? 'bg-amber-400 text-slate-950 shadow-lg shadow-amber-400/10' : 'text-slate-400 hover:bg-slate-900 hover:text-white' }}">
                        <x-dashboard-icon name="users" class="h-[22px] w-[22px]" />
                        <span x-show="sidebarExpanded || sidebarOpen" x-transition.opacity
                            class="whitespace-nowrap">Konfigurasi User</span>
                    </a>
                @endif
            </nav>
            <div class="mt-auto mb-4 shrink-0 border-t border-slate-800 pt-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Logout"
                        :class="{ 'justify-center': !sidebarExpanded && !sidebarOpen }"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-[15px] font-medium leading-5 text-slate-400 transition hover:bg-red-500/10 hover:text-red-300">
                        <x-dashboard-icon name="logout" class="h-[22px] w-[22px]" />
                        <span x-show="sidebarExpanded || sidebarOpen" x-transition.opacity>Logout</span>
                    </button>
                </form>
            </div>
        </aside>
        <div x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
            class="fixed inset-0 z-40 bg-black/50 md:hidden"></div>
        <main class="min-h-screen min-w-0 flex-1 md:ml-20">
            <header class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 md:hidden">
                <button type="button" @click="sidebarOpen = true" aria-label="Buka menu"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <span class="font-display text-lg font-bold text-slate-950">HCM</span>
            </header>
            <header
                class="flex flex-col gap-3 border-b border-slate-200 bg-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 sm:py-5 lg:px-10">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Human Capital Management
                    </p>
                    <h1 class="mt-1 font-display text-2xl font-bold text-slate-950">@yield('page-title', 'Dashboard')</h1>
                </div>
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold text-slate-700">{{ now()->translatedFormat('l, d F Y') }}</p>
                    <p class="text-xs text-slate-400">Panel administrasi internal</p>
                </div>
            </header>
            <section class="px-4 py-6 sm:px-6 sm:py-8 lg:px-10">@yield('content')</section>
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
