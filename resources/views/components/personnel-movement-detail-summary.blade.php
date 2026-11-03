@php
    $organizations = [
        [
            'title' => 'Organisasi Sebelumnya',
            'target' => false,
            'values' => [
                'Directorate' => $movement->direktorat_lama,
                'Department' => $movement->departemen_lama,
                'Unit' => $movement->unit_lama,
                'Section' => $movement->section_lama,
                'Position' => $movement->jabatan_lama,
            ],
        ],
        [
            'title' => 'Organisasi Tujuan',
            'target' => true,
            'values' => [
                'Directorate' => $movement->direktorat_baru,
                'Department' => $movement->departemen_baru,
                'Unit' => $movement->unit_baru,
                'Section' => $movement->section_baru,
                'Position' => $movement->jabatan_baru,
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
            ['label' => 'TMT', 'value' => $movement->tmt?->translatedFormat('d M Y') ?: '—', 'icon' => 'document'],
            ['label' => 'PG', 'value' => $movement->pg ?: '—', 'icon' => 'users'],
            ['label' => 'Level Lama', 'value' => $movement->level_lama ?: '—', 'icon' => 'clipboard'],
            ['label' => 'Level Baru', 'value' => $movement->level_baru ?: '—', 'icon' => 'clipboard'],
            ['label' => 'Level', 'value' => ($movement->level_lama ?: '—').' → '.($movement->level_baru ?: '—'), 'icon' => 'arrows'],
            ['label' => 'JG', 'value' => ($movement->jg_lama ?: '—').' → '.($movement->jg_baru ?: '—'), 'icon' => 'clipboard'],
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

<section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <div class="flex items-center gap-2 text-slate-800">
        <x-dashboard-icon name="document" class="h-5 w-5 text-sky-700" />
        <h4 class="font-display font-bold">Metadata {{ $movementLabel }}</h4>
    </div>
    <dl class="mt-4 grid gap-x-8 gap-y-2.5 text-sm lg:grid-cols-2">
        @foreach ([
            'Nama' => $movement->nama,
            'SAP' => $movement->sap,
            'Status' => $statusLabel,
            'Dibuat' => $movement->created_at?->format('d M Y H:i'),
            'Diperbarui' => $movement->updated_at?->format('d M Y H:i'),
            'Finalisasi' => $movement->finalized_at?->format('d M Y H:i'),
            'Diterapkan ke Data Pegawai' => $movement->employee_applied_at?->format('d M Y H:i'),
        ] as $label => $value)
            <div class="grid grid-cols-[8.5rem_0.75rem_minmax(0,1fr)] gap-2">
                <dt class="text-slate-500">{{ $label }}</dt>
                <span class="text-slate-400" aria-hidden="true">:</span>
                <dd class="min-w-0 break-words font-semibold text-slate-800">{{ $value ?: '—' }}</dd>
            </div>
        @endforeach
    </dl>
</section>
