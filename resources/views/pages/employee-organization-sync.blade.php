@extends('layouts.app')

@section('page-title', 'Sinkronisasi Organisasi Pegawai')

@section('content')
    @php
        $summary = $result['summary'];
        $pendingSafe = $summary['statuses']['ready_existing_master'] + $summary['statuses']['ready_create_master'];
        $reviewRows = collect($result['rows'])->whereIn('status', ['needs_review', 'conflict', 'invalid']);
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <p class="text-sm text-slate-500">Preview ini tidak mengubah database.</p>
                <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">Sinkronisasi Organisasi Pegawai</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Department berasal dari <strong>TXT_DEPT</strong>, Unit dari <strong>TXT_BIRO</strong>, dan Position dari
                    <strong>Position</strong>. Nilai Organizational Unit tidak digunakan dalam pemetaan ini.
                </p>
            </div>
            <a href="{{ route('data-pegawai') }}"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Kembali ke Data Pegawai
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Sinkronisasi tidak dapat dijalankan.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Total Pegawai', 'value' => $summary['employees']],
                ['label' => 'Siap Disinkronkan', 'value' => $pendingSafe],
                ['label' => 'Sudah Terpetakan', 'value' => $summary['statuses']['already_mapped']],
                ['label' => 'Perlu Review', 'value' => $summary['review_employees']],
                ['label' => 'Department Baru', 'value' => $summary['departments_to_create']],
                ['label' => 'Unit Baru', 'value' => $summary['units_to_create']],
                ['label' => 'Position Baru', 'value' => $summary['positions_to_create']],
                ['label' => 'FK Akan Diubah', 'value' => $summary['foreign_keys_to_update']],
            ] as $metric)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950">{{ number_format($metric['value'], 0, ',', '.') }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <div>
                    <h3 class="font-display text-xl font-bold text-slate-950">Hasil Preview</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Hanya status ready_existing_master dan ready_create_master yang akan diubah. Baris review tidak disentuh.
                    </p>
                </div>
                @if ($pendingSafe > 0)
                    <form method="POST" action="{{ route('data-pegawai.organization-sync.apply') }}"
                        onsubmit="return confirm('Terapkan sinkronisasi organisasi untuk data yang aman? Data konflik tidak akan diubah.')">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                            Terapkan Sinkronisasi
                        </button>
                    </form>
                @else
                    <span class="rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-600">Tidak ada perubahan aman</span>
                @endif
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($summary['statuses'] as $status => $count)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-700">{{ $status }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ number_format($count, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h3 class="font-display text-xl font-bold text-slate-950">Data yang Memerlukan Review</h3>
                <p class="mt-1 text-sm text-slate-500">Hanya ID internal ditampilkan; data pribadi pegawai tidak disertakan.</p>
            </div>

            @if ($reviewRows->isEmpty())
                <p class="mt-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">Tidak ada data yang memerlukan review.</p>
            @else
                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Employee ID</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Alasan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($reviewRows as $row)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-slate-700">{{ $row['employee_id'] }}</td>
                                    <td class="px-4 py-3 font-semibold text-amber-700">{{ $row['status'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $row['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
