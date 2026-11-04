@extends('layouts.app')

@section('page-title', 'Demosi')

@section('content')
    @php
        $canCreateDemotions = auth()->user()->hasPermission('demotions.create');
        $canManageDemotionApprovals = auth()->user()->hasPermission('demotions.manage-approval-settings');
        $canUpdateDemotions = auth()->user()->hasPermission('demotions.update');
        $canDeleteDemotions = auth()->user()->hasPermission('demotions.delete');
        $canApproveDemotions = auth()->user()->hasPermission('demotions.approve');
        $canPrintDemotions = auth()->user()->hasPermission('demotions.print');
    @endphp
    <div x-data="{ open: @js(old('_form') === 'create-demotion') }">
        <x-ui.page-header title="Daftar Demosi" eyebrow="Talent movement"
            description="Kelola proses penurunan jabatan dan evaluasi kinerja pegawai.">
            <x-slot:actions>
                @if ($canManageDemotionApprovals)
                    <a href="{{ route('demosi.approval-settings.edit') }}"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Pengaturan Persetujuan
                    </a>
                @endif
                @if ($canCreateDemotions)
                    <button type="button" @click="open = true"
                    class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Buat Demosi
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
                <p class="font-semibold">Data demosi tidak dapat diproses.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!$approvalConfigurationComplete)
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
                Konfigurasi tiga approver Demosi belum lengkap. Pengajuan dan persetujuan belum dapat diproses.
            </div>
        @endif

        @if ($canCreateDemotions)
        <div x-cloak x-show="open" x-transition.opacity @keydown.escape.window="open = false"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden bg-slate-950/60 p-2 backdrop-blur-sm sm:p-4"
            @click.self="open = false">
            <section class="flex max-h-[calc(100dvh-1rem)] w-full max-w-7xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:max-h-[calc(100dvh-2rem)] sm:rounded-3xl" role="dialog"
                aria-modal="true" aria-labelledby="manual-demotion-title" aria-describedby="manual-demotion-description">
                <div class="flex flex-none items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:px-6 sm:py-5 lg:px-7">
                    <div>
                        <h3 id="manual-demotion-title" class="font-display text-xl font-bold text-slate-950 sm:text-2xl">Tambah Data Demosi</h3>
                        <p id="manual-demotion-description" class="mt-1 text-sm leading-6 text-slate-500">Lengkapi data utama dan bandingkan organisasi saat ini dengan organisasi tujuan sebelum menyimpan.</p>
                    </div>
                    <button type="button" @click="open = false" aria-label="Tutup modal Tambah Data Demosi"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-2xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">&times;</button>
                </div>
                @include('pages.demosi._form', [
                    'action' => route('demosi.store'), 'method' => 'POST', 'movement' => null,
                    'formKey' => 'create-demotion', 'submitLabel' => 'Simpan Draft Demosi',
                ])
            </section>
        </div>
        @endif
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Total usulan</p>
            <p class="mt-2 font-display text-3xl font-bold text-slate-950">{{ $totalDemotions }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Menunggu persetujuan</p>
            <p class="mt-2 font-display text-3xl font-bold text-amber-600">{{ $pendingDemotions }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Disetujui tahun ini</p>
            <p class="mt-2 font-display text-3xl font-bold text-emerald-600">{{ $approvedDemotionsThisYear }}</p>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex w-full flex-col items-stretch justify-between gap-4 p-4 sm:p-6 xl:flex-row xl:items-center">
            <h3 class="font-display text-lg font-bold text-slate-950">Usulan demosi terbaru</h3>
            <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <x-ui.filter-panel id="demotion-filters" title="Filter Demosi" class="w-full sm:w-auto"
                    :action="route('demosi')" :reset-href="route('demosi')" :active="$search !== '' || $filter !== 'all'">
                    <label for="demotion-search" class="block text-sm font-semibold text-slate-700">Pencarian
                        <input id="demotion-search" name="search" value="{{ $search }}" type="search"
                            placeholder="Cari SAP, nama, jabatan..."
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </label>
                    <label for="demotion-status" class="block text-sm font-semibold text-slate-700">Status
                        <select id="demotion-status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm">
                            <option value="all" @selected($filter === 'all')>Semua</option>
                            <option value="draft" @selected($filter === 'draft')>Draft</option>
                            <option value="waiting" @selected($filter === 'waiting')>Menunggu</option>
                            <option value="approved" @selected($filter === 'approved')>Disetujui, menunggu TMT</option>
                            <option value="final" @selected($filter === 'final')>Sudah diterapkan</option>
                        </select>
                    </label>
                </x-ui.filter-panel>
                @if ($canDeleteDemotions && $demotions->contains(fn($demotion): bool => $demotion->verification_status !== 'Final'))
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:justify-end">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                            <input id="select-all-demotions" type="checkbox"
                                onchange="document.querySelectorAll('.demotion-checkbox').forEach((checkbox) => checkbox.checked = this.checked)"
                                class="rounded border-slate-300 text-slate-950 focus:ring-amber-400">
                            Pilih semua
                        </label>
                        <button type="submit" form="bulk-delete-demotions"
                            class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">Hapus
                            terpilih</button>
                    </div>
                @endif
            </div>
        </div>
        <form id="bulk-delete-demotions" method="POST" action="{{ route('demosi.destroy-many') }}"
            data-confirm-submit data-confirm-title="Hapus data terpilih?" data-confirm-text="Data yang dihapus tidak dapat dikembalikan."
            data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626" data-confirm-require-checkbox=".demotion-checkbox"
            data-confirm-empty-text="Centang minimal satu usulan demosi.">
            @csrf
            @method('DELETE')
            <div class="overflow-x-auto">
                <table class="w-full min-w-[73rem] table-fixed text-left text-sm whitespace-nowrap">
                    <x-personnel-movement-table-columns :show-selection-column="$canDeleteDemotions" />
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            @if ($canDeleteDemotions)
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
                        @forelse ($demotions as $demotion)
                            @php
                                $approvedCount = $demotion->approvals->where('status', 'Approved')->unique('manager_id')->count();
                                $activeStep = $demotion->current_approval_step ?? min($approvedCount + 1, 3);
                                $activeApproval = $demotion->approvals
                                    ->where('approval_round', $demotion->approval_round)
                                    ->firstWhere('step_order', $activeStep);
                                $activeApprover = $activeApproval?->manager;
                                $activeApproverName = $activeApproval?->approver_name_snapshot ?? $activeApprover?->name;
                                $isActiveApprover = $demotion->verification_status === 'Pending'
                                    && $activeApprover?->id === auth()->id();
                            @endphp
                            <tr tabindex="0" role="button" aria-label="Lihat detail demosi {{ $demotion->nama }}"
                                class="cursor-pointer transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400"
                                onclick="if (!event.target.closest('a,button,input,select,textarea,label,form')) document.getElementById('demotion-detail-{{ $demotion->id }}').showModal()"
                                onkeydown="if ((event.key === 'Enter' || event.key === ' ') && !event.target.closest('a,button,input,select,textarea,label,form')) { event.preventDefault(); document.getElementById('demotion-detail-{{ $demotion->id }}').showModal(); }">
                                @if ($canDeleteDemotions)
                                    <td data-label="Pilih" class="px-4 py-3 text-center">
                                        @if ($demotion->verification_status !== 'Final')
                                            <input type="checkbox" name="demotion_ids[]" value="{{ $demotion->id }}"
                                                class="demotion-checkbox rounded border-slate-300 text-slate-950 focus:ring-amber-400"
                                                onchange="document.getElementById('select-all-demotions').checked = document.querySelectorAll('.demotion-checkbox').length > 0 && document.querySelectorAll('.demotion-checkbox:not(:checked)').length === 0">
                                        @endif
                                    </td>
                                @endif
                                <td data-label="SAP" class="px-4 py-3 font-semibold text-slate-700">{{ $demotion->sap }}</td>
                                <td data-label="Pegawai" class="px-4 py-3">
                                    <p class="font-semibold text-slate-700">{{ $demotion->nama }}</p>
                                    <p class="mt-1 text-xs text-slate-500">TMT {{ $demotion->tmt?->format('d M Y') }}</p>
                                </td>
                                <td data-label="Posisi Saat Ini" class="px-4 py-3 text-slate-600">
                                    <p class="font-semibold text-slate-700">{{ $demotion->jabatan_lama }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $demotion->departemen_lama }}</p>
                                </td>
                                <td data-label="Usulan" class="px-4 py-3 text-slate-600">
                                    <p class="font-semibold text-slate-700">{{ $demotion->jabatan_baru }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $demotion->departemen_baru }}</p>
                                </td>
                                <td data-label="Workflow" class="px-4 py-3">
                                    <div class="flex flex-col items-start gap-1.5">
                                        @if ($demotion->verification_status === 'Final')
                                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Sudah diterapkan</span>
                                        @elseif ($demotion->verification_status === 'Approved')
                                            <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-sky-700">Disetujui, menunggu TMT</span>
                                        @elseif ($demotion->verification_status === 'Draft')
                                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">Draft</span>
                                        @else
                                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{{ $approvedCount }}/3 disetujui</span>
                                            <span class="text-xs text-slate-500">Tahap {{ $activeStep }}: {{ $activeApproverName ?? 'Snapshot approver tidak tersedia' }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Aksi" class="px-4 py-3 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <button type="button" onclick="document.getElementById('demotion-detail-{{ $demotion->id }}').showModal()"
                                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Detail</button>
                                        @if ($canApproveDemotions && $isActiveApprover)
                                            <button type="submit" form="approve-demotion-{{ $demotion->id }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setuju</button>
                                        @endif
                                        @can('submit', $demotion)
                                            <button type="submit" form="submit-demotion-{{ $demotion->id }}" @disabled(!$approvalConfigurationComplete)
                                                class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-slate-300">Ajukan Demosi</button>
                                        @endcan
                                        @if ($demotion->verification_status === 'Final' && $canPrintDemotions)
                                            <a href="{{ route('demosi.print', $demotion) }}" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Print</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canDeleteDemotions ? 7 : 6 }}"
                                    class="px-4 py-10 text-center text-sm text-slate-500">
                                    Belum ada data demosi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        <div class="border-t border-slate-100 px-4 py-3">{{ $demotions->links() }}</div>
    </div>

    @foreach ($demotions as $demotion)
        @php
            $activeStep = min($demotion->approvals->where('status', 'Approved')->unique('manager_id')->count() + 1, 3);
            $isActiveApprover = $approvalConfigurationComplete
                && $approvalConfiguration->get($activeStep)?->user_id === auth()->id();
        @endphp
        @if ($canApproveDemotions && $demotion->verification_status === 'Pending' && $isActiveApprover)
            <form id="approve-demotion-{{ $demotion->id }}" method="POST"
                action="{{ route('demosi.approve', $demotion) }}" data-confirm-submit data-confirm-title="Setujui demosi ini?"
                data-confirm-text="Persetujuan Tahap {{ $activeStep }} akan dicatat dan workflow dilanjutkan ke tahap berikutnya."
                data-confirm-icon="question" data-confirm-button-text="Ya, setujui" data-confirm-button-color="#059669">
                @csrf
            </form>
        @endif
        @can('submit', $demotion)
            <form id="submit-demotion-{{ $demotion->id }}" method="POST" action="{{ route('demosi.submit', $demotion) }}"
                data-confirm-submit data-confirm-title="Ajukan demosi ini?"
                data-confirm-text="Pengajuan akan dikirim kepada approver Tahap 1."
                data-confirm-icon="question" data-confirm-button-text="Ya, ajukan" data-confirm-button-color="#0284c7">
                @csrf
            </form>
        @endcan
    @endforeach

    @foreach ($demotions as $demotion)
        @php
            $statusIsFinal = $demotion->verification_status === 'Final';
            $approvedCount = $demotion->approvals->where('status', 'Approved')->unique('manager_id')->count();
            $currentApprovalStep = $demotion->verification_status === 'Pending' ? min($approvedCount + 1, 3) : null;
            $approvalsByStep = $demotion->approvals->whereNotNull('step_order')->keyBy('step_order');
            $legacyApprovals = $demotion->approvals->whereNull('step_order')->values();
            $initials = collect(preg_split('/\s+/', trim($demotion->nama)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
        @endphp
        <dialog id="demotion-detail-{{ $demotion->id }}"
            @if (old('_form') === 'edit-demotion-'.$demotion->id) x-init="$el.showModal()" @endif
            aria-labelledby="demotion-detail-title-{{ $demotion->id }}"
            class="w-[calc(100%-1rem)] max-w-7xl overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60 sm:w-[calc(100%-2rem)] sm:rounded-3xl">
            <section class="flex max-h-[calc(100dvh-1rem)] min-h-0 flex-col bg-white sm:max-h-[calc(100dvh-2rem)]">
                <header class="flex flex-none items-center justify-between gap-4 bg-slate-950 px-4 py-4 text-white sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-dashboard-icon name="users" class="h-6 w-6" />
                        <span class="h-7 w-px bg-white/30" aria-hidden="true"></span>
                        <h3 id="demotion-detail-title-{{ $demotion->id }}" class="truncate font-display text-lg font-bold sm:text-xl">Detail Demosi HC</h3>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup detail Demosi"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-2xl leading-none text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">&times;</button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6 lg:px-8 lg:py-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-slate-950 font-display text-xl font-bold text-white shadow-lg shadow-slate-900/20 sm:h-20 sm:w-20 sm:text-2xl">{{ $initials ?: '?' }}</span>
                        <div class="min-w-0 sm:border-l sm:border-slate-200 sm:pl-6">
                            <p class="truncate font-display text-2xl font-bold text-slate-950">{{ $demotion->nama }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-500">SAP {{ $demotion->sap }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:ml-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $demotion->employee ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]" aria-hidden="true">{{ $demotion->employee ? '✓' : '!' }}</span>
                                Employee {{ $demotion->employee ? 'Tersedia' : 'Tidak Tersedia' }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $statusIsFinal ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' }}">
                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]" aria-hidden="true">{{ $statusIsFinal ? '✓' : '·' }}</span>
                                {{ $demotion->verification_status }}
                            </span>
                        </div>
                    </div>

                    @include('components.personnel-movement-detail-summary', [
                        'movement' => $demotion,
                        'movementLabel' => 'Demosi',
                        'statusLabel' => $demotion->verification_status,
                    ])

                    <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-slate-800">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-sky-700 text-xs font-bold text-sky-700">✓</span>
                                <h4 class="font-display font-bold">Persetujuan {{ $approvedCount }} dari 3</h4>
                            </div>
                            <div class="flex items-center" aria-label="Progres persetujuan {{ $approvedCount }} dari 3">
                                @foreach (range(1, 3) as $step)
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 text-xs font-bold {{ $step <= $approvedCount ? 'border-emerald-500 bg-emerald-500 text-white' : ($step === $currentApprovalStep ? 'border-sky-500 bg-sky-50 text-sky-700' : 'border-slate-300 bg-white text-slate-400') }}">{{ $step <= $approvedCount ? '✓' : $step }}</span>
                                    @if ($step < 3)
                                        <span class="h-0.5 w-7 {{ $step <= $approvedCount ? 'bg-emerald-500' : 'bg-slate-200' }} sm:w-12"></span>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            @foreach (range(1, 3) as $step)
                                @php
                                    $decision = $approvalsByStep->get($step) ?? $legacyApprovals->get($step - 1);
                                    $isApproved = $decision?->status === 'Approved';
                                    $isCurrent = ! $isApproved && $step === $currentApprovalStep;
                                    $approverName = $decision?->approver_name_snapshot ?? $decision?->manager?->name ?? 'Snapshot approver tidak tersedia';
                                @endphp
                                <article class="rounded-xl border p-3 {{ $isApproved ? 'border-emerald-200 bg-emerald-50' : ($isCurrent ? 'border-sky-200 bg-sky-50' : 'border-slate-200 bg-slate-50') }}">
                                    <div class="flex items-start gap-2.5">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $isApproved ? 'bg-emerald-500 text-white' : ($isCurrent ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-500') }}">{{ $isApproved ? '✓' : $step }}</span>
                                        <div class="min-w-0">
                                            <p class="text-xs font-bold text-slate-800">Penyetuju {{ $step }}</p>
                                            <p class="mt-0.5 truncate text-xs text-slate-600" title="{{ $approverName }}">{{ $approverName }}</p>
                                            <p class="mt-1 text-xs font-semibold {{ $isApproved ? 'text-emerald-700' : ($isCurrent ? 'text-sky-700' : 'text-slate-500') }}">
                                                {{ $isApproved ? 'Disetujui' : ($isCurrent ? 'Menunggu keputusan' : 'Menunggu giliran') }}
                                            </p>
                                            @if ($isApproved)
                                                <p class="mt-1 text-[11px] text-slate-500">{{ $decision->approved_at?->format('d M Y H:i') ?: 'Waktu tidak tersedia' }}</p>
                                            @endif
                                            @if ($decision?->note)
                                                <p class="mt-1 break-words text-[11px] text-slate-500">{{ $decision->note }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>

                    @if ($canUpdateDemotions && ! $statusIsFinal)
                    <details id="demotion-edit-{{ $demotion->id }}" @if (old('_form') === 'edit-demotion-'.$demotion->id) open @endif
                        class="mt-5 scroll-mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                        <summary class="cursor-pointer font-display text-sm font-bold text-slate-800">Edit Data Demosi</summary>
                        @include('pages.demosi._form', [
                            'action' => route('demosi.update', $demotion), 'method' => 'PUT', 'movement' => $demotion,
                            'formKey' => 'edit-demotion-'.$demotion->id, 'submitLabel' => 'Simpan Perubahan',
                        ])
                    </details>
                    @endif
                </div>
                <footer class="flex flex-none flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:flex-row sm:justify-end sm:px-6 lg:px-8">
                    <button type="button" onclick="this.closest('dialog').close()" class="ui-button ui-button-secondary w-full sm:w-auto">Tutup</button>
                    @if ($canUpdateDemotions && ! $statusIsFinal)
                        <button type="button"
                            onclick="const panel = document.getElementById('demotion-edit-{{ $demotion->id }}'); panel.open = true; panel.scrollIntoView({ behavior: 'smooth', block: 'start' });"
                            class="ui-button ui-button-primary w-full sm:w-auto">
                            <x-dashboard-icon name="edit" class="h-4 w-4" />
                            Edit Data
                        </button>
                    @endif
                </footer>
            </section>
        </dialog>
    @endforeach
@endsection
