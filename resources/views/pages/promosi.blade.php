@extends('layouts.app')

@section('page-title', 'Promosi')

@section('content')
    @php
        $canCreatePromotions = auth()->user()->hasPermission('promotions.create');
        $canDeletePromotions = auth()->user()->hasPermission('promotions.delete');
        $canManagePromotionApprovals = auth()->user()->hasPermission('promotions.manage-approval-settings');
        $statusStyles = [
            \App\Enums\PromotionStatus::Draft->value => 'bg-slate-100 text-slate-700',
            \App\Enums\PromotionStatus::LegacyPending->value => 'bg-slate-100 text-slate-700',
            \App\Enums\PromotionStatus::WaitingApproval->value => 'bg-amber-50 text-amber-700',
            \App\Enums\PromotionStatus::Returned->value => 'bg-rose-50 text-rose-700',
            \App\Enums\PromotionStatus::Final->value => 'bg-emerald-50 text-emerald-700',
        ];
    @endphp

    <x-ui.page-header title="Daftar Promosi" eyebrow="Talent movement"
        description="Workflow promosi berurutan dari Draft hingga empat tahap persetujuan.">
        @if ($canCreatePromotions || $canManagePromotionApprovals)
            <x-slot:actions>
                @if ($canManagePromotionApprovals)
                    <a href="{{ route('promosi.approval-settings.edit') }}"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Pengaturan Persetujuan
                    </a>
                @endif
                @if ($canCreatePromotions)
                    <button type="button" onclick="document.getElementById('create-promotion').showModal()"
                    class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Buat Promosi
                    </button>
                @endif
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if (session('status'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Data Promosi tidak dapat diproses.</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($canCreatePromotions && !$approvalConfigurationComplete)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Konfigurasi empat approver Promosi belum lengkap. Ajukan Promosi dinonaktifkan sampai pengaturan diselesaikan.
        </div>
    @endif

    <div class="grid gap-5 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Total usulan</p>
            <p class="mt-2 font-display text-3xl font-bold text-slate-950">{{ $totalPromotions }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Menunggu persetujuan</p>
            <p class="mt-2 font-display text-3xl font-bold text-amber-600">{{ $pendingPromotions }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Final tahun ini</p>
            <p class="mt-2 font-display text-3xl font-bold text-emerald-600">{{ $approvedThisYear }}</p>
        </div>
    </div>

    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex w-full flex-col items-stretch justify-between gap-4 p-4 sm:p-6 xl:flex-row xl:items-center">
            <h3 class="font-display text-lg font-bold text-slate-950">Usulan Promosi</h3>
            <div class="flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <x-ui.filter-panel id="promotion-filters" title="Filter Promosi" class="w-full sm:w-auto"
                    :action="route('promosi')" :reset-href="route('promosi')"
                    :active="$search !== '' || $filter !== 'all'">
                    <label class="block text-sm font-semibold text-slate-700">Pencarian
                        <input name="search" value="{{ $search }}" type="search" placeholder="Cari SAP, nama, jabatan..."
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Status Promosi
                        <select name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            @foreach (['all' => 'Semua', 'draft' => 'Draft', 'waiting' => 'Menunggu Persetujuan', 'returned' => 'Dikembalikan', 'final' => 'Final', 'mine' => 'Menunggu Persetujuan Saya'] as $value => $label)
                                <option value="{{ $value }}" @selected($filter === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </x-ui.filter-panel>
                @if ($canDeletePromotions && $promotions->contains(fn ($promotion) => auth()->user()->can('delete', $promotion)))
                    <div class="flex flex-wrap items-center justify-between gap-3 sm:justify-end">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                            <input id="select-all-promotions" type="checkbox"
                                onchange="document.querySelectorAll('.promotion-checkbox').forEach((checkbox) => checkbox.checked = this.checked)"
                                class="rounded border-slate-300 text-slate-950 focus:ring-amber-400">
                            Pilih semua
                        </label>
                        <button type="submit" form="bulk-delete-promotions"
                            class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">
                            Hapus terpilih
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <form id="bulk-delete-promotions" method="POST" action="{{ route('promosi.destroy-many') }}"
            data-confirm-submit data-confirm-title="Hapus data terpilih?" data-confirm-text="Hanya Draft dan data Dikembalikan yang dapat dihapus."
            data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626" data-confirm-require-checkbox=".promotion-checkbox"
            data-confirm-empty-text="Centang minimal satu usulan Promosi.">
            @csrf
            @method('DELETE')
            <div class="overflow-x-auto">
                <table class="w-full min-w-[73rem] table-fixed text-left text-sm whitespace-nowrap">
                    <x-personnel-movement-table-columns :show-selection-column="$canDeletePromotions" />
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            @if ($canDeletePromotions)<th class="w-12 px-4 py-3 text-center align-middle">Pilih</th>@endif
                            <th class="w-24 px-4 py-3">SAP</th>
                            <th class="w-44 px-4 py-3">Pegawai</th>
                            <th class="w-52 px-4 py-3">Posisi Saat Ini</th>
                            <th class="w-52 px-4 py-3">Usulan</th>
                            <th class="w-60 px-4 py-3">Workflow</th>
                            <th class="w-48 px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($promotions as $promotion)
                            @php
                                $status = $promotion->statusEnum();
                                $roundApprovals = $promotion->approvals->where('approval_round', $promotion->approval_round);
                                $approvedCount = $roundApprovals->where('status', \App\Enums\PromotionApprovalStatus::Approved)->count();
                                $displayRequiredSteps = $status === \App\Enums\PromotionStatus::Final
                                    && $approvedCount > 0
                                    && !$roundApprovals->contains('step_order', $requiredApprovalSteps)
                                        ? $approvedCount
                                        : $requiredApprovalSteps;
                                $activeConfig = $promotion->current_approval_step ? $approvalConfiguration->get($promotion->current_approval_step) : null;
                                $activeApprover = $activeConfig?->user;
                                $isActiveApprover = $status === \App\Enums\PromotionStatus::WaitingApproval
                                    && $activeApprover?->id === auth()->id()
                                    && $approvalConfigurationComplete
                                    && auth()->user()->hasPermission('promotions.approve');
                                $latestReturn = $promotion->approvals
                                    ->where('status', \App\Enums\PromotionApprovalStatus::Returned)
                                    ->sortByDesc('id')->first();
                            @endphp
                            <tr class="align-top">
                                @if ($canDeletePromotions)
                                    <td class="px-4 py-4 text-center">
                                        @can('delete', $promotion)
                                            <input type="checkbox" name="promotion_ids[]" value="{{ $promotion->id }}"
                                                class="promotion-checkbox rounded border-slate-300 text-slate-950 focus:ring-amber-400"
                                                onchange="document.getElementById('select-all-promotions').checked = document.querySelectorAll('.promotion-checkbox').length > 0 && document.querySelectorAll('.promotion-checkbox:not(:checked)').length === 0">
                                        @endcan
                                    </td>
                                @endif
                                <td class="px-4 py-4 font-semibold text-slate-700">{{ $promotion->sap }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-700">{{ $promotion->nama }}</p>
                                    <p class="mt-1 text-xs text-slate-500">TMT {{ $promotion->tmt->format('d M Y') }}</p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <p>{{ $promotion->departemen_lama }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $promotion->jabatan_lama }}</p>
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <p>{{ $promotion->departemen_baru }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $promotion->jabatan_baru }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-3 py-1 text-xs font-bold {{ $statusStyles[$status->value] ?? 'bg-slate-100 text-slate-700' }}">{{ $status->label() }}</span>
                                    <p class="mt-2 text-xs text-slate-500">Putaran {{ max((int) $promotion->approval_round, 0) }} · {{ $approvedCount }}/{{ $displayRequiredSteps }} disetujui</p>
                                    @if ($status === \App\Enums\PromotionStatus::WaitingApproval)
                                        <p class="mt-1 text-xs text-slate-600">
                                            Tahap {{ $promotion->current_approval_step }}:
                                            {{ $activeApprover?->name ?? 'Konfigurasi approver tidak valid' }}
                                        </p>
                                        @if ($isActiveApprover)
                                            <span class="mt-2 inline-block rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">Menunggu Persetujuan Anda</span>
                                        @endif
                                    @elseif ($status === \App\Enums\PromotionStatus::Returned && $latestReturn)
                                        <p class="mt-2 max-w-xs whitespace-normal text-xs text-rose-700">Catatan: {{ $latestReturn->note }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <button type="button" onclick="document.getElementById('promotion-detail-{{ $promotion->id }}').showModal()"
                                            class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Detail</button>
                                        @if ($isActiveApprover)
                                            <button type="submit" form="approve-promotion-{{ $promotion->id }}"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Setuju</button>
                                        @endif
                                        @can('submit', $promotion)
                                            <button type="submit" form="submit-promotion-{{ $promotion->id }}" @disabled(!$approvalConfigurationComplete)
                                                class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-slate-300">Ajukan Promosi</button>
                                        @endcan
                                        @can('print', $promotion)
                                            <a href="{{ route('promosi.print', $promotion) }}" target="_blank" rel="noopener"
                                                class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Print</a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canDeletePromotions ? 7 : 6 }}" class="px-4 py-10 text-center text-slate-500">Belum ada data Promosi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="border-t border-slate-100 p-4">{{ $promotions->links() }}</div>
    </div>

    @if ($canCreatePromotions)
        <dialog id="create-promotion" @if (old('_form') === 'create') open @endif
            aria-labelledby="manual-promotion-title" aria-describedby="manual-promotion-description"
            class="w-[calc(100%-1rem)] max-w-7xl overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60 sm:w-[calc(100%-2rem)] sm:rounded-3xl">
            <section class="flex max-h-[calc(100dvh-1rem)] min-h-0 flex-col bg-white sm:max-h-[calc(100dvh-2rem)]">
                <div class="flex flex-none items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:px-6 sm:py-5 lg:px-7">
                    <div>
                        <h3 id="manual-promotion-title" class="font-display text-xl font-bold text-slate-950 sm:text-2xl">Tambah Data Promosi</h3>
                        <p id="manual-promotion-description" class="mt-1 text-sm leading-6 text-slate-500">Lengkapi data utama dan bandingkan organisasi saat ini dengan organisasi tujuan sebelum menyimpan.</p>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup modal Tambah Data Promosi"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-2xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">&times;</button>
                </div>
                @include('pages.promosi._form', ['promotion' => null, 'action' => route('promosi.store'), 'method' => 'POST', 'formKey' => 'create', 'submitLabel' => 'Simpan Draft Promosi'])
            </section>
        </dialog>
    @endif

    @foreach ($promotions as $promotion)
        @php
            $status = $promotion->statusEnum();
            $activeConfig = $promotion->current_approval_step ? $approvalConfiguration->get($promotion->current_approval_step) : null;
            $activeApprover = $activeConfig?->user;
            $isActiveApprover = $status === \App\Enums\PromotionStatus::WaitingApproval
                && $activeApprover?->id === auth()->id()
                && $approvalConfigurationComplete
                && auth()->user()->hasPermission('promotions.approve');
            $statusIsFinal = $status === \App\Enums\PromotionStatus::Final;
            $initials = collect(preg_split('/\s+/', trim($promotion->nama)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
        @endphp

        @if ($isActiveApprover)
            <form id="approve-promotion-{{ $promotion->id }}" method="POST" action="{{ route('promosi.approve', $promotion) }}"
                data-confirm-submit data-confirm-title="Setujui Promosi tahap {{ $promotion->current_approval_step }}?"
                data-confirm-text="Keputusan akan dicatat pada putaran {{ $promotion->approval_round }}."
                data-confirm-icon="question" data-confirm-button-text="Ya, setujui" data-confirm-button-color="#059669">
                @csrf
            </form>
        @endif
        @can('submit', $promotion)
            <form id="submit-promotion-{{ $promotion->id }}" method="POST" action="{{ route('promosi.submit', $promotion) }}"
                data-confirm-submit data-confirm-title="Ajukan promosi ini?"
                data-confirm-text="Pengajuan akan dikirim kepada approver Tahap 1 dan tidak dapat diedit selama proses persetujuan."
                data-confirm-icon="question" data-confirm-button-text="Ya, ajukan" data-confirm-button-color="#0284c7">
                @csrf
            </form>
        @endcan

        <dialog id="promotion-detail-{{ $promotion->id }}"
            @if (in_array(old('_form'), ["edit-{$promotion->id}", "return-{$promotion->id}"], true)) x-init="$el.showModal()" @endif
            aria-labelledby="promotion-detail-title-{{ $promotion->id }}"
            class="w-[calc(100%-1rem)] max-w-7xl overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60 sm:w-[calc(100%-2rem)] sm:rounded-3xl">
            <section class="flex max-h-[calc(100dvh-1rem)] min-h-0 flex-col bg-white sm:max-h-[calc(100dvh-2rem)]">
                <header class="flex flex-none items-center justify-between gap-4 bg-slate-950 px-4 py-4 text-white sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-dashboard-icon name="users" class="h-6 w-6" />
                        <span class="h-7 w-px bg-white/30" aria-hidden="true"></span>
                        <h3 id="promotion-detail-title-{{ $promotion->id }}" class="truncate font-display text-lg font-bold sm:text-xl">Detail Promosi HC</h3>
                    </div>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup detail Promosi"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-2xl leading-none text-slate-300 transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-300">&times;</button>
                </header>
                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6 lg:px-8 lg:py-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-6">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-slate-950 font-display text-xl font-bold text-white shadow-lg shadow-slate-900/20 sm:h-20 sm:w-20 sm:text-2xl">{{ $initials ?: '?' }}</span>
                        <div class="min-w-0 sm:border-l sm:border-slate-200 sm:pl-6">
                            <p class="truncate font-display text-2xl font-bold text-slate-950">{{ $promotion->nama }}</p>
                            <p class="mt-1 text-sm font-medium text-slate-500">SAP {{ $promotion->sap }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 sm:ml-2">
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold {{ $promotion->employee ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]" aria-hidden="true">{{ $promotion->employee ? '✓' : '!' }}</span>
                                Employee {{ $promotion->employee ? 'Tersedia' : 'Tidak Tersedia' }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-bold ring-1 ring-inset {{ $statusStyles[$status->value] ?? 'bg-slate-100 text-slate-700' }}">
                                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-current text-[10px]" aria-hidden="true">{{ $statusIsFinal ? '✓' : '·' }}</span>
                                {{ $status->label() }}
                            </span>
                        </div>
                    </div>

                    @include('components.personnel-movement-detail-summary', [
                        'movement' => $promotion,
                        'movementLabel' => 'Promosi',
                        'statusLabel' => $status->label(),
                    ])

                <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-slate-800">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-sky-700 text-xs font-bold text-sky-700">✓</span>
                            <h4 class="font-display font-bold">Timeline Persetujuan</h4>
                        </div>
                        <span class="text-xs font-semibold text-slate-500">Status: {{ $status->label() }}@if($promotion->finalized_at) · Final {{ $promotion->finalized_at->format('d M Y H:i') }}@endif</span>
                    </div>
                    @if ($promotion->approvals->whereNull('approval_round')->isNotEmpty())
                        <div class="mt-4 rounded-lg bg-slate-50 p-3"><p class="text-sm font-semibold text-slate-700">Riwayat persetujuan lama</p>@foreach ($promotion->approvals->whereNull('approval_round') as $approval)<p class="mt-2 text-xs text-slate-600">{{ $approval->approver_name_snapshot ?? $approval->manager?->name ?? 'User tidak tersedia' }} — {{ $approval->status->label() }} — {{ ($approval->approved_at ?? $approval->created_at)?->format('d M Y H:i') }}</p>@endforeach</div>
                    @endif
                    @if ((int) $promotion->approval_round > 0)
                        @foreach (range(1, (int) $promotion->approval_round) as $round)
                            @php
                                $isLegacyFinal = $status === \App\Enums\PromotionStatus::Final
                                    && !$promotion->approvals
                                        ->where('approval_round', $promotion->approval_round)
                                        ->contains('step_order', $requiredApprovalSteps);
                                $roundSteps = $isLegacyFinal
                                    ? $promotion->approvals->where('approval_round', $round)->pluck('step_order')->filter()->unique()->sort()->values()
                                    : $approvalSteps;
                            @endphp
                            <div class="mt-4"><p class="text-sm font-bold text-slate-800">Putaran {{ $round }}</p><div class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                @foreach ($roundSteps as $step)
                                    @php
                                        $decision = $promotion->approvals->first(fn ($item) => $item->approval_round === $round && $item->step_order === $step);
                                        $isActive = $round === $promotion->approval_round && $promotion->current_approval_step === $step && $status === \App\Enums\PromotionStatus::WaitingApproval;
                                        $stepConfig = $isActive ? $approvalConfiguration->get($step) : null;
                                    @endphp
                                    <div class="rounded-xl border p-3 {{ $decision?->status === \App\Enums\PromotionApprovalStatus::Approved ? 'border-emerald-200 bg-emerald-50' : ($isActive ? 'border-sky-200 bg-sky-50' : 'border-slate-200 bg-slate-50') }}">
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $decision?->status === \App\Enums\PromotionApprovalStatus::Approved ? 'bg-emerald-500 text-white' : ($isActive ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-500') }}">{{ $decision?->status === \App\Enums\PromotionApprovalStatus::Approved ? '✓' : $step }}</span>
                                            <p class="text-xs font-bold uppercase text-slate-500">Tahap {{ $step }}</p>
                                        </div>
                                        @if ($decision)
                                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ $decision->approver_name_snapshot ?? $decision->manager?->name ?? 'User tidak tersedia' }}</p>
                                            <p class="text-xs text-slate-500">{{ $decision->approver_number_snapshot ?: 'Nomor tidak tercatat' }} · {{ $decision->approver_role_snapshot ?: 'Role tidak tercatat' }}</p>
                                            <p class="mt-2 text-xs font-semibold {{ $decision->status === \App\Enums\PromotionApprovalStatus::Approved ? 'text-emerald-700' : 'text-rose-700' }}">{{ $decision->status->label() }}</p>
                                            <p class="text-xs text-slate-500">{{ ($decision->approved_at ?? $decision->returned_at)?->format('d M Y H:i') }}</p>
                                            @if ($decision->note)<p class="mt-2 whitespace-normal text-xs text-rose-700">Catatan: {{ $decision->note }}</p>@endif
                                        @elseif ($isActive)
                                            <p class="mt-2 text-sm font-semibold text-sky-800">{{ $stepConfig?->user?->name ?? 'Konfigurasi approver tidak valid' }}</p><p class="mt-1 text-xs font-semibold text-sky-700">Menunggu persetujuan</p>
                                        @else
                                            <p class="mt-2 text-xs text-slate-500">Belum aktif</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div></div>
                        @endforeach
                    @else
                        <p class="mt-3 text-sm text-slate-500">Belum diajukan ke workflow baru.</p>
                    @endif
                </section>

                @if ($isActiveApprover)
                    <form method="POST" action="{{ route('promosi.send-back', $promotion) }}" class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4"
                        data-confirm-submit data-confirm-title="Kembalikan Promosi ini?" data-confirm-text="Promosi akan dikembalikan kepada Super Admin untuk diperbaiki."
                        data-confirm-button-text="Ya, kembalikan" data-confirm-button-color="#e11d48">
                        @csrf
                        <input type="hidden" name="_form" value="return-{{ $promotion->id }}">
                        <label for="return-note-{{ $promotion->id }}" class="text-sm font-semibold text-rose-800">Catatan Sendback</label>
                        <textarea id="return-note-{{ $promotion->id }}" name="return_note" rows="3" maxlength="2000" required class="mt-2 block w-full rounded-xl border-rose-200 text-sm focus:border-rose-400 focus:ring-rose-400">{{ old('_form') === "return-{$promotion->id}" ? old('return_note') : '' }}</textarea>
                        <button type="submit" class="mt-3 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Kembalikan</button>
                    </form>
                @endif

                @can('update', $promotion)
                    <details id="promotion-edit-{{ $promotion->id }}" class="mt-5 scroll-mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5" @if (old('_form') === "edit-{$promotion->id}") open @endif>
                        <summary class="cursor-pointer font-display text-sm font-bold text-slate-800">Edit Data Promosi</summary>
                        @include('pages.promosi._form', ['action' => route('promosi.update', $promotion), 'method' => 'PUT', 'formKey' => "edit-{$promotion->id}", 'submitLabel' => 'Simpan Perubahan'])
                    </details>
                @endcan
                </div>
                <footer class="flex flex-none flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:flex-row sm:justify-end sm:px-6 lg:px-8">
                    <button type="button" onclick="this.closest('dialog').close()" class="ui-button ui-button-secondary w-full sm:w-auto">Tutup</button>
                    @can('update', $promotion)
                        <button type="button"
                            onclick="const panel = document.getElementById('promotion-edit-{{ $promotion->id }}'); panel.open = true; panel.scrollIntoView({ behavior: 'smooth', block: 'start' });"
                            class="ui-button ui-button-primary w-full sm:w-auto">
                            <x-dashboard-icon name="edit" class="h-4 w-4" />
                            Edit Data
                        </button>
                    @endcan
                </footer>
            </section>
        </dialog>
    @endforeach
@endsection
