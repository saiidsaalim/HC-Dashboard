@extends('layouts.app')

@section('page-title', 'Mutasi')

@section('content')
    @php
        $canCreateMutations = auth()->user()->hasPermission('mutations.create');
        $canManageMutationApprovals = auth()->user()->hasPermission('mutations.manage-approval-settings');
        $canUpdateMutations = auth()->user()->hasPermission('mutations.update');
        $canDeleteMutations = auth()->user()->hasPermission('mutations.delete');
        $canApproveMutations = auth()->user()->hasPermission('mutations.approve');
        $canPrintMutations = auth()->user()->hasPermission('mutations.print');
        $hasDeletableMutations = $canDeleteMutations
            && $mutations->contains(fn ($mutation): bool => auth()->user()->can('delete', $mutation));
        $selectedBatch = $batchId !== null ? $batchOptions->firstWhere('id', $batchId) : null;
    @endphp
    <div data-mutation-journey x-data="{
        open: @js(old('_form') === 'create-mutation'),
        openModal() {
            this.open = true;
            this.$nextTick(() => this.$refs.mutationModal?.querySelector('[type=search]')?.focus());
        },
        closeModal() {
            this.open = false;
            this.$nextTick(() => this.$refs.openMutation?.focus());
        },
        trapFocus(event) {
            const focusable = [...this.$refs.mutationModal.querySelectorAll(
                'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]',
            )].filter((element) => element.offsetParent !== null);
            if (focusable.length === 0) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        },
    }" x-init="if (open) $nextTick(() => $refs.mutationModal?.querySelector('[type=search]')?.focus())">
        <x-ui.page-header title="Daftar Mutasi" eyebrow="Talent movement"
            description="Buat dan pantau perpindahan pegawai.">
            <x-slot:actions>
                @if ($canManageMutationApprovals)
                    <a href="{{ route('mutasi.approval-settings.edit') }}"
                        class="ui-button ui-button-secondary">
                        Pengaturan Persetujuan
                    </a>
                @endif
                @if ($canCreateMutations)
                    <a href="{{ route('mutasi.batches.create') }}" class="ui-button ui-button-secondary">
                        <x-dashboard-icon name="users" class="h-4 w-4" />
                        Buat Batch Mutasi
                    </a>
                    <button type="button" x-ref="openMutation" @click="openModal()"
                        class="ui-button ui-button-primary">
                        <span class="text-lg leading-none" aria-hidden="true">+</span>
                        Buat Mutasi
                    </button>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div class="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm font-semibold text-emerald-800 sm:px-5"
                role="status">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-sm" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="m5 10 3 3 7-7" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Data mutasi tidak dapat diproses.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!$approvalConfigurationComplete)
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm font-medium text-amber-800 sm:px-5">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700" aria-hidden="true">!</span>
                <span class="pt-1">Konfigurasi tiga approver Mutasi belum lengkap. Pengajuan dan persetujuan belum dapat diproses.</span>
            </div>
        @endif

        @if ($canCreateMutations)
        <div x-cloak x-show="open" x-transition.opacity @keydown.escape.window="if (open) closeModal()"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-slate-950/60 p-2 backdrop-blur-sm sm:p-4"
            @click.self="closeModal()">
            <section x-ref="mutationModal" @keydown.tab="trapFocus($event)"
                class="flex max-h-[calc(100dvh-1rem)] w-full max-w-7xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:max-h-[calc(100dvh-2rem)] sm:rounded-3xl"
                role="dialog" aria-modal="true" aria-labelledby="manual-mutation-title" aria-describedby="manual-mutation-description">
                <div class="flex flex-none items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:px-6 sm:py-5 lg:px-7">
                    <div>
                        <h3 id="manual-mutation-title" class="font-display text-xl font-bold text-slate-950 sm:text-2xl">Tambah Data Mutasi</h3>
                        <p id="manual-mutation-description" class="mt-1 text-sm leading-6 text-slate-500">
                            Lengkapi data utama dan bandingkan organisasi saat ini dengan organisasi tujuan sebelum menyimpan.
                        </p>
                    </div>
                    <button type="button" @click="closeModal()" aria-label="Tutup modal Tambah Data Mutasi"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-2xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">&times;</button>
                </div>
                @include('pages.mutasi._form', [
                    'action' => route('mutasi.store'), 'method' => 'POST', 'movement' => null,
                    'formKey' => 'create-mutation', 'submitLabel' => 'Simpan Draft Mutasi',
                ])
            </section>
        </div>
        @endif
    </div>

    <div class="relative grid gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Ringkasan mutasi">
        <article class="relative z-10 flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                <x-dashboard-icon name="document" class="h-7 w-7" />
            </span>
            <div>
                <p class="text-sm font-semibold text-slate-500">Total data mutasi</p>
                <p class="mt-1 font-display text-3xl font-bold text-slate-950">{{ $totalMutations }}</p>
            </div>
        </article>
        <span class="absolute left-1/3 top-1/2 z-20 hidden h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-sky-700 shadow-sm xl:flex" aria-hidden="true">&rarr;</span>
        <article class="relative z-10 flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-amber-100">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="8.5" />
                    <path d="M12 7v5l3.5 2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-slate-500">Menunggu persetujuan</p>
                <p class="mt-1 font-display text-3xl font-bold text-amber-600">{{ $pendingMutations }}</p>
            </div>
        </article>
        <span class="absolute left-2/3 top-1/2 z-20 hidden h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-sky-700 shadow-sm xl:flex" aria-hidden="true">&rarr;</span>
        <article class="relative z-10 flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2 xl:col-span-1">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="12" r="8.5" />
                    <path d="m8 12 2.5 2.5L16 9" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-slate-500">Disetujui tahun ini</p>
                <p class="mt-1 font-display text-3xl font-bold text-emerald-600">{{ $approvedMutationsThisYear }}</p>
            </div>
        </article>
    </div>

    @if ($mutationBatches->isNotEmpty())
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="recent-mutation-batches-title">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 id="recent-mutation-batches-title" class="font-display text-lg font-bold text-slate-950">Batch Mutasi Terbaru</h3>
                    <p class="mt-1 text-xs text-slate-500">Pantau beberapa item Mutasi dalam satu kelompok pengajuan.</p>
                </div>
                @if ($canCreateMutations)
                    <a href="{{ route('mutasi.batches.create') }}" class="text-sm font-bold text-sky-700 hover:text-sky-800">Buat batch baru</a>
                @endif
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($mutationBatches as $batch)
                    <a href="{{ route('mutasi', ['batch' => $batch->id]) }}#data-mutasi"
                        @if ($batchId === $batch->id) aria-current="true" @endif
                        @class([
                            'rounded-xl border p-4 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500',
                            'border-sky-400 bg-sky-50 ring-1 ring-sky-200' => $batchId === $batch->id,
                            'border-slate-200 hover:border-sky-300 hover:bg-sky-50/40' => $batchId !== $batch->id,
                        ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wider text-sky-700">{{ $batch->batch_number }}</p>
                                <p class="mt-1 truncate font-display font-bold text-slate-900">{{ $batch->name }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $batch->mutations_count }} item</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
                            <span class="rounded-full bg-sky-50 px-2 py-1 text-sky-700">{{ $batch->draft_mutations_count }} Draft</span>
                            <span class="rounded-full bg-amber-50 px-2 py-1 text-amber-700">{{ $batch->pending_mutations_count }} Menunggu</span>
                            <span class="rounded-full bg-rose-50 px-2 py-1 text-rose-700">{{ $batch->returned_mutations_count }} Kembali</span>
                            <span class="rounded-full bg-emerald-50 px-2 py-1 text-emerald-700">{{ $batch->completed_mutations_count }} Selesai</span>
                        </div>
                        <span class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-sky-700">
                            Lihat daftar pegawai <span aria-hidden="true">&darr;</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
    <section id="data-mutasi" data-mutation-journey class="mt-6 scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex w-full flex-col gap-4 border-b border-slate-200 p-4 sm:p-5 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                    <x-dashboard-icon name="arrows" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="font-display text-lg font-bold text-slate-950">
                        {{ $selectedBatch ? 'Pegawai dalam Batch '.$selectedBatch->batch_number : 'Data mutasi terbaru' }}
                    </h3>
                    @if ($selectedBatch)
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $selectedBatch->name }} &middot; {{ $mutations->total() }} pegawai ditampilkan.
                            <a href="{{ route('mutasi') }}#data-mutasi" class="ml-1 font-bold text-sky-700 hover:text-sky-800">Tampilkan semua pegawai</a>
                        </p>
                    @else
                        <p class="mt-0.5 text-xs text-slate-500">Semua pegawai yang dimutasi ditampilkan saat halaman pertama dibuka.</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-col items-stretch gap-3 lg:flex-row lg:flex-wrap lg:items-center lg:justify-end">
                <form method="GET" action="{{ route('mutasi') }}" class="relative w-full lg:w-80">
                    @if ($filter !== 'all')
                        <input type="hidden" name="status" value="{{ $filter }}">
                    @endif
                    @if ($batchId !== null)
                        <input type="hidden" name="batch" value="{{ $batchId }}">
                    @endif
                    <label for="mutation-quick-search" class="sr-only">Cari data mutasi</label>
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <circle cx="8.5" cy="8.5" r="5.5" />
                        <path d="m13 13 4 4" stroke-linecap="round" />
                    </svg>
                    <input id="mutation-quick-search" name="search" value="{{ $search }}" type="search"
                        placeholder="Cari pegawai, SAP, posisi, atau usulan..."
                        class="ui-input w-full py-2.5 pl-10 pr-4 text-sm">
                </form>
                <x-ui.filter-panel id="mutation-filters" title="Filter Mutasi" class="w-full sm:w-auto"
                    :action="route('mutasi')" :reset-href="route('mutasi')" :active="$search !== '' || $filter !== 'all' || $batchId !== null">
                    @if ($search !== '')
                        <input type="hidden" name="search" value="{{ $search }}">
                    @endif
                    <label for="mutation-status" class="block text-sm font-semibold text-slate-700">Status
                        <select id="mutation-status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="all" @selected($filter === 'all')>Semua</option>
                            <option value="draft" @selected($filter === 'draft')>Draft</option>
                            <option value="waiting" @selected($filter === 'waiting')>Menunggu</option>
                            <option value="returned" @selected($filter === 'returned')>Dikembalikan</option>
                            <option value="approved" @selected($filter === 'approved')>Disetujui, menunggu TMT</option>
                            <option value="final" @selected($filter === 'final')>Sudah diterapkan</option>
                        </select>
                    </label>
                    <label for="mutation-batch" class="block text-sm font-semibold text-slate-700">Batch
                        <select id="mutation-batch" name="batch" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="">Semua Batch</option>
                            @foreach ($batchOptions as $batchOption)
                                <option value="{{ $batchOption->id }}" @selected($batchId === $batchOption->id)>{{ $batchOption->batch_number }} · {{ $batchOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </x-ui.filter-panel>
                <div class="flex flex-wrap items-center justify-between gap-3 sm:justify-end">
                    @if ($canDeleteMutations)
                        <label class="flex items-center gap-2 text-sm font-semibold {{ $hasDeletableMutations ? 'cursor-pointer text-slate-600' : 'cursor-not-allowed text-slate-400' }}">
                            <input id="select-all-mutations" type="checkbox"
                                @disabled(!$hasDeletableMutations)
                                onchange="document.querySelectorAll('.mutation-checkbox:not(:disabled)').forEach((checkbox) => checkbox.checked = this.checked)"
                                class="rounded border-slate-300 text-sky-700 focus:ring-sky-500 disabled:cursor-not-allowed disabled:opacity-50">
                            Pilih semua
                        </label>
                        <button type="submit" form="bulk-delete-form"
                            @disabled(!$hasDeletableMutations)
                            class="ui-button ui-button-danger">
                            Hapus terpilih
                        </button>
                    @endif
                </div>
            </div>
        </div>
        <form id="bulk-delete-form" method="POST" action="{{ route('mutasi.destroy-many') }}"
            data-confirm-submit data-confirm-title="Hapus data terpilih?" data-confirm-text="Data yang dihapus tidak dapat dikembalikan."
            data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626" data-confirm-require-checkbox=".mutation-checkbox"
            data-confirm-empty-text="Centang minimal satu data pegawai.">
            @csrf
            @method('DELETE')
            <div data-mutation-journey-table-wrap class="overflow-x-auto px-3 pb-2 sm:px-4">
                <table class="w-full min-w-[73rem] table-fixed text-left text-sm whitespace-nowrap mutation-journey-table">
                    <x-personnel-movement-table-columns :show-selection-column="$canDeleteMutations" />
                    <thead class="text-xs font-bold uppercase tracking-wider text-slate-600">
                        <tr>
                            @if ($canDeleteMutations)
                                <th class="w-12 px-4 py-3 text-center align-middle">Pilih</th>
                            @endif
                            <th class="w-24 px-4 py-3">SAP</th>
                            <th class="w-44 px-4 py-3">Pegawai</th>
                            <th class="w-52 px-4 py-3">Posisi Saat Ini</th>
                            <th class="w-52 px-4 py-3">Usulan</th>
                            <th class="w-60 px-4 py-3">Workflow</th>
                            <th class="w-48 px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($mutations as $mutation)
                            <tr tabindex="0" role="button" aria-label="Lihat detail mutasi {{ $mutation->nama }}"
                                class="cursor-pointer transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
                                onclick="if (!event.target.closest('a,button,input,select,textarea,label,form')) document.getElementById('mutation-detail-{{ $mutation->id }}').showModal()"
                                onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a,button,input,select,textarea,label,form')) { event.preventDefault(); document.getElementById('mutation-detail-{{ $mutation->id }}').showModal(); }">
                                @php
                                    $currentRoundApprovals = $mutation->approvals->where('approval_round', $mutation->approval_round);
                                    $approvedCount = $currentRoundApprovals->where('status', 'Approved')->unique('manager_id')->count();
                                    $activeStep = $mutation->current_approval_step ?? min($approvedCount + 1, 3);
                                    $activeApproval = $mutation->approvals
                                        ->where('approval_round', $mutation->approval_round)
                                        ->firstWhere('step_order', $activeStep);
                                    $activeApprover = $activeApproval?->manager;
                                    $activeApproverName = $activeApproval?->approver_name_snapshot ?? $activeApprover?->name;
                                    $isActiveApprover = $mutation->verification_status === 'Pending'
                                        && $activeApprover?->id === auth()->id();
                                    $latestReturn = $mutation->approvals->where('status', 'Returned')->sortByDesc('id')->first();
                                    $workflowStage = match ($mutation->verification_status) {
                                        'Draft', 'Returned' => 1,
                                        'Pending' => 2,
                                        'Approved', 'Final' => 3,
                                        default => 0,
                                    };
                                @endphp
                                @if ($canDeleteMutations)
                                    <td data-label="Pilih" class="px-4 py-4 text-center align-middle">
                                        @can('delete', $mutation)
                                            <input type="checkbox" name="mutation_ids[]" value="{{ $mutation->id }}"
                                                class="mutation-checkbox rounded border-slate-300 text-sky-700 focus:ring-sky-500"
                                                onchange="document.getElementById('select-all-mutations').checked = document.querySelectorAll('.mutation-checkbox').length > 0 && document.querySelectorAll('.mutation-checkbox:not(:checked)').length === 0">
                                        @else
                                            <input type="checkbox" disabled aria-label="Mutasi {{ $mutation->sap }} tidak dapat dipilih"
                                                class="rounded border-slate-300 text-slate-300 disabled:cursor-not-allowed disabled:opacity-50">
                                        @endcan
                                    </td>
                                @endif
                                <td data-label="SAP" class="px-4 py-4 align-middle">
                                    <span class="font-display text-sm font-bold text-slate-900">{{ $mutation->sap }}</span>
                                    @if ($mutation->batch)
                                        <a href="{{ route('mutasi.batches.show', $mutation->batch) }}" class="mt-1 block text-[10px] font-bold text-sky-700 hover:text-sky-800">{{ $mutation->batch->batch_number }}</a>
                                    @endif
                                </td>
                                <td data-label="Pegawai" class="px-4 py-4 align-middle">
                                    <p class="font-semibold text-slate-900">{{ $mutation->nama }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-500">TMT {{ $mutation->tmt?->format('d M Y') }}</p>
                                </td>
                                <td data-label="Posisi Saat Ini" class="px-3 py-4 align-middle whitespace-normal">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                                        <span class="mb-2 inline-flex rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-500 ring-1 ring-slate-200">Posisi saat ini</span>
                                        <div class="flex items-start gap-2.5">
                                            <x-dashboard-icon name="clipboard" class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" />
                                            <div class="min-w-0">
                                                <p class="font-semibold leading-5 text-slate-800">{{ $mutation->jabatan_lama }}</p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ collect([$mutation->unit_lama, $mutation->departemen_lama])->filter()->unique()->implode(' · ') ?: 'Organisasi tidak tersedia' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Usulan" class="px-3 py-4 align-middle whitespace-normal">
                                    <div class="relative rounded-xl border border-sky-200 bg-sky-50 px-3 py-3">
                                        <span class="mutation-journey-arrow absolute -left-5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full border-4 border-white bg-sky-600 font-bold text-white shadow-sm" aria-hidden="true">&rarr;</span>
                                        <span class="mb-2 ml-2 inline-flex rounded-full bg-white px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-sky-700 ring-1 ring-sky-200">Usulan</span>
                                        <div class="ml-2 flex items-start gap-2.5">
                                            <x-dashboard-icon name="clipboard" class="mt-0.5 h-5 w-5 shrink-0 text-sky-700" />
                                            <div class="min-w-0">
                                                <p class="font-semibold leading-5 text-slate-900">{{ $mutation->jabatan_baru }}</p>
                                                <p class="mt-1 text-xs leading-5 text-slate-500">{{ collect([$mutation->unit_baru, $mutation->departemen_baru])->filter()->unique()->implode(' · ') ?: 'Organisasi tidak tersedia' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Workflow" class="px-4 py-4 align-middle whitespace-normal">
                                    <div class="min-w-0" aria-label="Status workflow {{ $mutation->verification_status }}">
                                        <div class="flex items-center px-3" aria-hidden="true">
                                            <span class="h-3.5 w-3.5 shrink-0 rounded-full border-2 {{ $workflowStage >= 1 ? 'border-sky-600 bg-sky-600 ring-4 ring-sky-100' : 'border-slate-300 bg-white' }}"></span>
                                            <span class="h-0.5 min-w-0 flex-1 {{ $workflowStage >= 2 ? 'bg-amber-400' : 'bg-slate-200' }}"></span>
                                            <span class="h-3.5 w-3.5 shrink-0 rounded-full border-2 {{ $workflowStage >= 2 ? 'border-amber-500 bg-amber-500 ring-4 ring-amber-100' : 'border-slate-300 bg-white' }}"></span>
                                            <span class="h-0.5 min-w-0 flex-1 {{ $workflowStage >= 3 ? 'bg-emerald-500' : 'bg-slate-200' }}"></span>
                                            <span class="h-3.5 w-3.5 shrink-0 rounded-full border-2 {{ $workflowStage >= 3 ? 'border-emerald-600 bg-emerald-600 ring-4 ring-emerald-100' : 'border-slate-300 bg-white' }}"></span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-3 gap-1 text-center text-[10px] font-semibold">
                                            <span class="{{ $workflowStage === 1 ? 'text-sky-700' : 'text-slate-500' }}">Draft</span>
                                            <span class="{{ $workflowStage === 2 ? 'text-amber-700' : 'text-slate-500' }}">Menunggu</span>
                                            <span class="{{ $workflowStage >= 3 ? 'text-emerald-700' : 'text-slate-500' }}">Disetujui</span>
                                        </div>
                                        <div class="mt-3 text-center">
                                            @if ($mutation->verification_status === 'Final')
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-200">Sudah diterapkan</span>
                                            @elseif ($mutation->verification_status === 'Approved')
                                                <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-[10px] font-bold text-sky-700 ring-1 ring-sky-200">Menunggu TMT</span>
                                            @elseif ($mutation->verification_status === 'Pending')
                                                <p class="text-[10px] leading-4 text-slate-500">{{ $approvedCount }}/3 disetujui · Tahap {{ $activeStep }}<br>{{ $activeApproverName ?? 'Snapshot approver tidak tersedia' }}</p>
                                            @elseif ($mutation->verification_status === 'Returned')
                                                <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-[10px] font-bold text-rose-700 ring-1 ring-rose-200">Dikembalikan</span>
                                                @if ($latestReturn?->note)
                                                    <p class="mt-2 break-words text-[10px] leading-4 text-rose-700">Catatan: {{ $latestReturn->note }}</p>
                                                @endif
                                            @elseif ($workflowStage === 0)
                                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-bold text-red-700 ring-1 ring-red-200">{{ $mutation->verification_status }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Aksi" class="px-4 py-4 text-center align-middle">
                                    <div class="flex flex-col items-center gap-2">
                                        <button type="button" onclick="document.getElementById('mutation-detail-{{ $mutation->id }}').showModal()"
                                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-sky-500">Detail</button>
                                        @if ($canApproveMutations && $isActiveApprover)
                                            <button type="submit" form="approve-mutation-{{ $mutation->id }}" class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 focus-visible:ring-2 focus-visible:ring-emerald-500">Setuju</button>
                                        @endif
                                        @can('submit', $mutation)
                                            <button type="submit" form="submit-mutation-{{ $mutation->id }}" @disabled(!$approvalConfigurationComplete)
                                                class="w-full rounded-lg bg-sky-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-sky-800 focus-visible:ring-2 focus-visible:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-300">Ajukan Mutasi</button>
                                        @endcan
                                        @if ($mutation->verification_status === 'Final' && $canPrintMutations)
                                            <a href="{{ route('mutasi.print', $mutation) }}" class="w-full rounded-lg bg-sky-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-sky-800 focus-visible:ring-2 focus-visible:ring-sky-500">Print</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="mutation-journey-empty-row">
                                <td colspan="{{ $canDeleteMutations ? 7 : 6 }}"
                                    class="px-4 py-12 text-center align-middle text-sm text-slate-500">
                                    <span class="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                        <x-dashboard-icon name="arrows" class="h-5 w-5" />
                                    </span>
                                    <span class="font-semibold text-slate-600">Belum ada data mutasi.</span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        <div class="border-t border-slate-100 px-4 py-3">{{ $mutations->links() }}</div>
    </section>

    @foreach ($mutations as $mutation)
        @php
            $activeStep = $mutation->current_approval_step;
            $activeApproval = $mutation->approvals
                ->where('approval_round', $mutation->approval_round)
                ->firstWhere('step_order', $activeStep);
            $isActiveApprover = $approvalConfigurationComplete
                && $activeApproval?->status === 'Pending'
                && $activeApproval?->manager_id === auth()->id();
        @endphp
        @if ($canApproveMutations && $mutation->verification_status === 'Pending' && $isActiveApprover)
            <form id="approve-mutation-{{ $mutation->id }}" method="POST"
                action="{{ route('mutasi.approve', $mutation) }}" data-confirm-submit data-confirm-title="Setujui mutasi ini?"
                data-confirm-text="Persetujuan Tahap {{ $activeStep }} akan dicatat dan workflow dilanjutkan ke tahap berikutnya."
                data-confirm-icon="question" data-confirm-button-text="Ya, setujui" data-confirm-button-color="#059669">
                @csrf
            </form>
        @endif
        @can('submit', $mutation)
            <form id="submit-mutation-{{ $mutation->id }}" method="POST" action="{{ route('mutasi.submit', $mutation) }}"
                data-confirm-submit data-confirm-title="Ajukan mutasi ini?"
                data-confirm-text="Pengajuan akan dikirim kepada approver Tahap 1 dalam putaran baru."
                data-confirm-icon="question" data-confirm-button-text="Ya, ajukan" data-confirm-button-color="#0284c7">
                @csrf
            </form>
        @endcan
    @endforeach

    @foreach ($mutations as $mutation)
        @php
            $statusIsFinal = $mutation->verification_status === 'Final';
            $initials = collect(preg_split('/\s+/', trim($mutation->nama)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
        @endphp
        <dialog id="mutation-detail-{{ $mutation->id }}"
            @if (in_array(old('_form'), ['edit-mutation-'.$mutation->id, 'return-mutation-'.$mutation->id], true)) x-init="$el.showModal()" @endif
            aria-labelledby="mutation-detail-title-{{ $mutation->id }}"
            class="w-[calc(100%-1rem)] max-w-7xl overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60 sm:w-[calc(100%-2rem)] sm:rounded-3xl">
            <section class="flex max-h-[calc(100dvh-1rem)] min-h-0 flex-col bg-white sm:max-h-[calc(100dvh-2rem)]">
                <header class="flex flex-none items-center justify-between gap-4 bg-slate-950 px-4 py-4 text-white sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-dashboard-icon name="users" class="h-6 w-6" />
                        <span class="h-7 w-px bg-white/30" aria-hidden="true"></span>
                        <h3 id="mutation-detail-title-{{ $mutation->id }}" class="truncate font-display text-lg font-bold sm:text-xl">Detail Mutasi HC</h3>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup detail Mutasi"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-2xl leading-none text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">&times;</button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6 lg:px-8 lg:py-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-slate-950 font-display text-xl font-bold text-white shadow-lg shadow-slate-900/20 sm:h-20 sm:w-20 sm:text-2xl">{{ $initials ?: '?' }}</span>
                        <div class="min-w-0 sm:border-l sm:border-slate-200 sm:pl-6">
                            <p class="truncate font-display text-2xl font-bold text-slate-950">{{ $mutation->nama }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-500">SAP {{ $mutation->sap }}</p>
                            <x-personnel-position-id :employee="$mutation->employee" />
                        </div>
                        <div class="flex flex-wrap gap-2 sm:ml-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $mutation->employee ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]" aria-hidden="true">{{ $mutation->employee ? '✓' : '!' }}</span>
                                Employee {{ $mutation->employee ? 'Tersedia' : 'Tidak Tersedia' }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $statusIsFinal ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : ($mutation->verification_status === 'Returned' ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200') }}">
                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]" aria-hidden="true">{{ $statusIsFinal ? '✓' : '·' }}</span>
                                {{ $mutation->verification_status }}
                            </span>
                        </div>
                    </div>

                    @include('pages.mutasi._detail-content')
                    @can('sendBack', $mutation)
                        <form method="POST" action="{{ route('mutasi.send-back', $mutation) }}" class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4"
                            data-confirm-submit data-confirm-title="Kembalikan mutasi ini?"
                            data-confirm-text="Mutasi akan dikembalikan untuk diperbaiki dan harus diajukan ulang."
                            data-confirm-button-text="Ya, kembalikan" data-confirm-button-color="#e11d48">
                            @csrf
                            <input type="hidden" name="_form" value="return-mutation-{{ $mutation->id }}">
                            <label for="mutation-return-note-{{ $mutation->id }}" class="text-sm font-semibold text-rose-800">Catatan Send Back</label>
                            <textarea id="mutation-return-note-{{ $mutation->id }}" name="return_note" rows="3" maxlength="2000" required
                                class="mt-2 block w-full rounded-xl border-rose-200 text-sm focus:border-rose-400 focus:ring-rose-400">{{ old('_form') === 'return-mutation-'.$mutation->id ? old('return_note') : '' }}</textarea>
                            @if (old('_form') === 'return-mutation-'.$mutation->id)
                                @error('return_note')<p class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</p>@enderror
                            @endif
                            <button type="submit" class="mt-3 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Kembalikan</button>
                        </form>
                    @endcan
                    @can('update', $mutation)
                        <details id="mutation-edit-{{ $mutation->id }}" @if (old('_form') === 'edit-mutation-'.$mutation->id) open @endif
                            class="mt-5 scroll-mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                            <summary class="cursor-pointer font-display text-sm font-bold text-slate-800">Edit Data Mutasi</summary>
                            @include('pages.mutasi._form', [
                                'action' => route('mutasi.update', $mutation), 'method' => 'PUT', 'movement' => $mutation,
                                'formKey' => 'edit-mutation-'.$mutation->id, 'submitLabel' => 'Simpan Perubahan',
                            ])
                        </details>
                    @endcan
                </div>
                <footer class="flex flex-none flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:flex-row sm:justify-end sm:px-6 lg:px-8">
                    <button type="button" onclick="this.closest('dialog').close()" class="ui-button ui-button-secondary w-full sm:w-auto">Tutup</button>
                    @can('update', $mutation)
                        <button type="button"
                            onclick="const panel = document.getElementById('mutation-edit-{{ $mutation->id }}'); panel.open = true; panel.scrollIntoView({ behavior: 'smooth', block: 'start' });"
                            class="ui-button ui-button-primary w-full sm:w-auto">
                            <x-dashboard-icon name="edit" class="h-4 w-4" />
                            Edit Data
                        </button>
                    @endcan
                </footer>
            </section>
        </dialog>
    @endforeach
    </div>
@endsection
