@extends('layouts.app')

@section('page-title', 'Detail Batch Mutasi')

@section('content')
    @php
        $mutations = $batch->mutations;
        $draftCount = $mutations->where('verification_status', 'Draft')->count();
        $returnedCount = $mutations->where('verification_status', 'Returned')->count();
        $pendingCount = $mutations->where('verification_status', 'Pending')->count();
        $completedCount = $mutations->whereIn('verification_status', ['Approved', 'Final'])->count();
        $submittableCount = $mutations->filter(fn ($mutation): bool => auth()->user()->can('submit', $mutation))->count();
        $approvableCount = $mutations->filter(fn ($mutation): bool => auth()->user()->can('approve', $mutation)
            && $mutation->verification_status === 'Pending'
            && ! $mutation->approvals->where('approval_round', $mutation->approval_round)->where('manager_id', auth()->id())->where('status', 'Approved')->count())->count();
    @endphp

    <x-ui.page-header :title="$batch->name" eyebrow="Batch Mutasi"
        :description="$batch->batch_number.' · '.($batch->description ?: 'Tanpa keterangan tambahan')">
        <x-slot:actions>
            <a href="{{ route('mutasi', ['batch' => $batch->id]) }}" class="ui-button ui-button-secondary">Lihat di Daftar Mutasi</a>
            <a href="{{ route('mutasi') }}" class="ui-button ui-button-secondary">Kembali</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm font-semibold text-emerald-800" role="status">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white" aria-hidden="true">✓</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
            @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['label' => 'Total Item', 'value' => $mutations->count(), 'tone' => 'text-slate-950'],
            ['label' => 'Draft', 'value' => $draftCount, 'tone' => 'text-sky-700'],
            ['label' => 'Menunggu', 'value' => $pendingCount, 'tone' => 'text-amber-600'],
            ['label' => 'Dikembalikan', 'value' => $returnedCount, 'tone' => 'text-rose-600'],
            ['label' => 'Disetujui / Final', 'value' => $completedCount, 'tone' => 'text-emerald-600'],
        ] as $summary)
            <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $summary['label'] }}</p>
                <p class="mt-2 font-display text-3xl font-bold {{ $summary['tone'] }}">{{ $summary['value'] }}</p>
            </article>
        @endforeach
    </div>

    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-display text-lg font-bold text-slate-950">Kontrol Batch</h2>
                <p class="mt-1 text-sm text-slate-500">TMT default {{ $batch->default_tmt?->format('d M Y') }} · Dibuat oleh {{ $batch->creator?->name ?? 'User tidak tersedia' }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                @if ($submittableCount > 0 && auth()->user()->hasPermission('mutations.submit'))
                    <form method="POST" action="{{ route('mutasi.batches.submit', $batch) }}" data-confirm-submit
                        data-confirm-title="Ajukan semua item yang siap?"
                        data-confirm-text="{{ $submittableCount }} item Draft/Dikembalikan akan masuk ke putaran persetujuan baru."
                        data-confirm-icon="question" data-confirm-button-text="Ya, ajukan" data-confirm-button-color="#0284c7">
                        @csrf
                        <button type="submit" @disabled(!$approvalConfigurationComplete) class="ui-button ui-button-primary disabled:cursor-not-allowed disabled:opacity-50">
                            Ajukan Semua ({{ $submittableCount }})
                        </button>
                    </form>
                @endif
                @if (!$approvalConfigurationComplete)
                    <span class="rounded-xl bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-700 ring-1 ring-amber-200">Konfigurasi approver belum lengkap</span>
                @endif
            </div>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div>
                <h2 class="font-display text-lg font-bold text-slate-950">Item Batch Mutasi</h2>
                <p class="mt-1 text-sm text-slate-500">Persetujuan dan send back tetap tercatat per pegawai.</p>
            </div>
            @if ($approvableCount > 0)
                <button type="submit" form="approve-selected-batch-items" class="ui-button bg-emerald-600 text-white hover:bg-emerald-700">
                    Setujui Terpilih
                </button>
            @endif
        </div>

        <form id="approve-selected-batch-items" method="POST" action="{{ route('mutasi.batches.approve-selected', $batch) }}"
            data-confirm-submit data-confirm-title="Setujui item terpilih?"
            data-confirm-text="Persetujuan Anda akan dicatat pada setiap item yang dipilih."
            data-confirm-icon="question" data-confirm-button-text="Ya, setujui" data-confirm-button-color="#059669"
            data-confirm-require-checkbox=".batch-approval-checkbox" data-confirm-empty-text="Pilih minimal satu item yang dapat disetujui.">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full min-w-[68rem] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th class="w-14 px-4 py-3 text-center">Pilih</th>
                            <th class="px-4 py-3">Pegawai</th>
                            <th class="px-4 py-3">Posisi Saat Ini</th>
                            <th class="px-4 py-3">Tujuan Mutasi</th>
                            <th class="px-4 py-3">TMT</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($mutations as $mutation)
                            @php
                                $currentApproval = $mutation->approvals
                                    ->where('approval_round', $mutation->approval_round)
                                    ->firstWhere('step_order', $mutation->current_approval_step);
                                $canApproveItem = $mutation->verification_status === 'Pending'
                                    && $currentApproval?->manager_id === auth()->id()
                                    && $currentApproval?->status === 'Pending';
                                $statusClass = match ($mutation->verification_status) {
                                    'Final' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                    'Approved' => 'bg-sky-50 text-sky-700 ring-sky-200',
                                    'Pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                    'Returned' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                    default => 'bg-slate-100 text-slate-700 ring-slate-200',
                                };
                            @endphp
                            <tr class="align-middle hover:bg-slate-50">
                                <td class="px-4 py-4 text-center">
                                    <input type="checkbox" name="mutation_ids[]" value="{{ $mutation->id }}" @disabled(!$canApproveItem)
                                        class="batch-approval-checkbox rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-30">
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-bold text-slate-900">{{ $mutation->nama }}</p>
                                    <p class="mt-1 text-xs text-slate-500">SAP {{ $mutation->sap }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800">{{ $mutation->jabatan_lama }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ collect([$mutation->unit_lama, $mutation->departemen_lama])->filter()->unique()->implode(' · ') }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-sky-800">{{ $mutation->jabatan_baru }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ collect([$mutation->unit_baru, $mutation->departemen_baru])->filter()->unique()->implode(' · ') }}</p>
                                </td>
                                <td class="px-4 py-4 font-medium text-slate-700">{{ $mutation->tmt?->format('d M Y') }}</td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $statusClass }}">{{ $mutation->verification_status }}</span>
                                    @if ($mutation->verification_status === 'Pending')
                                        <p class="mt-1 text-xs text-slate-500">Tahap {{ $mutation->current_approval_step }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <a href="{{ route('mutasi', ['search' => $mutation->sap]) }}" class="ui-button ui-button-secondary px-3 py-2 text-xs">Kelola Item</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </section>
@endsection
