@extends('layouts.app')

@section('page-title', 'Dashboard')

@section('content')
    @php
        $toneClasses = [
            'amber' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'sky' => 'bg-sky-50 text-sky-700 ring-sky-100',
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'violet' => 'bg-violet-50 text-violet-700 ring-violet-100',
        ];
        $statRoutes = [
            'Fit & Proper' => 'fit-proper',
            'Mutasi' => 'mutasi',
            'Promosi' => 'promosi',
            'Laporan' => 'laporan',
        ];
    @endphp

    <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <p class="text-sm font-medium text-slate-500">Selamat datang kembali, {{ auth()->user()->name }}.</p>
            <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Ringkasan Human Capital</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Akses ringkas ke informasi dan modul utama yang Anda gunakan.</p>
        </div>
        <span class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm">
            <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
            {{ auth()->user()->role ?? 'Pengguna' }}
        </span>
    </div>

    <div data-dashboard-card-grid class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <a href="{{ route($statRoutes[$stat['label']]) }}"
                data-dashboard-card
                class="ui-card group relative overflow-hidden p-5 hover:-translate-y-1 hover:scale-[1.01] hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 motion-reduce:transform-none">
                <div class="flex items-start justify-between gap-4">
                    <span class="rounded-xl p-3 ring-1 ring-inset transition-transform duration-300 group-hover:-translate-y-0.5 group-hover:scale-[1.03] motion-reduce:transform-none {{ $toneClasses[$stat['tone']] }}">
                        <x-dashboard-icon :name="$stat['icon']" />
                    </span>
                    <svg class="h-5 w-5 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.69L10.22 5.03a.75.75 0 0 1 1.06-1.06l5.5 5.5a.75.75 0 0 1 0 1.06l-5.5 5.5a.75.75 0 1 1-1.06-1.06l4.22-4.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                    </svg>
                </div>
                <p class="mt-6 text-sm font-semibold text-slate-500">{{ $stat['label'] }}</p>
                <p data-dashboard-counter="{{ $stat['value'] }}"
                    class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">0</p>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ $stat['description'] }}</p>
            </a>
        @endforeach
    </div>

    <section class="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h3 class="font-display text-lg font-bold text-slate-950">Akses cepat</h3>
                <p class="mt-1 text-sm text-slate-500">Buka pekerjaan utama tanpa melewati banyak halaman.</p>
            </div>
            <span class="w-fit rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">Human Capital Management</span>
        </div>
        <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($stats as $stat)
                <a href="{{ route($statRoutes[$stat['label']]) }}"
                    class="group flex min-h-28 items-center justify-between gap-4 bg-white px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-400">
                    <span>
                        <span class="block text-sm font-bold text-slate-800">{{ $stat['label'] }}</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">Buka modul {{ $stat['label'] }}</span>
                    </span>
                    <span class="text-xl text-slate-300 transition group-hover:translate-x-1 group-hover:text-slate-600" aria-hidden="true">&rarr;</span>
                </a>
            @endforeach
        </div>
    </section>
@endsection
