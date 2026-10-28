@php
    $matrixRows = [
        [
            'potential' => 'Tinggi',
            'boxes' => [
                ['number' => '07', 'title' => 'Potential tinggi', 'performance' => 'Rendah', 'tone' => 'border-sky-200 bg-sky-50'],
                ['number' => '08', 'title' => 'Talenta berkembang', 'performance' => 'Sedang', 'tone' => 'border-emerald-200 bg-emerald-50'],
                ['number' => '09', 'title' => 'Talenta unggulan', 'performance' => 'Tinggi', 'tone' => 'border-emerald-200 bg-emerald-50'],
            ],
        ],
        [
            'potential' => 'Sedang',
            'boxes' => [
                ['number' => '04', 'title' => 'Perlu pengembangan', 'performance' => 'Rendah', 'tone' => 'border-amber-200 bg-amber-50'],
                ['number' => '05', 'title' => 'Talenta inti', 'performance' => 'Sedang', 'tone' => 'border-sky-200 bg-sky-50'],
                ['number' => '06', 'title' => 'Kinerja unggul', 'performance' => 'Tinggi', 'tone' => 'border-emerald-200 bg-emerald-50'],
            ],
        ],
        [
            'potential' => 'Rendah',
            'boxes' => [
                ['number' => '01', 'title' => 'Perlu perhatian', 'performance' => 'Rendah', 'tone' => 'border-amber-200 bg-amber-50'],
                ['number' => '02', 'title' => 'Kontributor stabil', 'performance' => 'Sedang', 'tone' => 'border-sky-200 bg-sky-50'],
                ['number' => '03', 'title' => 'Spesialis andal', 'performance' => 'Tinggi', 'tone' => 'border-sky-200 bg-sky-50'],
            ],
        ],
    ];
@endphp

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="talent-matrix-title">
    <header class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div>
            <h3 id="talent-matrix-title" class="font-display text-lg font-bold text-slate-950">Pemetaan talenta</h3>
            <p class="mt-1 text-sm text-slate-500">Potential &times; Performance</p>
        </div>
        <span class="w-fit rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.16em] text-slate-600">
            9-Box Matrix
        </span>
    </header>

    <div class="p-2 sm:p-5">
        <div class="grid grid-cols-[1.75rem_minmax(0,1fr)] gap-1.5 sm:grid-cols-[2.75rem_minmax(0,1fr)] sm:gap-3">
                <div class="flex flex-col items-center justify-center gap-2 rounded-lg bg-slate-950 px-1 py-2 text-white sm:gap-3 sm:rounded-xl sm:px-2 sm:py-4"
                    aria-label="Potential meningkat ke atas">
                    <svg class="h-4 w-4 shrink-0 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" aria-hidden="true">
                        <path d="M12 20V4m0 0-6 6m6-6 6 6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span class="-rotate-180 text-[9px] font-bold tracking-[0.12em] [writing-mode:vertical-rl] sm:text-xs sm:tracking-[0.2em]">POTENTIAL</span>
                </div>

                <div>
                    <div role="grid" aria-label="9-Box Talent Matrix" class="space-y-1.5 sm:space-y-3">
                        @foreach ($matrixRows as $row)
                            <div role="row"
                                class="grid grid-cols-[1.5rem_repeat(3,minmax(0,1fr))] gap-1.5 sm:grid-cols-[3.75rem_repeat(3,minmax(0,1fr))] sm:gap-3">
                                <div role="rowheader"
                                    class="flex items-center justify-center text-[9px] font-semibold text-slate-500 [writing-mode:vertical-rl] sm:text-xs">
                                    {{ $row['potential'] }}
                                </div>

                                @foreach ($row['boxes'] as $box)
                                    <article role="gridcell" data-talent-box data-box="{{ $box['number'] }}"
                                        data-potential="{{ $row['potential'] }}" data-performance="{{ $box['performance'] }}"
                                        class="flex aspect-square min-w-0 flex-col overflow-hidden rounded-lg border p-1.5 sm:rounded-xl sm:p-4 {{ $box['tone'] }}">
                                        <div class="flex items-start justify-between gap-1 sm:gap-3">
                                            <span class="text-[10px] font-bold text-slate-500 sm:text-xs">{{ $box['number'] }}</span>
                                            <span class="rounded-full bg-white/80 px-1.5 py-0.5 text-[10px] font-bold text-slate-700 sm:px-2 sm:py-1 sm:text-xs"
                                                aria-label="0 talenta">0</span>
                                        </div>

                                        <div class="mt-1.5 min-w-0 sm:mt-4">
                                            <h4 class="break-words font-display text-[9px] font-bold leading-tight text-slate-950 sm:text-base sm:leading-normal">
                                                {{ $box['title'] }}
                                            </h4>
                                            <dl class="mt-2 hidden space-y-1 break-words text-xs leading-normal text-slate-600 sm:block">
                                                <div class="grid gap-0.5 sm:flex sm:gap-1">
                                                    <dt class="font-semibold">Potential:</dt>
                                                    <dd>{{ $row['potential'] }}</dd>
                                                </div>
                                                <div class="grid gap-0.5 sm:flex sm:gap-1">
                                                    <dt class="font-semibold">Performance:</dt>
                                                    <dd>{{ $box['performance'] }}</dd>
                                                </div>
                                            </dl>
                                        </div>

                                        <div class="mt-auto flex items-center gap-1 border-t border-slate-900/10 pt-1.5 text-[9px] font-medium leading-tight text-slate-600 sm:gap-2 sm:pt-4 sm:text-xs sm:leading-normal">
                                            <svg class="hidden h-4 w-4 shrink-0 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="1.8" aria-hidden="true">
                                                <path d="M16 20v-1.5A4.5 4.5 0 0 0 11.5 14h-3A4.5 4.5 0 0 0 4 18.5V20M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7-1a2.5 2.5 0 0 1 0 5m3 6v-1a4 4 0 0 0-2.5-3.7"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <span class="hidden sm:inline">Belum ada talenta</span>
                                            <span class="sm:hidden">Kosong</span>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-1.5 grid grid-cols-[1.5rem_repeat(3,minmax(0,1fr))] gap-1.5 sm:mt-3 sm:grid-cols-[3.75rem_repeat(3,minmax(0,1fr))] sm:gap-3"
                        aria-hidden="true">
                        <span></span>
                        @foreach (['Rendah', 'Sedang', 'Tinggi'] as $performance)
                            <span class="text-center text-[9px] font-semibold text-slate-500 sm:text-xs">{{ $performance }}</span>
                        @endforeach
                    </div>

                    <div class="ml-[1.875rem] mt-2 flex items-center justify-center gap-1.5 text-slate-700 sm:ml-[4.5rem] sm:mt-3 sm:gap-3"
                        aria-label="Performance meningkat ke kanan">
                        <span class="text-[9px] font-bold tracking-[0.12em] sm:text-xs sm:tracking-[0.2em]">PERFORMANCE</span>
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            aria-hidden="true">
                            <path d="M4 12h16m0 0-6-6m6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
            </div>
        </div>
    </div>
</section>
