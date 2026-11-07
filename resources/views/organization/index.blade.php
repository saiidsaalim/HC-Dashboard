@extends('layouts.app')

@php
    $isFormationMasterHome = request()->routeIs('formasi.master') || (session('active_workspace', 'hc-od-career-management') === 'hc-od-career-management' && auth()->user()->hasPermission('formation.view'));
@endphp

@section('page-title', $isFormationMasterHome ? 'Formasi · Master Organisasi' : 'Master Organization')

@section('content')
    <section class="formation-master">
        <div class="mb-6 max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Struktur organisasi</p>
            <h2 class="mt-2 font-display text-2xl font-bold text-slate-950 sm:text-3xl">Kelola master organisasi</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                Kelola setiap tingkat organisasi dari satu tempat menggunakan data posisi dan relasi yang sama.
            </p>
        </div>

        @php
            $levels = [
                ['name' => 'Directorate', 'total' => $directoratesTotal, 'active' => $directoratesActive, 'route' => 'organization.directorates.index', 'icon' => 'formasi', 'description' => 'Tingkat utama dalam struktur organisasi.'],
                ['name' => 'Department', 'total' => $departmentsTotal, 'active' => $departmentsActive, 'route' => 'organization.departments.index', 'icon' => 'users', 'description' => 'Kelompok organisasi di bawah Directorate.'],
                ['name' => 'Unit', 'total' => $unitsTotal, 'active' => $unitsActive, 'route' => 'organization.units.index', 'icon' => 'clipboard', 'description' => 'Unit kerja yang terhubung ke Department.'],
                ['name' => 'Section', 'total' => $sectionsTotal, 'active' => $sectionsActive, 'route' => 'organization.sections.index', 'icon' => 'document', 'description' => 'Bagian di dalam Unit, bila tersedia.'],
                ['name' => 'Position', 'total' => $positionsTotal, 'active' => $positionsActive, 'route' => 'organization.positions.index', 'icon' => 'chart', 'description' => 'Posisi, relasi atasan, dan kebutuhan formasi.'],
            ];
        @endphp

        <div class="mb-5 flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold text-slate-500" aria-label="Tingkatan organisasi">
            @foreach ($levels as $level)
                @unless ($loop->first)
                    <svg class="h-3.5 w-3.5 text-slate-300" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                @endunless
                <span class="rounded-lg bg-slate-50 px-2.5 py-1.5">{{ $level['name'] }}</span>
            @endforeach
        </div>

        <div class="formation-master-grid gap-4 grid md:grid-cols-2 xl:grid-cols-5">
            @foreach ($levels as $index => $level)
                <article class="formation-master-card flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="formation-master-icon flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700"><x-dashboard-icon :name="$level['icon']" aria-hidden="true" /></span>
                        <span class="text-xs font-semibold text-slate-400">0{{ $index + 1 }}</span>
                    </div>
                    <h3 class="mt-3 text-lg font-display font-bold text-slate-950">{{ $level['name'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $level['description'] }}</p>
                    <dl class="my-5 grid grid-cols-2 gap-3">
                        <div>
                            <dt class="text-xs font-semibold text-slate-400">Total</dt>
                            <dd class="formation-master-count font-display text-3xl mt-1 font-bold text-slate-950">{{ number_format($level['total'], 0, ',', '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-emerald-600">Aktif</dt>
                            <dd class="font-display text-3xl mt-1 font-bold text-emerald-700">{{ number_format($level['active'], 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                    <a href="{{ route($level['route']) }}"
                        class="formation-master-link min-h-10 gap-2 px-3 mt-auto inline-flex items-center justify-center rounded-xl bg-slate-950 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                        Kelola {{ $level['name'] }}
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </a>
                </article>
            @endforeach
        </div>

        <div class="mt-5 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-900">
            <x-dashboard-icon name="edit" class="mt-0.5 h-4 w-4" aria-hidden="true" />
            <p>Kebutuhan formasi ditetapkan secara manual pada data Position. Menyimpan perubahan struktur atau nama tidak menetapkan kebutuhan.</p>
        </div>
    </section>
@endsection
