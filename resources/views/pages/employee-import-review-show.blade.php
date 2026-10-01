@extends('layouts.app')

@section('page-title', 'Review Import Pegawai')

@section('content')
    <div x-data="{
        busy: false,
        message: '',
        async submitAction(event) {
            event.preventDefault();
            const form = event.target;
            if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
            this.busy = true;
            this.message = '';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                });
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || Object.values(result.errors || {}).flat().join(' ') || 'Aksi tidak dapat diproses.');
                }
                this.message = form.dataset.success || 'Perubahan tersimpan.';
                window.location.reload();
            } catch (error) {
                this.message = error.message;
                this.busy = false;
            }
        },
    }" class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('data-pegawai.import.review.index') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">Semua batch</a>
                <h2 class="mt-2 font-display text-2xl font-bold text-slate-950">Batch #{{ $batch->id }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ strtoupper($batch->source_type) }} · {{ $batch->imported_at?->format('Y-m-d H:i') ?? '-' }} · {{ $batch->status }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('data-pegawai.import.review.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Riwayat batch</a>
                @if ($approvableCount > 0)
                    <form method="POST" action="{{ route('data-pegawai.import-batches.approve-candidates', $batch) }}" data-confirm="Setujui semua {{ $approvableCount }} row VALID dengan mapping AUTO_CANDIDATE? INVALID, REVIEW_REQUIRED, dan UNMATCHED tetap tidak disetujui." data-success="{{ $approvableCount }} row valid disetujui." @submit="submitAction($event)">
                        @csrf
                        <button type="submit" :disabled="busy" class="rounded-lg border border-emerald-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 disabled:opacity-50">Setujui semua ({{ $approvableCount }})</button>
                    </form>
                @endif
                @if ($batch->approved_rows > $batch->processed_rows)
                    <form method="POST" action="{{ route('data-pegawai.import-batches.process', $batch) }}" data-confirm="Proses hanya row yang sudah APPROVED?" data-success="Batch selesai diproses." @submit="submitAction($event)">
                        @csrf
                        <button type="submit" :disabled="busy" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">Proses {{ $batch->approved_rows - $batch->processed_rows }} row approved</button>
                    </form>
                @endif
            </div>
        </div>

        <div x-cloak x-show="message" role="status" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" x-text="message"></div>

        <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                'Total' => $batch->total_rows,
                'Valid' => $batch->valid_rows,
                'Invalid' => $batch->invalid_rows,
                'Approved' => $batch->approved_rows,
                'Processed' => $batch->processed_rows,
                'Sisa review' => max(0, $batch->total_rows - $batch->invalid_rows - $batch->processed_rows),
            ] as $label => $value)
                <div class="bg-white px-4 py-3"><dt class="text-xs font-medium text-slate-500">{{ $label }}</dt><dd class="mt-1 font-display text-xl font-bold tabular-nums text-slate-900">{{ $value }}</dd></div>
            @endforeach
        </dl>

        <nav aria-label="Filter row import" class="inline-flex rounded-lg border border-slate-300 bg-white p-1">
            <a href="{{ route('data-pegawai.import.review.show', ['batch' => $batch, 'filter' => 'all']) }}"
                @class([
                    'rounded-md px-3 py-2 text-sm font-semibold transition',
                    'bg-slate-900 text-white' => $filter === 'all',
                    'text-slate-600 hover:bg-slate-100' => $filter !== 'all',
                ]) aria-current="{{ $filter === 'all' ? 'page' : 'false' }}">
                Semua ({{ $batch->total_rows }})
            </a>
            <a href="{{ route('data-pegawai.import.review.show', ['batch' => $batch, 'filter' => 'invalid']) }}"
                @class([
                    'rounded-md px-3 py-2 text-sm font-semibold transition',
                    'bg-red-700 text-white' => $filter === 'invalid',
                    'text-slate-600 hover:bg-red-50' => $filter !== 'invalid',
                ]) aria-current="{{ $filter === 'invalid' ? 'page' : 'false' }}">
                Invalid ({{ $invalidCount }})
            </a>
        </nav>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-200 px-4 py-3"><h3 class="font-semibold text-slate-900">Row sumber</h3></div>
            <div class="divide-y divide-slate-200">
                @forelse ($rows as $row)
                    <article class="px-4 py-4 sm:px-5" x-data="{ expanded: false }">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <button type="button" @click="expanded = !expanded" class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-left">
                                <span class="font-semibold text-slate-900">Row {{ $row->source_row }}</span>
                                <span class="font-mono text-sm text-slate-700">SAP: {{ $row->normalized_payload['sap'] ?? 'kosong' }}</span>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $row->normalized_payload['_operation'] ?? 'UNMATCHED' }}</span>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $row->validation_status }}</span>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $row->mapping_status }}</span>
                            </button>
                            <div class="flex shrink-0 items-center gap-3">
                                @if ($row->validation_status !== 'INVALID' && $row->mapping_status !== 'UNMATCHED' && $row->mapping_status !== 'APPROVED' && $row->processed_at === null)
                                    <form method="POST" action="{{ route('data-pegawai.import-rows.approve', $row) }}" data-success="Row {{ $row->source_row }} disetujui." @submit="submitAction($event)">
                                        @csrf
                                        <button type="submit" :disabled="busy" class="rounded-lg border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50 disabled:opacity-50">Setujui row</button>
                                    </form>
                                @elseif ($row->mapping_status === 'APPROVED')
                                    <span class="text-xs font-semibold text-emerald-700">{{ $row->processed_at ? 'Selesai diproses' : 'Siap diproses' }}</span>
                                @endif
                                <button type="button" @click="expanded = !expanded" class="text-sm font-semibold text-sky-700 hover:text-sky-900" x-text="expanded ? 'Tutup detail' : 'Lihat detail'"></button>
                            </div>
                        </div>

                        @if ($row->validation_errors || $row->mapping_errors)
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($row->validation_errors ?? [] as $error)
                                    <span class="rounded-md bg-red-50 px-2 py-1 text-xs text-red-800">{{ $error }}</span>
                                @endforeach
                                @foreach ($row->mapping_errors ?? [] as $error)
                                    <span class="rounded-md bg-amber-50 px-2 py-1 text-xs text-amber-800">{{ $error }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div x-cloak x-show="expanded" x-transition.opacity class="mt-4 grid gap-5 border-t border-slate-200 pt-4 xl:grid-cols-2">
                            <section>
                                <h4 class="mb-2 text-xs font-bold uppercase text-slate-500">Source payload</h4>
                                <dl class="grid gap-x-4 sm:grid-cols-2">
                                    @foreach ($row->source_payload ?? [] as $field => $value)
                                        <div class="border-t border-slate-100 py-2"><dt class="text-xs text-slate-500">{{ $field }}</dt><dd class="break-words text-sm text-slate-900">{{ $value === '' ? '—' : $value }}</dd></div>
                                    @endforeach
                                </dl>
                            </section>
                            <section>
                                <h4 class="mb-2 text-xs font-bold uppercase text-slate-500">Normalized payload</h4>
                                <dl class="grid gap-x-4 sm:grid-cols-2">
                                    @foreach ($row->normalized_payload ?? [] as $field => $value)
                                        <div class="border-t border-slate-100 py-2"><dt class="text-xs text-slate-500">{{ $field }}</dt><dd class="break-words text-sm text-slate-900">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : ($value === null || $value === '' ? '—' : $value) }}</dd></div>
                                    @endforeach
                                </dl>
                            </section>
                            @if ($row->processing_result)
                                <section class="xl:col-span-2"><h4 class="mb-2 text-xs font-bold uppercase text-slate-500">Hasil proses</h4><pre class="overflow-x-auto rounded-lg bg-slate-50 p-3 text-xs text-slate-700">{{ json_encode($row->processing_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></section>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="px-4 py-12 text-center text-sm text-slate-500">{{ $filter === 'invalid' ? 'Tidak ada row invalid pada batch ini.' : 'Batch ini belum memiliki row.' }}</p>
                @endforelse
            </div>
            @if ($rows->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">{{ $rows->links() }}</div>
            @endif
        </section>
    </div>
@endsection