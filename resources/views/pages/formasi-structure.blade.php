@extends('layouts.app')

@section('page-title', 'Formasi · Struktur Organisasi')

@section('content')
    @include('pages._formasi-tabs')

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }} Pilih kembali Department yang sesuai.</div>
    @endif

    <section class="formation-structure min-w-0" data-formation-structure x-data="{ search: '' }">
        <div class="formation-structure-toolbar mb-5 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-bold text-slate-950">Struktur Organisasi</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $departments->total() }} Department · buka kartu untuk melihat detailnya.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" data-structure-expand
                        @click="$refs.branches.querySelectorAll('details[data-structure-branch]').forEach(branch => branch.open = true); $dispatch('formation-expand')"
                        class="ui-button ui-button-secondary text-xs">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 7 5 5 5-5M7 13l5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Buka semua
                    </button>
                    <button type="button" data-structure-collapse
                        @click="$refs.branches.querySelectorAll('details[data-structure-branch]').forEach(branch => branch.open = false); $dispatch('formation-collapse')"
                        class="ui-button ui-button-secondary text-xs">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m7 11 5-5 5 5M7 17l5-5 5 5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        Tutup semua
                    </button>
                </div>
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <form method="GET" action="{{ route('formasi.structure') }}" class="flex min-w-0 flex-wrap items-end gap-2">
                    <label class="min-w-0 flex-1 text-xs font-semibold text-slate-600" for="structure-department">Department
                        <select id="structure-department" name="department_id" class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm">
                            <option value="">Semua Department</option>
                            @foreach ($departmentOptions as $department)
                                <option value="{{ $department->id }}" @selected($departmentId == $department->id)>{{ $department->name }} (ID {{ $department->id }})</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="ui-button ui-button-primary" type="submit">Tampilkan</button>
                    <a class="ui-button ui-button-secondary" href="{{ route('formasi.structure') }}">Reset</a>
                </form>
                <label class="block min-w-0 text-xs font-semibold text-slate-600" for="structure-search">Cari pada halaman ini
                    <input id="structure-search" type="search" x-model="search"
                        @input="if (search.trim()) { $refs.branches.querySelectorAll('details[data-structure-branch]').forEach(branch => branch.open = true); $dispatch('formation-expand') }"
                        placeholder="Nama Department, Unit, atau posisi…"
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm">
                </label>
            </div>
        </div>

        <div class="mb-5 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm leading-6 text-sky-900">
            <x-dashboard-icon name="eye" class="mt-0.5 h-4 w-4" aria-hidden="true" />
            <p>Hubungan atasan mengikuti relasi yang sudah tersimpan. Posisi tanpa atasan tetap ditampilkan. Data nonaktif ditandai.</p>
        </div>

        <div class="space-y-4" x-ref="branches">
            @foreach ($emptyDirectorates as $directorate)
                <article class="formation-department rounded-2xl border border-slate-200 bg-white p-5" data-structure-card
                    x-show="!search.trim() || $el.textContent.toLowerCase().includes(search.trim().toLowerCase())">
                    <div class="flex items-start gap-3">
                        <span class="rounded-xl bg-slate-100 p-2.5 text-slate-500"><x-dashboard-icon name="formasi" aria-hidden="true" /></span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Directorate</p>
                            <h3 class="mt-1 font-bold text-slate-950">{{ $directorate->name }}</h3>
                            <p class="mt-1 text-xs text-slate-500">ID {{ $directorate->id }}{{ $directorate->active ? '' : ' · Nonaktif' }}</p>
                            <p class="mt-3 text-sm text-slate-500">Belum ada Department.</p>
                        </div>
                    </div>
                </article>
            @endforeach
            @forelse ($departments as $department)
                @php
                    $tree = $positionTrees[$department->id];
                    $unitsById = $department->units->keyBy('id');
                    $positionCount = $department->units->sum(fn ($unit) => $unit->positions->count());
                    $sectionCount = $department->units->sum(fn ($unit) => $unit->sections->count());
                @endphp
                <details class="formation-department group min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm"
                    data-structure-card data-structure-branch @if ($departmentId || $departments->count() === 1) open @endif
                    x-show="!search.trim() || $el.textContent.toLowerCase().includes(search.trim().toLowerCase())">
                    <summary class="formation-department-summary flex cursor-pointer list-none flex-wrap items-center gap-3 p-4 sm:p-5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700"><x-dashboard-icon name="formasi" aria-hidden="true" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-medium text-slate-400">{{ $department->directorate?->name ?? 'Directorate belum terhubung' }}</span>
                            <span class="mt-1 block break-words font-display text-lg font-bold text-slate-950">{{ $department->name }}</span>
                            <span class="mt-1 block text-xs text-slate-400">Department ID {{ $department->id }}</span>
                        </span>
                        <span class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500">
                            <span class="rounded-lg bg-slate-50 px-2.5 py-1.5">{{ $department->units->count() }} Unit</span>
                            <span class="rounded-lg bg-slate-50 px-2.5 py-1.5">{{ $positionCount }} Posisi</span>
                            @if (! $department->active || ($department->directorate && ! $department->directorate->active))
                                <x-ui.status-badge>Organisasi nonaktif</x-ui.status-badge>
                            @endif
                        </span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 7 5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </summary>
                    <div class="formation-department-body grid min-w-0 gap-5 border-t border-slate-100 p-4 sm:p-5 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                        <section class="formation-org-panel min-w-0 rounded-xl bg-slate-50 p-4" aria-label="Unit dan Section {{ $department->name }}">
                            <div class="mb-4 flex items-center justify-between gap-2">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Unit dan Section</h3>
                                <span class="text-xs text-slate-400">{{ $sectionCount }} Section</span>
                            </div>
                            <ul class="space-y-3">
                                @forelse ($department->units->sortBy('name') as $unit)
                                    <li>
                                        <details class="rounded-xl border border-slate-200 bg-white" data-structure-branch>
                                            <summary class="cursor-pointer break-words px-3 py-2.5 text-sm font-semibold text-slate-800">
                                                {{ $unit->name }}
                                                <span class="ml-1 text-xs font-normal text-slate-400">ID {{ $unit->id }}{{ $unit->active ? '' : ' · Nonaktif' }}</span>
                                                <span class="mt-1 block pl-4 text-xs font-normal text-slate-500">{{ $unit->sections->count() }} Section · {{ $unit->positions->count() }} Posisi</span>
                                            </summary>
                                            <ul class="mx-3 mb-3 space-y-2 border-l border-slate-200 pl-4 text-sm text-slate-600">
                                                @forelse ($unit->sections->sortBy('name') as $section)
                                                    <li class="break-words">{{ $section->name }} <span class="text-xs text-slate-400">· ID {{ $section->id }}{{ $section->active ? '' : ' · Nonaktif' }}</span></li>
                                                @empty
                                                    <li class="text-xs text-slate-400">Belum ada Section.</li>
                                                @endforelse
                                            </ul>
                                        </details>
                                    </li>
                                @empty
                                    <li class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Belum ada Unit.</li>
                                @endforelse
                            </ul>
                        </section>
                        <section aria-label="Relasi atasan posisi {{ $department->name }}" class="min-w-0">
                            <div class="mb-4 flex items-center justify-between gap-2">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Relasi Atasan Posisi</h3>
                                <span class="text-xs text-slate-400">{{ $positionCount }} Posisi</span>
                            </div>
                            <ul class="space-y-3">
                                @forelse ($tree['roots'] as $position)
                                    @include('pages._formasi-position-node', ['position' => $position, 'children' => $tree['children'], 'path' => []])
                                @empty
                                    <li class="rounded-xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Belum ada pohon posisi dengan relasi yang lengkap.</li>
                                @endforelse
                            </ul>
                            @if ($tree['unresolved']->isNotEmpty())
                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-800">Relasi berikut belum dapat disusun menjadi pohon karena terdapat hubungan berulang. Periksa relasi atasan pada Master Organisasi.</div>
                                <ul class="mt-3 space-y-3">
                                    @foreach ($tree['unresolved'] as $position)
                                        @include('pages._formasi-position-node', ['position' => $position, 'children' => collect(), 'path' => []])
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    </div>
                </details>
            @empty
                @if ($emptyDirectorates->isEmpty())
                    <x-ui.empty-state title="Belum ada Department yang sesuai" description="Sesuaikan filter atau kelola struktur melalui Master Organisasi sesuai izin." />
                @endif
            @endforelse
        </div>
        <div x-cloak x-show="search.trim() &amp;&amp; !Array.from($refs.branches.querySelectorAll('[data-structure-card]')).some(card => card.textContent.toLowerCase().includes(search.trim().toLowerCase()))"
            class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500" role="status">
            Tidak ada hasil pada halaman ini. Coba kata lain atau pilih Department di atas.
        </div>
        <div class="mt-5">{{ $departments->links() }}</div>
    </section>
@endsection
