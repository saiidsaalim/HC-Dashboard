@extends('layouts.app')

@section('page-title', 'Formasi')

@section('content')
    @php
        $confirmedPercent = $summary['total'] > 0 ? (int) round($summary['confirmed'] / $summary['total'] * 100) : 0;
        $tableParameters = array_filter([...$filters, 'search' => $search, 'per_page' => $perPage], fn ($value) => $value !== null && $value !== '');
        $statusParameters = \Illuminate\Support\Arr::except($tableParameters, ['confirmation', 'gap_status']);
        $filterLabels = [
            'search' => $search !== '' ? 'Pencarian: '.$search : null,
            'job_level' => ($filters['job_level'] ?? '') !== '' ? 'Level: '.$filters['job_level'] : null,
            'directorate_id' => !empty($filters['directorate_id']) ? 'Directorate: '.$directorates->firstWhere('id', $filters['directorate_id'])?->name : null,
            'department_id' => !empty($filters['department_id']) ? 'Department: '.$departments->firstWhere('id', $filters['department_id'])?->name : null,
            'unit_id' => !empty($filters['unit_id']) ? 'Unit: '.$units->firstWhere('id', $filters['unit_id'])?->name : null,
            'section_id' => !empty($filters['section_id']) ? 'Section: '.$sections->firstWhere('id', $filters['section_id'])?->name : null,
            'confirmation' => match ($filters['confirmation'] ?? '') { 'confirmed' => 'Sudah ditetapkan', 'unconfirmed' => 'Belum ditetapkan', default => null },
            'gap_status' => ['shortage' => 'Kekurangan', 'fulfilled' => 'Terpenuhi', 'excess' => 'Kelebihan', 'vacant' => 'Kosong'][$filters['gap_status'] ?? ''] ?? null,
        ];
    @endphp
    <div class="formation-page" data-formation-page x-data="{ expandedRows: {} }">
    @include('pages._formasi-tabs')

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-semibold">Filter belum dapat diterapkan. Pilih kembali filter yang sesuai.</p>
            <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="formation-section-label"><span>Ringkasan formasi</span><span>Seluruh posisi aktif · tidak dibatasi filter</span></div>
    <div class="formation-metrics">
        @foreach ([
            ['label' => 'Total Posisi Aktif', 'value' => $summary['total'], 'tone' => 'blue', 'icon' => 'formasi', 'note' => 'Posisi dalam organisasi'],
            ['label' => 'Kebutuhan Ditetapkan', 'value' => $summary['confirmed'], 'tone' => 'teal', 'icon' => 'clipboard', 'note' => 'Posisi sudah ditetapkan manual'],
            ['label' => 'Belum Ditetapkan', 'value' => $summary['unconfirmed'], 'tone' => 'amber', 'icon' => 'edit', 'note' => 'Posisi menunggu penetapan'],
            ['label' => 'Total Kekurangan', 'value' => $summary['shortage'], 'tone' => 'rose', 'icon' => 'users', 'note' => 'Pegawai · termasuk posisi kosong'],
            ['label' => 'Total Kelebihan', 'value' => $summary['excess'], 'tone' => 'violet', 'icon' => 'users', 'note' => 'Pegawai di atas kebutuhan'],
        ] as $card)
            <article class="formation-metric formation-tone-{{ $card['tone'] }}">
                <div class="formation-metric-top"><span>{{ $card['label'] }}</span><span class="formation-metric-icon"><x-dashboard-icon :name="$card['icon']" aria-hidden="true" /></span></div>
                <strong>{{ number_format($card['value'], 0, ',', '.') }}</strong>
                <p>{{ $card['note'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="formation-progress-strip">
        <div class="formation-progress-copy"><span class="formation-progress-icon"><x-dashboard-icon name="clipboard" aria-hidden="true" /></span><div><strong>Progres penetapan kebutuhan</strong><p>{{ $summary['confirmed'] }} dari {{ $summary['total'] }} posisi aktif sudah ditetapkan</p></div></div>
        <div class="formation-progress"><progress value="{{ $summary['confirmed'] }}" max="{{ max(1, $summary['total']) }}" aria-label="Progres penetapan kebutuhan"></progress><strong>{{ $confirmedPercent }}%</strong></div>
    </div>

    <details class="formation-data-note">
        <summary><span class="formation-data-note-icon" aria-hidden="true">i</span><span><strong>Pegawai belum terpetakan: {{ number_format($unmappedEmployeeCount, 0, ',', '.') }}</strong><span>Gap mengikuti data pegawai yang terhubung ke posisi.</span></span><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></summary>
        <div>Jumlah belum terpetakan mencakup seluruh pegawai tanpa Position ID. Aktual menghitung seluruh pegawai yang saat ini terhubung ke posisi. Kelengkapan pemetaan belum dipastikan, sehingga gap belum menjamin kondisi riil perusahaan. Kebutuhan yang belum ditetapkan tidak dihitung; kekurangan dan kelebihan dijumlahkan terpisah per posisi.</div>
    </details>

    <section class="formation-table-card" aria-label="Analisis Formasi">
        <div class="formation-table-heading"><div><h3>Analisis per posisi <span>{{ number_format($positions->total(), 0, ',', '.') }}</span></h3><p>Buka rincian posisi atau tetapkan jumlah kebutuhan sesuai izin Anda.</p></div><span class="formation-table-hint"><x-dashboard-icon name="eye" aria-hidden="true" />Klik posisi untuk rincian</span></div>
        <div class="formation-toolbar">
            <form method="GET" action="{{ route('formasi') }}" class="formation-search-form" data-table-toolbar>
                @foreach (\Illuminate\Support\Arr::except($tableParameters, ['search', 'job_level', 'per_page']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <div class="formation-search"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.5" /><path d="m13 13 4 4" stroke-linecap="round" /></svg><label class="sr-only" for="formation-search">Cari posisi</label><input id="formation-search" name="search" value="{{ $search }}" placeholder="Cari nama atau kode posisi..." data-table-search><button type="submit" aria-label="Cari data">Cari</button></div>
                <label class="formation-level-filter" for="formation-level"><span>Level</span><select id="formation-level" name="job_level" data-formation-level @change="$el.form.requestSubmit()"><option value="">Semua level</option>@foreach ($jobLevels as $jobLevel)<option value="{{ $jobLevel }}" @selected(($filters['job_level'] ?? '') === $jobLevel)>{{ $jobLevel }}</option>@endforeach</select></label>
                <input type="hidden" name="per_page" value="{{ $perPage }}">
            </form>
            <x-ui.filter-panel id="formation-filters" title="Filter Analisis Formasi"
                :reset-href="route('formasi', ['search' => $search, 'per_page' => $perPage])"
                :active="collect($filters)->except('search')->filter()->isNotEmpty()">
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <input type="hidden" name="job_level" value="{{ $filters['job_level'] ?? '' }}">
                <div class="space-y-4" x-data="{
                    directorateId: @js((string) ($filters['directorate_id'] ?? '')),
                    departmentId: @js((string) ($filters['department_id'] ?? '')),
                    unitId: @js((string) ($filters['unit_id'] ?? '')),
                    sectionId: @js((string) ($filters['section_id'] ?? '')),
                    confirmation: @js($filters['confirmation'] ?? ''),
                    gapStatus: @js($filters['gap_status'] ?? ''),
                    departments: @js($departments->map(fn ($department) => ['id' => $department->id, 'directorate_id' => $department->directorate_id, 'label' => $department->name.($department->directorate ? ' · '.$department->directorate->name : '')])->values()),
                    units: @js($units->map(fn ($unit) => ['id' => $unit->id, 'department_id' => $unit->department_id, 'directorate_id' => $unit->department?->directorate_id, 'label' => $unit->name.' · '.$unit->department?->name])->values()),
                    sections: @js($sections->map(fn ($section) => ['id' => $section->id, 'unit_id' => $section->unit_id, 'department_id' => $section->unit?->department_id, 'directorate_id' => $section->unit?->department?->directorate_id, 'label' => $section->name.' · '.$section->unit?->name.' (ID '.$section->id.')'])->values()),
                }">
                    <label class="block text-sm font-semibold text-slate-700">Directorate
                        <select name="directorate_id" x-model="directorateId" @change="departmentId = ''; unitId = ''; sectionId = ''" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Semua Directorate</option>
                            @foreach ($directorates as $directorate)
                                <option value="{{ $directorate->id }}" @selected(($filters['directorate_id'] ?? '') == $directorate->id)>{{ $directorate->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Department
                        <select name="department_id" x-model="departmentId" @change="unitId = ''; sectionId = ''" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Semua Department</option>
                            <template x-for="department in departments.filter(item => !directorateId || String(item.directorate_id ?? '') === directorateId)" :key="department.id">
                                <option :value="String(department.id)" x-text="department.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Unit
                        <select name="unit_id" x-model="unitId" @change="sectionId = ''" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Semua Unit</option>
                            <template x-for="unit in units.filter(item => (!departmentId || String(item.department_id) === departmentId) &amp;&amp; (!directorateId || String(item.directorate_id ?? '') === directorateId))" :key="unit.id">
                                <option :value="String(unit.id)" x-text="unit.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Section
                        <select name="section_id" x-model="sectionId" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Semua Section</option>
                            <template x-for="section in sections.filter(item => (!unitId || String(item.unit_id) === unitId) &amp;&amp; (!departmentId || String(item.department_id) === departmentId) &amp;&amp; (!directorateId || String(item.directorate_id ?? '') === directorateId))" :key="section.id">
                                <option :value="String(section.id)" x-text="section.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Status Penetapan
                        <select name="confirmation" x-model="confirmation" @change="if (confirmation === 'unconfirmed') gapStatus = ''" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Semua status penetapan</option>
                            <option value="confirmed" @selected(($filters['confirmation'] ?? '') === 'confirmed')>Sudah ditetapkan</option>
                            <option value="unconfirmed" @selected(($filters['confirmation'] ?? '') === 'unconfirmed')>Belum ditetapkan</option>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Status Gap
                        <select name="gap_status" x-model="gapStatus" :disabled="confirmation === 'unconfirmed'" @change="if (gapStatus) confirmation = 'confirmed'" class="mt-1 block w-full rounded-lg border-slate-300 text-sm disabled:bg-slate-100 disabled:text-slate-400">
                            <option value="">Semua status gap</option>
                            @foreach (['shortage' => 'Kekurangan', 'fulfilled' => 'Terpenuhi', 'excess' => 'Kelebihan', 'vacant' => 'Kosong'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['gap_status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs font-normal text-slate-500">Status gap tersedia setelah kebutuhan ditetapkan.</span>
                    </label>
                </div>
            </x-ui.filter-panel>
        </div>
        <div class="formation-quick-filters" aria-label="Filter cepat status">
            @foreach (['all' => 'Semua status', 'unconfirmed' => 'Belum ditetapkan', 'vacant' => 'Kosong', 'shortage' => 'Kekurangan', 'fulfilled' => 'Terpenuhi', 'excess' => 'Kelebihan'] as $value => $label)
                @php
                    $quickParameters = match ($value) {
                        'all' => $statusParameters,
                        'unconfirmed' => [...$statusParameters, 'confirmation' => 'unconfirmed'],
                        default => [...$statusParameters, 'confirmation' => 'confirmed', 'gap_status' => $value],
                    };
                    $quickActive = $value === 'all' ? empty($filters['confirmation']) && empty($filters['gap_status']) : ($value === 'unconfirmed' ? ($filters['confirmation'] ?? '') === 'unconfirmed' : ($filters['gap_status'] ?? '') === $value);
                @endphp
                <a href="{{ route('formasi', $quickParameters) }}" class="formation-quick-filter {{ $quickActive ? 'is-active' : '' }}" data-quick-status="{{ $value }}" @if ($quickActive) aria-current="true" @endif><span class="formation-status-dot formation-dot-{{ $value }}" aria-hidden="true"></span>{{ $label }}</a>
            @endforeach
        </div>
        @if (array_filter($filterLabels))
            <div class="formation-active-filters" aria-label="Filter aktif">
                <span>Filter aktif</span>
                @foreach (array_filter($filterLabels) as $key => $label)
                    @php
                        $removeKeys = match ($key) {
                            'directorate_id' => ['directorate_id', 'department_id', 'unit_id', 'section_id'],
                            'department_id' => ['department_id', 'unit_id', 'section_id'],
                            'unit_id' => ['unit_id', 'section_id'],
                            'confirmation' => ['confirmation', 'gap_status'],
                            default => [$key],
                        };
                    @endphp
                    <a href="{{ route('formasi', \Illuminate\Support\Arr::except($tableParameters, $removeKeys)) }}" class="formation-filter-chip" data-filter-chip="{{ $key }}" aria-label="Hapus filter {{ $label }}">{{ $label }}<span aria-hidden="true">×</span></a>
                @endforeach
                <a href="{{ route('formasi', ['per_page' => $perPage]) }}" class="formation-reset-filters">Hapus semua</a>
            </div>
        @endif
        <div class="formation-table-scroll" tabindex="0" aria-label="Tabel analisis posisi, geser untuk melihat semua kolom">
            <table class="formation-table">
                <thead>
                    <tr>
                        @foreach (['Posisi', 'Level', 'Department', 'Unit', 'Section', 'Kebutuhan Formasi', 'Pegawai Terhubung', 'Gap', 'Status', 'Aksi'] as $heading)
                            <th scope="col" class="px-4 py-3">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($positions as $position)
                        @php
                            $actual = (int) $position->employees_count;
                            $gap = $position->formation_is_confirmed ? (int) $position->formation_count - $actual : null;
                            [$status, $tone] = match (true) {
                                $gap === null => ['Belum ditetapkan', 'neutral'],
                                $actual === 0 && $gap > 0 => ['Kosong', 'danger'],
                                $gap > 0 => ['Kekurangan', 'warning'],
                                $gap < 0 => ['Kelebihan', 'info'],
                                default => ['Terpenuhi', 'success'],
                            };
                        @endphp
                        <tr data-position-id="{{ $position->id }}" :class="expandedRows[{{ $position->id }}] ? 'is-expanded' : ''">
                            <td><button type="button" class="formation-position-trigger" data-position-expand="{{ $position->id }}" @click="expandedRows[{{ $position->id }}] = !expandedRows[{{ $position->id }}]" :aria-expanded="!!expandedRows[{{ $position->id }}]" aria-expanded="false" aria-controls="position-details-{{ $position->id }}"><span class="formation-position-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($position->name, 0, 2)) }}</span><span><strong>{{ $position->name }}</strong><small>ID {{ $position->id }}</small></span><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 8 4 4 4-4" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></button></td>
                            <td><span class="formation-level-badge">{{ $position->job_level ?: '—' }}</span></td>
                            <td class="px-4 py-3 text-slate-600">{{ $position->unit?->department?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $position->unit?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $position->section?->name ?? '—' }}</td>
                            <td class="formation-number {{ !$position->formation_is_confirmed ? 'formation-undetermined' : '' }}">{{ $position->formation_is_confirmed ? $position->formation_count : 'Belum ditetapkan' }}</td>
                            <td class="formation-number">{{ $actual }}</td>
                            <td class="formation-number {{ $gap > 0 ? 'formation-gap-short' : ($gap < 0 ? 'formation-gap-excess' : '') }}">{{ $gap === null ? '—' : ($gap > 0 ? '+'.$gap : $gap) }}</td>
                            <td class="px-4 py-3"><x-ui.status-badge :tone="$tone">{{ $status }}</x-ui.status-badge></td>
                            <td class="px-4 py-3">
                                @can('organization.view')
                                    @can('update', $position)
                                        <a class="formation-row-action" href="{{ route('formasi.requirement.edit', ['position' => $position, ...$tableParameters, 'page' => $positions->currentPage()]) }}">{{ $position->formation_is_confirmed ? 'Ubah kebutuhan' : 'Tetapkan kebutuhan' }}<span aria-hidden="true">↗</span></a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endcan
                                @else
                                    <span class="text-slate-400">—</span>
                                @endcan
                            </td>
                        </tr>
                        <tr id="position-details-{{ $position->id }}" data-position-details="{{ $position->id }}" x-cloak x-show="expandedRows[{{ $position->id }}]">
                            <td colspan="10"><div class="formation-row-details"><div><span>Directorate</span><strong>{{ $position->unit?->department?->directorate?->name ?? 'Belum terhubung' }}</strong></div><div><span>Kode posisi</span><strong>{{ $position->code }}</strong></div><div><span>SAP Position ID</span><strong>{{ $position->external_position_id ?: 'Belum diisi' }}</strong></div><p>{{ $position->formation_is_confirmed ? 'Kebutuhan ditetapkan secara manual. Gap dihitung dari pegawai yang saat ini terhubung.' : 'Nilai awal belum dianggap sebagai kebutuhan sampai ditetapkan.' }}</p></div></td>
                        </tr>
                    @empty
                        <tr><x-ui.empty-state colspan="10" title="Tidak ada posisi aktif yang sesuai" description="Sesuaikan pencarian atau filter. Posisi dikelola melalui Master Organisasi sesuai izin." /></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="formation-table-footer">
            <p>{{ $positions->total() ? $positions->firstItem().'–'.$positions->lastItem() : '0' }} dari {{ number_format($positions->total(), 0, ',', '.') }} posisi</p>
            <form method="GET" action="{{ route('formasi') }}" class="formation-per-page">@foreach (\Illuminate\Support\Arr::except($tableParameters, ['per_page']) as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<label for="formation-per-page">Per halaman</label><select id="formation-per-page" name="per_page" aria-label="Jumlah data per halaman" @change="$el.form.requestSubmit()">@foreach ([20, 40, 60] as $option)<option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>@endforeach</select><button type="submit" class="sr-only">Tampilkan</button></form>
            <div>{{ $positions->links() }}</div>
        </div>
    </section>
    </div>
@endsection
