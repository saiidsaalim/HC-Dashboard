@props([
    'batches',
    'selectedBatchId' => null,
    'routePrefix',
    'movementLabel',
])

@if ($batches->isNotEmpty())
    <section data-mutation-batch-carousel class="mt-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
        aria-labelledby="recent-{{ $routePrefix }}-batches-title">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 id="recent-{{ $routePrefix }}-batches-title" class="font-display text-lg font-bold text-slate-950">Batch {{ $movementLabel }} Terbaru</h3>
                <p class="mt-1 text-xs text-slate-500">Pantau beberapa item {{ $movementLabel }} dalam satu kelompok pengajuan.</p>
            </div>
            @if ($batches->count() > 1)
                <div class="flex shrink-0 items-center gap-2" aria-label="Navigasi batch {{ strtolower($movementLabel) }}">
                    <button type="button" data-mutation-batch-previous aria-label="Lihat batch sebelumnya"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m12.5 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <button type="button" data-mutation-batch-next aria-label="Lihat batch berikutnya"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m7.5 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>
        <div data-mutation-batch-track class="mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($batches as $batch)
                <a href="{{ route($routePrefix.'.batches.show', $batch) }}"
                    @class([
                        'w-full shrink-0 snap-start rounded-xl border p-4 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 md:w-[calc(50%_-_0.375rem)] xl:w-[calc(33.333333%_-_0.5rem)]',
                        'border-sky-400 bg-sky-50 ring-1 ring-sky-200' => $selectedBatchId === $batch->id,
                        'border-slate-200 hover:border-sky-300 hover:bg-sky-50/40' => $selectedBatchId !== $batch->id,
                    ]) data-mutation-batch-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-sky-700">{{ $batch->batch_number }}</p>
                            <p class="mt-1 truncate font-display font-bold text-slate-900">{{ $batch->name }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $batch->movements_count }} item</span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
                        <span class="rounded-full bg-sky-50 px-2 py-1 text-sky-700">{{ $batch->draft_movements_count }} Draft</span>
                        <span class="rounded-full bg-amber-50 px-2 py-1 text-amber-700">{{ $batch->pending_movements_count }} Menunggu</span>
                        <span class="rounded-full bg-rose-50 px-2 py-1 text-rose-700">{{ $batch->returned_movements_count }} Kembali</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-emerald-700">{{ $batch->completed_movements_count }} Selesai</span>
                    </div>
                    <span class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-sky-700">
                        Lihat detail batch <span aria-hidden="true">&rarr;</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
