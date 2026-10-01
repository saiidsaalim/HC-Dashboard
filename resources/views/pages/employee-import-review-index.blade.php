@extends('layouts.app')

@section('page-title', 'Review Import Pegawai')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('data-pegawai') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-900">Data Pegawai</a>
                <h2 class="mt-2 font-display text-2xl font-bold text-slate-950">Batch import</h2>
            </div>
            <a href="{{ route('data-pegawai') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Kembali ke Data Pegawai</a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Batch</th>
                            <th class="px-4 py-3">Diimpor</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Valid</th>
                            <th class="px-4 py-3 text-right">Invalid</th>
                            <th class="px-4 py-3 text-right">Approved</th>
                            <th class="px-4 py-3 text-right">Processed</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($batches as $batch)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-900">#{{ $batch->id }} <span class="ml-1 font-normal text-slate-500">{{ strtoupper($batch->source_type) }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $batch->imported_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="px-4 py-3"><span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $batch->status }}</span></td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $batch->total_rows }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $batch->valid_rows }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $batch->invalid_rows }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $batch->approved_rows }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $batch->processed_rows }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('data-pegawai.import.review.show', $batch) }}" class="font-semibold text-sky-700 hover:text-sky-900">Review</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12 text-center text-slate-500">Belum ada batch import.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($batches->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">{{ $batches->links() }}</div>
            @endif
        </section>
    </div>
@endsection