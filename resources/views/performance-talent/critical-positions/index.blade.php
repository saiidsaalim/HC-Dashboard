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
            'id' => $oldCriticalPositionId,
            'position' => old('position', ''),
            'level' => old('level', ''),
            'department' => old('department', ''),
            'directorate' => old('directorate', ''),
            'criticality' => old('criticality', ''),
            'incumbent' => old('incumbent', ''),
            'sap' => old('sap', ''),
            'status' => old('status', ''),
        ];
    @endphp

    <div x-data="{
        panel: @js($initialPanel),
        editAction: @js($initialEditAction),
        editForm: @js($initialEditForm),
        openCreate() {
            this.panel = 'create';
        },
        openEdit(criticalPosition) {
            this.editAction = criticalPosition.updateUrl;
            this.editForm = criticalPosition;
            this.panel = 'edit';
        },
        closePanel() {
            this.panel = null;
        },
    }" x-effect="document.body.classList.toggle('overflow-hidden', panel !== null)"
        @keydown.escape.window="closePanel()">
        <x-ui.page-header title="Critical Position" eyebrow="Performance & Talent Management"
            description="Pantau posisi kritis, tingkat prioritas, dan status keterisian dalam satu tampilan.">
            <x-slot:actions>
                @can('create', \App\Models\CriticalPosition::class)
                    <button type="button" @click="openCreate()" class="ui-button ui-button-primary">Tambah Critical Position</button>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->getBag('default')->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $errors->getBag('default')->first() }}
            </div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-4 sm:px-5">
                <h3 class="font-display text-lg font-bold text-slate-950">Daftar Critical Position</h3>
                <p class="mt-1 text-sm text-slate-500">Data posisi yang memiliki dampak penting terhadap keberlangsungan organisasi.</p>
            </div>

            <div class="overflow-x-auto xl:overflow-x-visible">
                <table class="w-full min-w-[1000px] table-fixed text-left text-xs xl:min-w-0">
                    <colgroup>
                        <col class="w-[4%]">
                        <col class="w-[15%]">
                        <col class="w-[8%]">
                        <col class="w-[14%]">
                        <col class="w-[14%]">
                        <col class="w-[7%]">
                        <col class="w-[15%]">
                        <col class="w-[7%]">
                        <col class="w-[8%]">
                        <col class="w-[8%]">
                    </colgroup>
                    <thead class="text-[10px] uppercase lg:text-xs">
                        <tr>
                            <th scope="col" class="break-words px-1 py-3 text-center sm:px-2">No</th>
                            <th scope="col" class="break-words px-1 py-3 sm:px-2">Position</th>
                            <th scope="col" class="break-words px-1 py-3 sm:px-2">Level</th>
                            <th scope="col" class="break-words px-1 py-3 sm:px-2">Department</th>
                            <th scope="col" class="break-words px-1 py-3 sm:px-2">Direktorat</th>
                            <th scope="col" class="break-words px-1 py-3 text-center sm:px-2">Criticality</th>
                            <th scope="col" class="break-words px-1 py-3 sm:px-2">Incumbent</th>
                            <th scope="col" class="break-words px-1 py-3 text-center sm:px-2">SAP</th>
                            <th scope="col" class="break-words px-1 py-3 text-center sm:px-2">Status</th>
                            <th scope="col" class="break-words px-1 py-3 text-center sm:px-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($criticalPositions as $criticalPosition)
                            <tr>
                                <td class="break-words px-1 py-4 text-center font-medium text-slate-500 sm:px-2">
                                    {{ $criticalPositions->firstItem() + $loop->index }}
                                </td>
                                <td class="break-words px-1 py-4 font-semibold text-slate-800 sm:px-2">{{ $criticalPosition->position }}</td>
                                <td class="break-words px-1 py-4 text-slate-600 sm:px-2">{{ $criticalPosition->level }}</td>
                                <td class="break-words px-1 py-4 text-slate-600 sm:px-2">{{ $criticalPosition->department }}</td>
                                <td class="break-words px-1 py-4 text-slate-600 sm:px-2">{{ $criticalPosition->directorate }}</td>
                                <td class="break-words px-1 py-4 text-center sm:px-2">
                                    <x-ui.status-badge :tone="$criticalPosition->criticality->tone()">
                                        {{ $criticalPosition->criticality->value }}
                                    </x-ui.status-badge>
                                </td>
                                <td class="break-words px-1 py-4 text-slate-600 sm:px-2">{{ $criticalPosition->incumbent ?? 'Belum tersedia' }}</td>
                                <td class="break-all px-1 py-4 text-center font-medium text-slate-600 sm:px-2">{{ $criticalPosition->sap ?? '—' }}</td>
                                <td class="break-words px-1 py-4 text-center sm:px-2">
                                    <x-ui.status-badge :tone="$criticalPosition->status->tone()">
                                        {{ $criticalPosition->status->value }}
                                    </x-ui.status-badge>
                                </td>
                                <td class="px-1 py-4 text-center sm:px-2">
                                    <div class="inline-flex items-center justify-center gap-2 whitespace-nowrap">
                                        @can('update', $criticalPosition)
                                            <button type="button"
                                                @click="openEdit(@js([
                                                    'id' => (string) $criticalPosition->id,
                                                    'position' => $criticalPosition->position,
                                                    'level' => $criticalPosition->level,
                                                    'department' => $criticalPosition->department,
                                                    'directorate' => $criticalPosition->directorate,
                                                    'criticality' => $criticalPosition->criticality->value,
                                                    'incumbent' => $criticalPosition->incumbent ?? '',
                                                    'sap' => $criticalPosition->sap ?? '',
                                                    'status' => $criticalPosition->status->value,
                                                    'updateUrl' => route('performance-talent.critical-positions.update', $criticalPosition),
                                                ]))"
                                                class="font-semibold text-sky-700 hover:text-sky-900">Edit</button>
                                        @endcan
                                        @can('delete', $criticalPosition)
                                            <form method="POST"
                                                action="{{ route('performance-talent.critical-positions.destroy', $criticalPosition) }}"
                                                data-confirm-delete data-confirm-submit
                                                data-confirm-title="Hapus Critical Position ini?"
                                                data-confirm-text="Data yang dihapus tidak dapat dikembalikan."
                                                data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-semibold text-red-600 hover:text-red-800">Hapus</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <x-ui.empty-state colspan="10" title="Belum ada Critical Position"
                                    description="Tambahkan Critical Position untuk mulai mengelola posisi kritis organisasi." />
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 px-4 py-3">{{ $criticalPositions->links() }}</div>
        </section>

        @can('create', \App\Models\CriticalPosition::class)
            <div x-cloak x-show="panel === 'create'" class="fixed inset-0 z-[70]" role="presentation">
                <button type="button" @click="closePanel()" aria-label="Tutup formulir tambah Critical Position"
                    class="absolute inset-0 h-full w-full bg-slate-950/60 backdrop-blur-sm"></button>
                <section x-show="panel === 'create'"
                    x-transition:enter="transition-transform duration-300 ease-out"
                    x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition-transform duration-200 ease-in"
                    x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                    class="fixed inset-y-0 left-0 z-[71] flex w-full max-w-xl flex-col overflow-hidden border-r border-slate-200 bg-white shadow-2xl"
                    role="dialog" aria-modal="true" aria-labelledby="create-critical-position-title">
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-6 sm:py-5">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Performance & Talent Management</p>
                            <h3 id="create-critical-position-title" class="mt-1 font-display text-xl font-bold text-slate-950">Tambah Critical Position</h3>
                            <p class="mt-1 text-sm text-slate-500">Tambahkan posisi kritis dan status keterisiannya.</p>
                        </div>
                        <button type="button" @click="closePanel()" aria-label="Tutup formulir"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    @include('performance-talent.critical-positions._form', [
                        'mode' => 'create',
                        'action' => route('performance-talent.critical-positions.store'),
                        'errorBag' => 'createCriticalPosition',
                    ])
                </section>
            </div>
        @endcan

        @if (auth()->user()->hasPermission('critical-positions.update'))
            <div x-cloak x-show="panel === 'edit'" class="fixed inset-0 z-[70]" role="presentation">
                <button type="button" @click="closePanel()" aria-label="Tutup formulir edit Critical Position"
                    class="absolute inset-0 h-full w-full bg-slate-950/60 backdrop-blur-sm"></button>
                <section x-show="panel === 'edit'"
                    x-transition:enter="transition-transform duration-300 ease-out"
                    x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition-transform duration-200 ease-in"
                    x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
                    class="fixed inset-y-0 left-0 z-[71] flex w-full max-w-xl flex-col overflow-hidden border-r border-slate-200 bg-white shadow-2xl"
                    role="dialog" aria-modal="true" aria-labelledby="edit-critical-position-title">
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-6 sm:py-5">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Performance & Talent Management</p>
                            <h3 id="edit-critical-position-title" class="mt-1 font-display text-xl font-bold text-slate-950">Ubah Critical Position</h3>
                            <p class="mt-1 text-sm text-slate-500">Perbarui posisi kritis tanpa meninggalkan halaman daftar.</p>
                        </div>
                        <button type="button" @click="closePanel()" aria-label="Tutup formulir"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                    @include('performance-talent.critical-positions._form', [
                        'mode' => 'edit',
                        'action' => null,
                        'errorBag' => 'updateCriticalPosition',
                    ])
                </section>
            </div>
        @endif
    </div>
@endsection
