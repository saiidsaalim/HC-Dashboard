@extends('layouts.app')

@section('page-title', 'Master Organization')

@section('content')
    <div class="mb-6 max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-600">Struktur organisasi</p>
        <h2 class="mt-2 font-display text-2xl font-bold text-slate-950 sm:text-3xl">Kelola master organisasi</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">
            Pilih tingkat organisasi yang ingin dikelola. Setiap master memiliki daftar, filter, dan proses CRUD sendiri.
        </p>
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <article class="flex min-h-64 flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-sky-600">Tingkat 1</p>
                    <h3 class="mt-2 font-display text-2xl font-bold text-slate-950">Department</h3>
                </div>
                <span class="rounded-xl bg-sky-50 p-3 text-sky-700">
                    <x-dashboard-icon name="users" class="h-6 w-6" />
                </span>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-500">Kelola Department sebagai induk tertinggi struktur organisasi.</p>
            <dl class="mt-6 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $departmentsTotal }}</dd>
                </div>
                <div class="rounded-xl bg-emerald-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Aktif</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-emerald-700">{{ $departmentsActive }}</dd>
                </div>
            </dl>
            <a href="{{ route('organization.departments.index') }}"
                class="mt-auto inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                Kelola Department
            </a>
        </article>

        <article class="flex min-h-64 flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Tingkat 2</p>
                    <h3 class="mt-2 font-display text-2xl font-bold text-slate-950">Unit</h3>
                </div>
                <span class="rounded-xl bg-amber-50 p-3 text-amber-700">
                    <x-dashboard-icon name="chart" class="h-6 w-6" />
                </span>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-500">Kelola Unit yang berada di bawah Department tertentu.</p>
            <dl class="mt-6 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $unitsTotal }}</dd>
                </div>
                <div class="rounded-xl bg-emerald-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Aktif</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-emerald-700">{{ $unitsActive }}</dd>
                </div>
            </dl>
            <a href="{{ route('organization.units.index') }}"
                class="mt-auto inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                Kelola Unit
            </a>
        </article>

        <article class="flex min-h-64 flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wider text-emerald-600">Tingkat 3</p>
                    <h3 class="mt-2 font-display text-2xl font-bold text-slate-950">Position</h3>
                </div>
                <span class="rounded-xl bg-emerald-50 p-3 text-emerald-700">
                    <x-dashboard-icon name="clipboard" class="h-6 w-6" />
                </span>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-500">Kelola Position yang berada di dalam Unit dan Department.</p>
            <dl class="mt-6 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-slate-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Total</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $positionsTotal }}</dd>
                </div>
                <div class="rounded-xl bg-emerald-50 p-3">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Aktif</dt>
                    <dd class="mt-1 font-display text-2xl font-bold text-emerald-700">{{ $positionsActive }}</dd>
                </div>
            </dl>
            <a href="{{ route('organization.positions.index') }}"
                class="mt-auto inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                Kelola Position
            </a>
        </article>
    </div>
@endsection
