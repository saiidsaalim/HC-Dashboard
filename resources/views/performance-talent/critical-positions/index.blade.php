@extends('layouts.app')

@section('page-title', 'Critical Position')

@section('content')
    @php
        $hasCreateErrors = $errors->getBag('createCriticalPosition')->any();
        $hasUpdateErrors = $errors->getBag('updateCriticalPosition')->any();
        $oldCriticalPositionId = old('critical_position_id');
        $initialPanel = $hasCreateErrors ? 'create' : ($hasUpdateErrors ? 'edit' : null);
        $initialEditAction = $hasUpdateErrors && is_numeric($oldCriticalPositionId)
            ? route('performance-talent.critical-positions.update', (int) $oldCriticalPositionId)
            : '';
        $initialEditForm = [
            'id' => (string) $oldCriticalPositionId,
            'position_id' => (string) old('position_id', ''),
            'criticality' => old('criticality', ''),
            'assessment_reason' => old('assessment_reason', ''),
            'assessed_at' => old('assessed_at', ''),
            'review_due_at' => old('review_due_at', ''),
            'notes' => old('notes', ''),
        ];
        $canViewSuccessors = auth()->user()->can('viewAny', \App\Models\Successor::class);
        $canCreateSuccessors = auth()->user()->can('create', \App\Models\Successor::class);
    @endphp

    <div x-data="{
        panel: @js($initialPanel),
        editAction: @js($initialEditAction),
        editForm: @js($initialEditForm),
        detail: null,
        openCreate() { this.panel = 'create'; },
        openEdit(criticalPosition) {
            this.editAction = criticalPosition.updateUrl;
            this.editForm = criticalPosition;
            this.panel = 'edit';
        },
        openDetail(criticalPosition) {
            this.detail = criticalPosition;
            this.panel = 'detail';
        },
        closePanel() { this.panel = null; },
    }" x-effect="document.body.classList.toggle('overflow-hidden', panel !== null)" @keydown.escape.window="closePanel()">
        <x-ui.page-header title="Critical Position" eyebrow="Performance & Talent Management"
            description="Penilaian posisi kritis yang terhubung langsung dengan Master Organization, incumbent, dan Successor View.">
            <x-slot:actions>
                @can('create', \App\Models\CriticalPosition::class)
                    <button type="button" @click="openCreate()" class="ui-button ui-button-primary">Tambah Critical Position</button>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->getBag('default')->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->getBag('default')->first() }}</div>
        @endif

        <section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan Critical Position">
            @foreach ([
                ['label' => 'Total Critical Position', 'value' => $summary['total'], 'class' => 'border-sky-200 bg-sky-50 text-sky-700'],
                ['label' => 'High Criticality', 'value' => $summary['high'], 'class' => 'border-rose-200 bg-rose-50 text-rose-700'],
                ['label' => 'Vacant', 'value' => $summary['vacant'], 'class' => 'border-amber-200 bg-amber-50 text-amber-700'],
                ['label' => 'Belum Memiliki Successor', 'value' => $summary['without_successor'], 'class' => 'border-violet-200 bg-violet-50 text-violet-700'],
            ] as $card)
                <article class="rounded-2xl border p-4 shadow-sm {{ $card['class'] }}">
                    <p class="text-xs font-bold uppercase tracking-wide">{{ $card['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold">{{ number_format($card['value']) }}</p>
                </article>
            @endforeach
        </section>

        <section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="GET" action="{{ route('performance-talent.critical-positions.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="block text-sm font-semibold text-slate-700 xl:col-span-2">Pencarian
                    <input type="search" name="search" value="{{ $search }}" placeholder="Nama, kode, atau SAP Position ID"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                </label>
                <label class="block text-sm font-semibold text-slate-700">Direktorat
                    <select name="directorate_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Direktorat</option>
                        @foreach ($directorates as $directorate)
                            <option value="{{ $directorate->id }}" @selected($directorateId == $directorate->id)>{{ $directorate->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Department
                    <select name="department_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($departmentId == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Level Jabatan
                    <select name="job_level" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Level</option>
                        @foreach ($jobLevels as $level)
                            <option value="{{ $level }}" @selected($jobLevel === $level)>{{ $level }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Criticality
                    <select name="criticality" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Criticality</option>
                        @foreach (\App\Enums\CriticalPositionCriticality::cases() as $option)
                            <option value="{{ $option->value }}" @selected($criticality === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Coverage
                    <select name="coverage_status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Coverage</option>
                        @foreach (\App\Enums\CriticalPositionCoverageStatus::cases() as $option)
                            <option value="{{ $option->value }}" @selected($coverageStatus === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-slate-700">Status Successor
                    <select name="successor_status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Status</option>
                        <option value="available" @selected($successorStatus === 'available')>Tersedia</option>
                        <option value="missing" @selected($successorStatus === 'missing')>Belum tersedia</option>
                    </select>
                </label>
                <div class="flex items-end gap-2 md:col-span-2 xl:col-span-4 xl:justify-end">
                    <a href="{{ route('performance-talent.critical-positions.index') }}" class="ui-button ui-button-secondary">Reset</a>
                    <button class="ui-button ui-button-primary">Terapkan Filter</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
                <h3 class="font-display text-lg font-bold text-slate-950">Daftar Critical Position</h3>
                <p class="mt-1 text-sm text-slate-500">Struktur organisasi, incumbent, coverage, dan successor selalu mengikuti data sumber terbaru.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1780px] text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase text-slate-500">
                        <tr>
                            @foreach (['No', 'Position', 'SAP Position ID', 'Level', 'Department/Direktorat', 'Criticality', 'Incumbent', 'Formasi', 'Coverage', 'Successor', 'Review Berikutnya', 'Aksi'] as $column)
                                <th class="px-3 py-3 {{ in_array($column, ['No', 'Formasi', 'Aksi'], true) ? 'text-center' : '' }}">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($criticalPositions as $criticalPosition)
                            @php
                                $position = $criticalPosition->position;
                                $department = $position->unit->department;
                                $coverage = $position->coverageStatus();
                                $incumbents = $position->employees;
                                $successors = $position->successors;
                                $successorUrl = route('performance-talent.successors.index', ['position' => $position->id]);
                                $rowData = [
                                    'id' => (string) $criticalPosition->id,
                                    'position_id' => (string) $position->id,
                                    'position_name' => $position->name,
                                    'position_code' => $position->code,
                                    'external_position_id' => $position->external_position_id ?: '—',
                                    'active_label' => $position->active ? 'Aktif' : 'Nonaktif',
                                    'job_level' => $position->job_level,
                                    'section' => $position->section?->name ?: '—',
                                    'unit' => $position->unit->name,
                                    'department' => $department->name,
                                    'directorate' => $department->directorate?->name ?: '—',
                                    'criticality' => $criticalPosition->criticality->value,
                                    'criticality_label' => $criticalPosition->criticality->label(),
                                    'assessment_reason' => $criticalPosition->assessment_reason,
                                    'assessed_at' => $criticalPosition->assessed_at->format('Y-m-d'),
                                    'assessed_at_label' => $criticalPosition->assessed_at->format('d M Y'),
                                    'review_due_at' => $criticalPosition->review_due_at?->format('Y-m-d') ?? '',
                                    'review_due_at_label' => $criticalPosition->review_due_at?->format('d M Y') ?? 'Belum dijadwalkan',
                                    'assessor' => $criticalPosition->assessor?->name ?: 'Tidak tersedia',
                                    'notes' => $criticalPosition->notes ?? '',
                                    'formation_count' => $position->formation_count,
                                    'coverage' => $coverage->label(),
                                    'incumbents' => $incumbents->map(fn ($employee) => [
                                        'name' => filled($employee->name) ? $employee->name : 'Nama belum tersedia',
                                        'personal_number' => $employee->personal_number ?: '—',
                                        'sap' => $employee->sap ?: '—',
                                        'position' => $position->name,
                                    ])->values(),
                                    'successor_count' => $position->successorCount(),
                                    'ready_now_count' => $position->readyNowSuccessorCount(),
                                    'best_readiness' => $position->bestSuccessorReadiness() ? ucfirst($position->bestSuccessorReadiness()) : 'Belum tersedia',
                                    'successors' => $canViewSuccessors ? $successors->map(fn ($successor) => [
                                        'name' => $successor->name,
                                        'sap' => $successor->sap ?: '—',
                                        'current_position' => $successor->current_position ?: '—',
                                        'readiness' => ucfirst((string) $successor->succession_readiness),
                                    ])->values() : [],
                                    'successorUrl' => $canViewSuccessors ? $successorUrl : null,
                                    'updateUrl' => route('performance-talent.critical-positions.update', $criticalPosition),
                                ];
                            @endphp
                            <tr class="align-top hover:bg-slate-50/60">
                                <td class="px-3 py-4 text-center font-medium text-slate-500">{{ $criticalPositions->firstItem() + $loop->index }}</td>
                                <td class="px-3 py-4"><p class="font-semibold text-slate-900">{{ $position->name }}</p><p class="mt-1 text-slate-500">{{ $position->code }}</p></td>
                                <td class="px-3 py-4 font-medium text-slate-700">{{ $position->external_position_id ?: '—' }}</td>
                                <td class="px-3 py-4 text-slate-600">{{ $position->job_level }}</td>
                                <td class="px-3 py-4"><p class="text-slate-700">{{ $department->name }}</p><p class="mt-1 text-slate-500">{{ $department->directorate?->name ?: '—' }}</p></td>
                                <td class="px-3 py-4"><x-ui.status-badge :tone="$criticalPosition->criticality->tone()">{{ $criticalPosition->criticality->label() }}</x-ui.status-badge></td>
                                <td class="px-3 py-4">
                                    @if ($incumbents->isEmpty())
                                        <span class="text-slate-500">Belum ada incumbent</span>
                                    @else
                                        <p class="font-semibold text-slate-800">{{ filled($incumbents->first()->name) ? $incumbents->first()->name : 'Nama belum tersedia' }}</p>
                                        <p class="mt-1 text-slate-500">Personal Number: {{ $incumbents->first()->personal_number ?: '—' }}</p>
                                        @if ($incumbents->count() > 1)<button type="button" @click="openDetail(@js($rowData))" class="mt-1 font-semibold text-sky-700">+{{ $incumbents->count() - 1 }} incumbent lain</button>@endif
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-center font-semibold text-slate-700">{{ $incumbents->count() }}/{{ $position->formation_count }}</td>
                                <td class="px-3 py-4"><x-ui.status-badge :tone="$coverage->tone()">{{ $coverage->label() }}</x-ui.status-badge></td>
                                <td class="px-3 py-4">
                                    <p class="font-semibold {{ $successors->isEmpty() ? 'text-rose-700' : 'text-slate-800' }}">{{ $successors->count() }} kandidat</p>
                                    <p class="mt-1 text-slate-500">Ready Now: {{ $position->readyNowSuccessorCount() }}</p>
                                    <p class="text-slate-500">Terbaik: {{ $position->bestSuccessorReadiness() ? ucfirst($position->bestSuccessorReadiness()) : '—' }}</p>
                                    @if ($canViewSuccessors)
                                        <a href="{{ $successorUrl }}" class="mt-2 inline-block font-semibold text-sky-700">Lihat Successor</a>
                                        @if ($successors->isEmpty() && $canCreateSuccessors)
                                            <a href="{{ route('performance-talent.successors.index', ['position' => $position->id, 'create' => 1]) }}" class="ml-2 mt-2 inline-block font-semibold text-emerald-700">Tambah Successor</a>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-slate-600">{{ $criticalPosition->review_due_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-3 py-4">
                                    <div class="flex items-center justify-center gap-2 whitespace-nowrap">
                                        <button type="button" @click="openDetail(@js($rowData))" class="font-semibold text-sky-700 hover:text-sky-900">Detail</button>
                                        @can('update', $criticalPosition)
                                            <button type="button" @click="openEdit(@js($rowData))" class="font-semibold text-amber-700 hover:text-amber-900">Edit</button>
                                        @endcan
                                        @can('delete', $criticalPosition)
                                            <form method="POST" action="{{ route('performance-talent.critical-positions.destroy', $criticalPosition) }}" data-confirm-delete data-confirm-submit data-confirm-title="Hapus Critical Position ini?" data-confirm-text="Hanya data penilaian yang dihapus; Master Position, Employee, dan Successor tetap tersimpan.">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="font-semibold text-red-600 hover:text-red-800">Hapus</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><x-ui.empty-state colspan="12" title="Belum ada Critical Position" description="Tambahkan penilaian Position atau sesuaikan filter pencarian." /></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $criticalPositions->links() }}</div>
        </section>

        <div x-cloak x-show="panel === 'detail'" class="fixed inset-0 z-[70]" role="presentation">
            <button type="button" @click="closePanel()" aria-label="Tutup detail" class="absolute inset-0 h-full w-full bg-slate-950/60 backdrop-blur-sm"></button>
            <section class="fixed inset-y-0 right-0 z-[71] flex w-full max-w-2xl flex-col overflow-hidden border-l border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true">
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-sky-700">Detail Critical Position</p><h3 class="mt-1 font-display text-xl font-bold text-slate-950" x-text="detail?.position_name"></h3></div>
                    <button type="button" @click="closePanel()" class="ui-button ui-button-secondary">Tutup</button>
                </div>
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto bg-slate-50 p-5">
                    <section class="rounded-xl border border-slate-200 bg-white p-4"><h4 class="font-bold text-slate-900">Identitas & Struktur</h4><dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">Kode internal</dt><dd class="font-semibold" x-text="detail?.position_code"></dd></div><div><dt class="text-slate-500">SAP Position ID</dt><dd class="font-semibold" x-text="detail?.external_position_id"></dd></div><div><dt class="text-slate-500">Level</dt><dd x-text="detail?.job_level"></dd></div><div><dt class="text-slate-500">Section</dt><dd x-text="detail?.section"></dd></div><div><dt class="text-slate-500">Unit</dt><dd x-text="detail?.unit"></dd></div><div><dt class="text-slate-500">Department</dt><dd x-text="detail?.department"></dd></div><div><dt class="text-slate-500">Direktorat</dt><dd x-text="detail?.directorate"></dd></div><div><dt class="text-slate-500">Status Position</dt><dd x-text="detail?.active_label"></dd></div></dl></section>
                    <section class="rounded-xl border border-slate-200 bg-white p-4"><h4 class="font-bold text-slate-900">Penilaian</h4><dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">Criticality</dt><dd class="font-semibold" x-text="detail?.criticality_label"></dd></div><div><dt class="text-slate-500">Penilai</dt><dd x-text="detail?.assessor"></dd></div><div><dt class="text-slate-500">Tanggal penilaian</dt><dd x-text="detail?.assessed_at_label"></dd></div><div><dt class="text-slate-500">Review berikutnya</dt><dd x-text="detail?.review_due_at_label"></dd></div></dl><div class="mt-3"><p class="text-xs font-semibold text-slate-500">Alasan</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700" x-text="detail?.assessment_reason"></p></div><div class="mt-3"><p class="text-xs font-semibold text-slate-500">Catatan</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700" x-text="detail?.notes || '—'"></p></div></section>
                    <section class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center justify-between"><h4 class="font-bold text-slate-900">Incumbent</h4><span class="text-sm font-semibold text-slate-600"><span x-text="(detail?.incumbents || []).length"></span>/<span x-text="detail?.formation_count"></span> · <span x-text="detail?.coverage"></span></span></div><template x-for="incumbent in (detail?.incumbents || [])" :key="`${incumbent.sap}-${incumbent.personal_number}`"><article class="mt-3 rounded-lg border border-slate-200 p-3 text-sm"><p class="font-semibold text-slate-900" x-text="incumbent.name"></p><p class="text-slate-500">Personal Number: <span x-text="incumbent.personal_number"></span> · SAP Pegawai: <span x-text="incumbent.sap"></span></p><p class="text-slate-500" x-text="incumbent.position"></p></article></template><p x-show="(detail?.incumbents || []).length === 0" class="mt-3 text-sm text-slate-500">Posisi sedang Vacant.</p></section>
                    <section class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center justify-between"><h4 class="font-bold text-slate-900">Successor</h4><span class="text-sm text-slate-600"><span x-text="detail?.successor_count"></span> kandidat · <span x-text="detail?.ready_now_count"></span> Ready Now</span></div><p class="mt-1 text-sm text-slate-500">Readiness terbaik: <span x-text="detail?.best_readiness"></span></p><template x-for="successor in (detail?.successors || [])" :key="`${successor.sap}-${successor.name}`"><article class="mt-3 rounded-lg border border-slate-200 p-3 text-sm"><p class="font-semibold text-slate-900" x-text="successor.name"></p><p class="text-slate-500">SAP: <span x-text="successor.sap"></span> · <span x-text="successor.current_position"></span></p><p class="font-medium text-sky-700" x-text="successor.readiness"></p></article></template><p x-show="detail?.successor_count === 0" class="mt-3 text-sm font-medium text-rose-700">Posisi belum memiliki successor.</p>@if ($canViewSuccessors)<a x-show="detail?.successorUrl" x-bind:href="detail?.successorUrl" class="mt-3 inline-block font-semibold text-sky-700">Lihat Successor</a>@endif</section>
                </div>
            </section>
        </div>

        @can('create', \App\Models\CriticalPosition::class)
            <div x-cloak x-show="panel === 'create'" class="fixed inset-0 z-[70]" role="presentation">
                <button type="button" @click="closePanel()" class="absolute inset-0 h-full w-full bg-slate-950/60 backdrop-blur-sm" aria-label="Tutup formulir"></button>
                <section class="fixed inset-y-0 left-0 z-[71] flex w-full max-w-2xl flex-col overflow-hidden border-r border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true">
                    <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4"><div><p class="text-xs font-bold uppercase tracking-wide text-sky-700">Performance & Talent Management</p><h3 class="mt-1 font-display text-xl font-bold">Tambah Critical Position</h3><p class="mt-1 text-sm text-slate-500">Pilih Master Position dan isi data penilaiannya.</p></div><button type="button" @click="closePanel()" class="ui-button ui-button-secondary">Tutup</button></div>
                    @include('performance-talent.critical-positions._form', ['mode' => 'create', 'action' => route('performance-talent.critical-positions.store'), 'errorBag' => 'createCriticalPosition'])
                </section>
            </div>
        @endcan

        @if (auth()->user()->hasPermission('critical-positions.update'))
            <div x-cloak x-show="panel === 'edit'" class="fixed inset-0 z-[70]" role="presentation">
                <button type="button" @click="closePanel()" class="absolute inset-0 h-full w-full bg-slate-950/60 backdrop-blur-sm" aria-label="Tutup formulir"></button>
                <section class="fixed inset-y-0 left-0 z-[71] flex w-full max-w-2xl flex-col overflow-hidden border-r border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true">
                    <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4"><div><p class="text-xs font-bold uppercase tracking-wide text-sky-700">Performance & Talent Management</p><h3 class="mt-1 font-display text-xl font-bold">Ubah Critical Position</h3><p class="mt-1 text-sm text-slate-500">Struktur organisasi dan incumbent tetap dibaca dari sumber terbaru.</p></div><button type="button" @click="closePanel()" class="ui-button ui-button-secondary">Tutup</button></div>
                    @include('performance-talent.critical-positions._form', ['mode' => 'edit', 'action' => null, 'errorBag' => 'updateCriticalPosition'])
                </section>
            </div>
        @endif
    </div>
@endsection
