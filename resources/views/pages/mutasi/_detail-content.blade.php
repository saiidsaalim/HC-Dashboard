@php
    $approvedCount = $mutation->approvals->where('status', 'Approved')->unique('manager_id')->count();
    $currentApprovalStep = $mutation->verification_status === 'Pending' ? min($approvedCount + 1, 3) : null;
    $approvalsByStep = $mutation->approvals->whereNotNull('step_order')->keyBy('step_order');
    $legacyApprovals = $mutation->approvals->whereNull('step_order')->values();
    $organizations = [
        [
            'title' => 'Organisasi Sebelumnya',
            'target' => false,
            'values' => [
                'Directorate' => $mutation->direktorat_lama,
                'Department' => $mutation->departemen_lama,
                'Unit' => $mutation->unit_lama,
                'Section' => $mutation->section_lama,
                'Position' => $mutation->jabatan_lama,
            ],
        ],
        [
            'title' => 'Organisasi Tujuan',
            'target' => true,
            'values' => [
                'Directorate' => $mutation->direktorat_baru,
                'Department' => $mutation->departemen_baru,
                'Unit' => $mutation->unit_baru,
                'Section' => $mutation->section_baru,
                'Position' => $mutation->jabatan_baru,
            ],
        ],
    ];
@endphp

<div class="mt-6 grid items-stretch gap-3 lg:grid-cols-[minmax(0,1fr)_3.5rem_minmax(0,1fr)]">
    @foreach ($organizations as $organization)
        @if ($loop->last)
            <div class="flex items-center justify-center lg:col-start-2 lg:row-start-1" aria-hidden="true">
                <span class="flex h-11 w-11 rotate-90 items-center justify-center rounded-full bg-slate-100 text-sky-700 ring-1 ring-slate-200 lg:rotate-0">
                    <x-dashboard-icon name="arrows" class="h-5 w-5" />
                </span>
            </div>
        @endif
        <section class="min-w-0 overflow-hidden rounded-2xl border {{ $organization['target'] ? 'border-sky-200 bg-sky-50/40 lg:col-start-3' : 'border-slate-200 bg-slate-50/70 lg:col-start-1' }} lg:row-start-1">
            <div class="flex items-center gap-3 border-b px-4 py-3 {{ $organization['target'] ? 'border-sky-200 bg-sky-100/70 text-sky-950' : 'border-slate-200 bg-slate-100 text-slate-800' }}">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl {{ $organization['target'] ? 'bg-slate-950 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200' }}">
                    <x-dashboard-icon name="formasi" class="h-5 w-5" />
                </span>
                <h4 class="font-display text-base font-bold sm:text-lg">{{ $organization['title'] }}</h4>
            </div>
            <dl class="divide-y {{ $organization['target'] ? 'divide-sky-100' : 'divide-slate-200' }} px-4 py-2">
                @foreach ($organization['values'] as $label => $value)
                    <div class="grid min-w-0 grid-cols-[6.5rem_0.75rem_minmax(0,1fr)] gap-2 py-2.5 text-sm sm:grid-cols-[8rem_0.75rem_minmax(0,1fr)]">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <span class="text-slate-400" aria-hidden="true">:</span>
                        <dd class="min-w-0 break-words font-semibold text-slate-800">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach
</div>

<section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex items-center gap-2 text-slate-800">
        <x-dashboard-icon name="arrows" class="h-5 w-5 text-sky-700" />
        <h4 class="font-display font-bold">Detail Perubahan</h4>
    </div>
    <div class="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'TMT', 'value' => $mutation->tmt?->translatedFormat('d M Y') ?: '—', 'icon' => 'document'],
            ['label' => 'PG', 'value' => $mutation->pg ?: '—', 'icon' => 'users'],
            ['label' => 'Level Lama', 'value' => $mutation->level_lama ?: '—', 'icon' => 'clipboard'],
            ['label' => 'Level Baru', 'value' => $mutation->level_baru ?: '—', 'icon' => 'clipboard'],
            ['label' => 'Level', 'value' => ($mutation->level_lama ?: '—').' → '.($mutation->level_baru ?: '—'), 'icon' => 'arrows'],
            ['label' => 'JG', 'value' => ($mutation->jg_lama ?: '—').' → '.($mutation->jg_baru ?: '—'), 'icon' => 'clipboard'],
        ] as $detail)
            <div class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                    <x-dashboard-icon :name="$detail['icon']" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500">{{ $detail['label'] }}</p>
                    <p class="mt-0.5 break-words text-sm font-bold text-slate-900">{{ $detail['value'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>

<div class="mt-5 grid gap-5 lg:grid-cols-[0.85fr_1.15fr]">
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex items-center gap-2 text-slate-800">
            <x-dashboard-icon name="document" class="h-5 w-5 text-sky-700" />
            <h4 class="font-display font-bold">Metadata Mutasi</h4>
        </div>
        <dl class="mt-4 space-y-2.5 text-sm">
            @foreach ([
                'Nama' => $mutation->nama,
                'SAP' => $mutation->sap,
                'Status' => $mutation->verification_status,
                'Dibuat' => $mutation->created_at?->format('d M Y H:i'),
                'Diperbarui' => $mutation->updated_at?->format('d M Y H:i'),
                'Finalisasi' => $mutation->finalized_at?->format('d M Y H:i'),
                'Diterapkan ke Data Pegawai' => $mutation->employee_applied_at?->format('d M Y H:i'),
            ] as $label => $value)
                <div class="grid grid-cols-[8.5rem_0.75rem_minmax(0,1fr)] gap-2">
                    <dt class="text-slate-500">{{ $label }}</dt>
                    <span class="text-slate-400" aria-hidden="true">:</span>
                    <dd class="min-w-0 break-words font-semibold text-slate-800">{{ $value ?: '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
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
</div>
