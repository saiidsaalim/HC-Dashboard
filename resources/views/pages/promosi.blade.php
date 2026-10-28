@extends('layouts.app')

@section('page-title', 'Promosi')

@section('content')
    @php
        $canCreatePromotions = auth()->user()->hasPermission('promotions.create');
        $canDeletePromotions = auth()->user()->hasPermission('promotions.delete');
        $canImportPromotions = auth()->user()->hasPermission('promotions.import');
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
        @if ($canCreatePromotions || $canImportPromotions || $canManagePromotionApprovals)
            <x-slot:actions>
                @if ($canManagePromotionApprovals)
                    <a href="{{ route('promosi.approval-settings.edit') }}"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Pengaturan Persetujuan
                    </a>
                @endif
                @if ($canImportPromotions)
                    <form method="POST" action="{{ route('promosi.import') }}" enctype="multipart/form-data"
                    class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.csv,.txt" required
                        class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-500 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 sm:w-auto">
                    <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">
                        Import Promosi
                    </button>
                    </form>
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
        <div class="flex flex-col justify-between gap-4 p-4 sm:p-6 xl:flex-row xl:items-center">
            <h3 class="font-display text-lg font-bold text-slate-950">Usulan Promosi</h3>
            <x-ui.filter-panel id="promotion-filters" title="Filter Promosi"
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
        </div>

        <form id="bulk-delete-promotions" method="POST" action="{{ route('promosi.destroy-many') }}"
            data-confirm-submit data-confirm-title="Hapus data terpilih?" data-confirm-text="Hanya Draft dan data Dikembalikan yang dapat dihapus."
            data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626" data-confirm-require-checkbox=".promotion-checkbox"
            data-confirm-empty-text="Centang minimal satu usulan Promosi.">
            @csrf
            @method('DELETE')
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            @if ($canDeletePromotions)<th class="w-12 px-4 py-3 text-center">Pilih</th>@endif
                            <th class="px-4 py-3">SAP</th>
                            <th class="min-w-[160px] px-4 py-3">Pegawai</th>
                            <th class="min-w-[150px] px-4 py-3">Posisi Saat Ini</th>
                            <th class="min-w-[150px] px-4 py-3">Usulan</th>
                            <th class="px-4 py-3">Workflow</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
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
                                                class="promotion-checkbox rounded border-slate-300 text-slate-950 focus:ring-amber-400">
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

        @if ($canDeletePromotions && $promotions->contains(fn ($promotion) => auth()->user()->can('delete', $promotion)))
            <div class="border-t border-slate-100 p-4">
                <button type="submit" form="bulk-delete-promotions" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Hapus terpilih</button>
            </div>
        @endif
        <div class="border-t border-slate-100 p-4">{{ $promotions->links() }}</div>
    </div>

    @if ($canCreatePromotions)
        <dialog id="create-promotion" @if (old('_form') === 'create') open @endif
            class="w-[calc(100%-2rem)] max-w-5xl rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
            <section class="max-h-[90vh] overflow-y-auto bg-white p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><h3 class="font-display text-xl font-bold text-slate-950">Buat Draft Promosi</h3><p class="mt-1 text-sm text-slate-500">Pilih Employee lalu koreksi snapshot dokumen jika diperlukan.</p></div>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup" class="rounded-lg px-3 py-2 text-xl text-slate-400 hover:bg-slate-100">&times;</button>
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

        <dialog id="promotion-detail-{{ $promotion->id }}" @if (in_array(old('_form'), ["edit-{$promotion->id}", "return-{$promotion->id}"], true)) open @endif
            class="w-[calc(100%-2rem)] max-w-5xl rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
            <section class="max-h-[90vh] overflow-y-auto bg-white p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Detail Promosi</p><h3 class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $promotion->nama }}</h3></div>
                    <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup" class="rounded-lg px-3 py-2 text-xl text-slate-400 hover:bg-slate-100">&times;</button>
                </div>

                <div class="mt-6 grid gap-5 lg:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <h4 class="font-semibold text-slate-900">Data Pegawai</h4>
                        <dl class="mt-3 space-y-2 text-sm"><div><dt class="text-xs text-slate-400">SAP</dt><dd class="font-medium text-slate-700">{{ $promotion->sap }}</dd></div><div><dt class="text-xs text-slate-400">Nama</dt><dd class="font-medium text-slate-700">{{ $promotion->nama }}</dd></div><div><dt class="text-xs text-slate-400">Employee</dt><dd class="font-medium {{ $promotion->employee ? 'text-emerald-700' : 'text-amber-700' }}">{{ $promotion->employee ? 'Masih tersedia' : 'Tidak lagi tersedia' }}</dd></div></dl>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <h4 class="font-semibold text-slate-900">Posisi Saat Ini</h4>
                        <dl class="mt-3 space-y-2 text-sm">@foreach (['Department Lama' => $promotion->departemen_lama, 'Jabatan Saat Ini' => $promotion->jabatan_lama, 'PG' => $promotion->pg, 'BAND Saat Ini' => $promotion->band_lama, 'JG Saat Ini' => $promotion->jg_lama] as $label => $value)<div><dt class="text-xs text-slate-400">{{ $label }}</dt><dd class="font-medium text-slate-700">{{ $value }}</dd></div>@endforeach</dl>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <h4 class="font-semibold text-slate-900">Usulan Promosi</h4>
                        <dl class="mt-3 space-y-2 text-sm">@foreach (['Department Baru' => $promotion->departemen_baru, 'Jabatan Usulan' => $promotion->jabatan_baru, 'BAND Usulan' => $promotion->band_baru, 'JG Usulan' => $promotion->jg_baru, 'TMT' => $promotion->tmt->format('d M Y')] as $label => $value)<div><dt class="text-xs text-slate-400">{{ $label }}</dt><dd class="font-medium text-slate-700">{{ $value }}</dd></div>@endforeach</dl>
                    </div>
                </div>

                <div class="mt-6 rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="font-semibold text-slate-900">Timeline Persetujuan</h4><span class="text-xs text-slate-500">Status: {{ $status->label() }}@if($promotion->finalized_at) · Final {{ $promotion->finalized_at->format('d M Y H:i') }}@endif</span></div>
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
                                    <div class="rounded-lg border p-3 {{ $isActive ? 'border-sky-200 bg-sky-50' : 'border-slate-200' }}">
                                        <p class="text-xs font-bold uppercase text-slate-500">Tahap {{ $step }}</p>
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
                </div>

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
                    <details class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4" @if (old('_form') === "edit-{$promotion->id}") open @endif>
                        <summary class="cursor-pointer text-sm font-semibold text-slate-700">Edit Data Promosi</summary>
                        @include('pages.promosi._form', ['action' => route('promosi.update', $promotion), 'method' => 'PUT', 'formKey' => "edit-{$promotion->id}", 'submitLabel' => 'Simpan Perubahan'])
                    </details>
                @endcan
            </section>
        </dialog>
    @endforeach
@endsection
