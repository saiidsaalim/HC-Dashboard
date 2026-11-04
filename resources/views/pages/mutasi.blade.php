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
    @endphp
    <div x-data="{
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
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Pengaturan Persetujuan
                    </a>
                @endif
                @if ($canCreateMutations)
                    <button type="button" x-ref="openMutation" @click="openModal()"
                    class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Buat Mutasi
                    </button>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))
            <div
                class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
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
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                Konfigurasi tiga approver Mutasi belum lengkap. Pengajuan dan persetujuan belum dapat diproses.
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

    <div class="grid gap-5 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Total data mutasi</p>
            <p class="mt-2 font-display text-3xl font-bold text-slate-950">{{ $totalMutations }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Menunggu persetujuan</p>
            <p class="mt-2 font-display text-3xl font-bold text-amber-600">{{ $pendingMutations }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Disetujui tahun ini</p>
            <p class="mt-2 font-display text-3xl font-bold text-emerald-600">{{ $approvedMutationsThisYear }}</p>
        </div>
    </div>
    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex w-full flex-col items-stretch justify-between gap-4 p-4 sm:p-6 xl:flex-row xl:items-center">
            <h3 class="font-display text-lg font-bold text-slate-950">Data mutasi terbaru</h3>
            <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <x-ui.filter-panel id="mutation-filters" title="Filter Mutasi" class="w-full sm:w-auto"
                    :action="route('mutasi')" :reset-href="route('mutasi')" :active="$search !== '' || $filter !== 'all'">
                    <label for="mutation-search" class="block text-sm font-semibold text-slate-700">Pencarian
                        <input id="mutation-search" name="search" value="{{ $search }}" type="search"
                            placeholder="Cari SAP, nama, jabatan..."
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </label>
                    <label for="mutation-status" class="block text-sm font-semibold text-slate-700">Status
                        <select id="mutation-status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="all" @selected($filter === 'all')>Semua</option>
                            <option value="draft" @selected($filter === 'draft')>Draft</option>
                            <option value="waiting" @selected($filter === 'waiting')>Menunggu</option>
                            <option value="approved" @selected($filter === 'approved')>Disetujui, menunggu TMT</option>
                            <option value="final" @selected($filter === 'final')>Sudah diterapkan</option>
                        </select>
                    </label>
                </x-ui.filter-panel>
                <div class="flex flex-wrap items-center justify-between gap-3 sm:justify-end">
                    @if ($canDeleteMutations && $mutations->contains(fn($mutation): bool => $mutation->verification_status !== 'Final'))
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                            <input id="select-all-mutations" type="checkbox"
                                onchange="document.querySelectorAll('.mutation-checkbox').forEach((checkbox) => checkbox.checked = this.checked)"
                                class="rounded border-slate-300 text-slate-950 focus:ring-amber-400">
                            Pilih semua
                        </label>
                        <button type="submit" form="bulk-delete-form"
                            class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
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
            <div class="overflow-x-auto">
                <table class="w-full min-w-[73rem] table-fixed text-left text-sm whitespace-nowrap">
                    <x-personnel-movement-table-columns :show-selection-column="$canDeleteMutations" />
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
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
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($mutations as $mutation)
                            <tr tabindex="0" role="button" aria-label="Lihat detail mutasi {{ $mutation->nama }}"
                                class="cursor-pointer transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400"
                                onclick="if (!event.target.closest('a,button,input,select,textarea,label,form')) document.getElementById('mutation-detail-{{ $mutation->id }}').showModal()"
                                onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a,button,input,select,textarea,label,form')) { event.preventDefault(); document.getElementById('mutation-detail-{{ $mutation->id }}').showModal(); }">
                                @php
                                    $approvedCount = $mutation->approvals->where('status', 'Approved')->unique('manager_id')->count();
                                    $activeStep = $mutation->current_approval_step ?? min($approvedCount + 1, 3);
                                    $activeApproval = $mutation->approvals
                                        ->where('approval_round', $mutation->approval_round)
                                        ->firstWhere('step_order', $activeStep);
                                    $activeApprover = $activeApproval?->manager;
                                    $activeApproverName = $activeApproval?->approver_name_snapshot ?? $activeApprover?->name;
                                    $isActiveApprover = $mutation->verification_status === 'Pending'
                                        && $activeApprover?->id === auth()->id();
                                @endphp
                                @if ($canDeleteMutations)
                                    <td data-label="Pilih" class="px-4 py-3 text-center align-middle">
                                        @if ($mutation->verification_status !== 'Final')
                                            <input type="checkbox" name="mutation_ids[]" value="{{ $mutation->id }}"
                                                class="mutation-checkbox rounded border-slate-300 text-slate-950 focus:ring-amber-400"
                                                onchange="document.getElementById('select-all-mutations').checked = document.querySelectorAll('.mutation-checkbox').length > 0 && document.querySelectorAll('.mutation-checkbox:not(:checked)').length === 0">
                                        @endif
                                    </td>
                                @endif
                                <td data-label="SAP" class="px-4 py-3 align-middle font-semibold text-slate-700">
                                    {{ $mutation->sap }}
                                </td>
                                <td data-label="Pegawai" class="px-4 py-3 align-middle">
                                    <p class="font-semibold text-slate-700">{{ $mutation->nama }}</p>
                                    <p class="mt-1 text-xs text-slate-500">TMT {{ $mutation->tmt?->format('d M Y') }}</p>
                                </td>
                                <td data-label="Posisi Saat Ini" class="px-4 py-3 align-middle text-slate-600">
                                    <p class="font-semibold text-slate-700">{{ $mutation->jabatan_lama }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $mutation->departemen_lama }}</p>
                                </td>
                                <td data-label="Usulan" class="px-4 py-3 align-middle text-slate-600">
                                    <p class="font-semibold text-slate-700">{{ $mutation->jabatan_baru }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $mutation->departemen_baru }}</p>
                                </td>
                                <td data-label="Workflow" class="px-4 py-3 align-middle">
                                    <div class="flex flex-col items-start gap-1.5">
                                        @if ($mutation->verification_status === 'Final')
                                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Sudah diterapkan</span>
                                        @elseif ($mutation->verification_status === 'Approved')
                                            <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">Disetujui, menunggu TMT</span>
                                        @elseif ($mutation->verification_status === 'Draft')
                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">Draft</span>
                                        @else
                                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{{ $approvedCount }}/3 disetujui</span>
                                            <span class="text-xs text-slate-500">Tahap {{ $activeStep }}: {{ $activeApproverName ?? 'Snapshot approver tidak tersedia' }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Aksi" class="px-4 py-3 text-center align-middle">
                                    <div class="flex flex-col items-center gap-2">
                                        <button type="button" onclick="document.getElementById('mutation-detail-{{ $mutation->id }}').showModal()"
                                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Detail</button>
                                        @if ($canApproveMutations && $isActiveApprover)
                                            <button type="submit" form="approve-mutation-{{ $mutation->id }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setuju</button>
                                        @endif
                                        @can('submit', $mutation)
                                            <button type="submit" form="submit-mutation-{{ $mutation->id }}" @disabled(!$approvalConfigurationComplete)
                                                class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-slate-300">Ajukan Mutasi</button>
                                        @endcan
                                        @if ($mutation->verification_status === 'Final' && $canPrintMutations)
                                            <a href="{{ route('mutasi.print', $mutation) }}" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Print</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canDeleteMutations ? 7 : 6 }}"
                                    class="px-4 py-10 text-center align-middle text-sm text-slate-500">
                                    Belum ada data mutasi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        <div class="border-t border-slate-100 px-4 py-3">{{ $mutations->links() }}</div>
    </div>

    @foreach ($mutations as $mutation)
        @php
            $activeStep = min($mutation->approvals->where('status', 'Approved')->unique('manager_id')->count() + 1, 3);
            $isActiveApprover = $approvalConfigurationComplete
                && $approvalConfiguration->get($activeStep)?->user_id === auth()->id();
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
                data-confirm-text="Pengajuan akan dikirim kepada approver Tahap 1."
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
            @if (old('_form') === 'edit-mutation-'.$mutation->id) x-init="$el.showModal()" @endif
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
                        </div>
                        <div class="flex flex-wrap gap-2 sm:ml-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $mutation->employee ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]" aria-hidden="true">{{ $mutation->employee ? '✓' : '!' }}</span>
                                Employee {{ $mutation->employee ? 'Tersedia' : 'Tidak Tersedia' }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $statusIsFinal ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' }}">
                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]" aria-hidden="true">{{ $statusIsFinal ? '✓' : '·' }}</span>
                                {{ $mutation->verification_status }}
                            </span>
                        </div>
                    </div>

                    @include('pages.mutasi._detail-content')
                    @if ($canUpdateMutations && ! $statusIsFinal)
                        <details id="mutation-edit-{{ $mutation->id }}" @if (old('_form') === 'edit-mutation-'.$mutation->id) open @endif
                            class="mt-5 scroll-mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                            <summary class="cursor-pointer font-display text-sm font-bold text-slate-800">Edit Data Mutasi</summary>
                            @include('pages.mutasi._form', [
                                'action' => route('mutasi.update', $mutation), 'method' => 'PUT', 'movement' => $mutation,
                                'formKey' => 'edit-mutation-'.$mutation->id, 'submitLabel' => 'Simpan Perubahan',
                            ])
                        </details>
                    @endif
                </div>
                <footer class="flex flex-none flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:flex-row sm:justify-end sm:px-6 lg:px-8">
                    <button type="button" onclick="this.closest('dialog').close()" class="ui-button ui-button-secondary w-full sm:w-auto">Tutup</button>
                    @if ($canUpdateMutations && ! $statusIsFinal)
                        <button type="button"
                            onclick="const panel = document.getElementById('mutation-edit-{{ $mutation->id }}'); panel.open = true; panel.scrollIntoView({ behavior: 'smooth', block: 'start' });"
                            class="ui-button ui-button-primary w-full sm:w-auto">
                            <x-dashboard-icon name="edit" class="h-4 w-4" />
                            Edit Data
                        </button>
                    @endif
                </footer>
            </section>
        </dialog>
    @endforeach
    </div>
@endsection
