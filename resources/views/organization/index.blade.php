@extends('layouts.app')

@section('page-title', 'Master Organization')

@section('content')
    <div class="mb-6 max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-600">Struktur organisasi</p>
        <h2 class="mt-2 font-display text-2xl font-bold text-slate-950 sm:text-3xl">Kelola master organisasi</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">
            Struktur master mengikuti urutan Directorate, Department, Unit, Section, lalu Position.
        </p>
    </div>

    @php
        $levels = [
            ['name' => 'Directorate', 'total' => $directoratesTotal, 'active' => $directoratesActive, 'route' => 'organization.directorates.index', 'description' => 'Level tertinggi yang bersumber dari TXT_DIR.'],
            ['name' => 'Department', 'total' => $departmentsTotal, 'active' => $departmentsActive, 'route' => 'organization.departments.index', 'description' => 'Department di bawah Directorate, bersumber dari TXT_DEPT.'],
            ['name' => 'Unit', 'total' => $unitsTotal, 'active' => $unitsActive, 'route' => 'organization.units.index', 'description' => 'Unit di bawah Department, bersumber dari TXT_BIRO.'],
            ['name' => 'Section', 'total' => $sectionsTotal, 'active' => $sectionsActive, 'route' => 'organization.sections.index', 'description' => 'Section di bawah Unit, bersumber dari TXT_SECT.'],
            ['name' => 'Position', 'total' => $positionsTotal, 'active' => $positionsActive, 'route' => 'organization.positions.index', 'description' => 'Position berada pada Section dalam struktur organisasi.'],
        ];
    @endphp

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-5">
        @foreach ($levels as $index => $level)
            <article class="flex min-h-64 flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Tingkat {{ $index + 1 }}</p>
                <h3 class="mt-2 font-display text-xl font-bold text-slate-950">{{ $level['name'] }}</h3>
                <p class="mt-3 text-sm leading-6 text-slate-500">{{ $level['description'] }}</p>
                <dl class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs font-semibold uppercase text-slate-400">Total</dt><dd class="mt-1 text-2xl font-bold text-slate-950">{{ $level['total'] }}</dd></div>
                    <div class="rounded-xl bg-emerald-50 p-3"><dt class="text-xs font-semibold uppercase text-emerald-600">Aktif</dt><dd class="mt-1 text-2xl font-bold text-emerald-700">{{ $level['active'] }}</dd></div>
                </dl>
                <a href="{{ route($level['route']) }}" class="mt-auto inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                    Kelola {{ $level['name'] }}
                </a>
            </article>
        @endforeach
    </div>
@endsection
