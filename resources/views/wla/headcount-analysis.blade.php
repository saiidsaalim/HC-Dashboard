@extends('layouts.app')

@section('page-title', 'Analisis Headcount WLA')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('wla') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; WLA Assessments</a>
            <p class="mt-2 text-sm text-slate-500">Perbandingan rekomendasi WLA Final dengan jumlah pegawai aktual pada Position yang sama.</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-950">Analisis Headcount dan Gap</h2>
        </div>
    </div>

    @if ($periods->isEmpty())
        <section class="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
            <h3 class="font-display text-lg font-bold text-slate-900">Belum ada WLA Final</h3>
            <p class="mt-2 text-sm text-slate-500">Analisis tersedia setelah minimal satu WLA difinalisasi.</p>
        </section>
    @else
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <form method="GET"
                x-data="{
                    departmentId: @js((string) ($filters['department_id'] ?? '')),
                    unitId: @js((string) ($filters['unit_id'] ?? '')),
                    positionId: @js((string) ($filters['position_id'] ?? '')),
                    units: @js($units),
                    positions: @js($positions),
                    resetDepartment() { this.unitId = ''; this.positionId = ''; },
                    resetUnit() { this.positionId = ''; },
                }"
                class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[150px_1fr_1fr_1fr_170px_auto]">
                <label class="text-sm font-medium text-slate-700">Periode
                    <select name="period" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        @foreach ($periods as $period)
                            <option value="{{ $period }}" @selected($selectedPeriod === $period)>{{ $period }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Department
                    <select name="department_id" x-model="departmentId" @change="resetDepartment()" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department['id'] }}">{{ $department['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Unit
                    <select name="unit_id" x-model="unitId" @change="resetUnit()" :disabled="!departmentId" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400 disabled:bg-slate-100">
                        <option value="">Semua Unit</option>
                        <template x-for="unit in units.filter(item => String(item.department_id) === String(departmentId))" :key="unit.id">
                            <option :value="String(unit.id)" x-text="unit.name"></option>
                        </template>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Position
                    <select name="position_id" x-model="positionId" :disabled="!unitId" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400 disabled:bg-slate-100">
                        <option value="">Semua Position</option>
                        <template x-for="position in positions.filter(item => String(item.unit_id) === String(unitId))" :key="position.id">
                            <option :value="String(position.id)" x-text="position.name"></option>
                        </template>
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700">Status gap
                    <select name="gap_status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua</option>
                        <option value="shortage" @selected($filters['gap_status'] === 'shortage')>Kekurangan</option>
                        <option value="balanced" @selected($filters['gap_status'] === 'balanced')>Seimbang</option>
                        <option value="surplus" @selected($filters['gap_status'] === 'surplus')>Kelebihan</option>
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
                    <a href="{{ route('wla.headcount-analysis') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </section>

        <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Position dianalisis', 'value' => $summary['positions_analyzed'], 'style' => 'text-slate-900'],
                ['label' => 'Total rekomendasi pegawai', 'value' => $summary['total_recommended'], 'style' => 'text-slate-900'],
                ['label' => 'Pegawai Aktual Saat Ini', 'value' => $summary['total_actual'], 'style' => 'text-sky-700'],
                ['label' => 'Total kekurangan', 'value' => $summary['total_shortage'], 'style' => 'text-red-700'],
                ['label' => 'Total kelebihan', 'value' => $summary['total_surplus'], 'style' => 'text-amber-700'],
                ['label' => 'Position seimbang', 'value' => $summary['balanced_positions'], 'style' => 'text-emerald-700'],
                ['label' => 'Snapshot tidak lengkap', 'value' => $summary['incomplete_snapshots'], 'style' => 'text-slate-500'],
            ] as $card)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-2 font-display text-3xl font-bold {{ $card['style'] }}">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Unit</th>
                            <th class="px-4 py-3">Position</th>
                            <th class="px-4 py-3 text-right">FTE Final</th>
                            <th class="px-4 py-3 text-right">Rekomendasi</th>
                            <th class="px-4 py-3 text-right">Pegawai Aktual Saat Ini</th>
                            <th class="px-4 py-3 text-right">Gap</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 text-slate-600">{{ $row['period'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['department_name'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['unit_name'] }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ $row['position_name'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['fte'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $row['recommended_employees'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sky-700">{{ $row['actual_employees'] ?? '—' }}</td>
                                <td @class([
                                    'px-4 py-3 text-right font-bold',
                                    'text-red-700' => $row['status'] === 'shortage',
                                    'text-emerald-700' => $row['status'] === 'balanced',
                                    'text-amber-700' => $row['status'] === 'surplus',
                                    'text-slate-500' => $row['status'] === 'unavailable',
                                ])>{{ $row['gap_display'] }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-red-50 text-red-700' => $row['status'] === 'shortage',
                                        'bg-emerald-50 text-emerald-700' => $row['status'] === 'balanced',
                                        'bg-amber-50 text-amber-700' => $row['status'] === 'surplus',
                                        'bg-slate-100 text-slate-600' => $row['status'] === 'unavailable',
                                    ])>{{ $row['status_label'] }}</span>
                                    @if ($row['unavailable_reason'])
                                        <p class="mt-1 text-xs text-slate-500">{{ $row['unavailable_reason'] }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-12 text-center text-slate-500">Tidak ada WLA Final yang sesuai dengan filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $rows->links() }}</div>
        </section>
    @endif
@endsection
