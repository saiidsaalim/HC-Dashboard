@extends('layouts.app')

@section('page-title', 'WLA Assessment')

@section('content')
    <x-ui.page-header title="WLA Assessments" eyebrow="Workload Analysis"
        description="Kelola assessment beban kerja dalam status Draft dan Final.">
        <x-slot:actions>
            @can('viewAny', \App\Models\WlaAssessment::class)
                <a href="{{ route('wla.headcount-analysis') }}" class="w-fit rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Analisis Headcount</a>
            @endcan
            @can('create', \App\Models\WlaAssessment::class)
                <a href="{{ route('wla.create') }}" class="ui-button ui-button-primary">Assessment Baru</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1280px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Assessment</th><th class="px-4 py-3">Period</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Position</th><th class="px-4 py-3">Schedule</th><th class="px-4 py-3">Calendar</th><th class="px-4 py-3">Efficiency</th><th class="px-4 py-3">FTE</th><th class="px-4 py-3">Rekomendasi</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assessments as $wla)
                        @php($isFinal = $wla->status === \App\Enums\WlaAssessmentStatus::Final)
                        @php($snapshot = $isFinal ? $wla->final_snapshot : null)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $isFinal ? data_get($snapshot, 'assessment_code', 'Snapshot tidak tersedia') : $wla->assessment_code }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'period', '—') : $wla->period }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'department.name', 'Snapshot tidak tersedia') : $wla->department->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'unit.name', 'Snapshot tidak tersedia') : $wla->unit->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'position.name', 'Snapshot tidak tersedia') : $wla->position->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'schedule.name', 'Snapshot tidak tersedia') : $wla->workSchedule->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'calendar.year', '—') : $wla->workCalendar->year }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'efficiency_factor', '—') : $wla->efficiency_factor }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'fte', '—') : $wla->fte }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $isFinal ? data_get($snapshot, 'recommended_employees', '—') : $wla->recommended_employees }}</td>
                            <td class="px-4 py-3"><x-ui.status-badge :tone="$isFinal ? 'success' : 'neutral'">{{ ucfirst($wla->status->value) }}</x-ui.status-badge></td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('wla.show', $wla) }}">Open</a>@can('update', $wla)<a class="font-semibold text-slate-600 hover:text-slate-900" href="{{ route('wla.edit', $wla) }}">Edit</a>@endcan</div></td>
                        </tr>
                    @empty
                        <tr><x-ui.empty-state colspan="12" title="Belum ada assessment" description="Buat assessment baru untuk memulai analisis beban kerja." /></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $assessments->links() }}</div>
    </section>
@endsection
