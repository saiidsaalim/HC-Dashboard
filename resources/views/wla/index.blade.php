@extends('layouts.app')

@section('page-title', 'WLA Assessment')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm text-slate-500">Draft workload assessments and activity inputs.</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-950">WLA Assessments</h2>
        </div>
        @can('create', \App\Models\WlaAssessment::class)
            <a href="{{ route('wla.create') }}" class="w-fit rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">New Assessment</a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1120px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                    <tr><th class="px-4 py-3">Assessment</th><th class="px-4 py-3">Period</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Position</th><th class="px-4 py-3">Schedule</th><th class="px-4 py-3">Calendar</th><th class="px-4 py-3">Efficiency</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assessments as $wla)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $wla->assessment_code }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $wla->period }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $wla->department->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $wla->unit->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $wla->position->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $wla->workSchedule->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $wla->workCalendar->year }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ number_format((float) $wla->efficiency_factor * 100, 2) }}%</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ ucfirst($wla->status->value) }}</span></td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('wla.show', $wla) }}">Open</a>@can('update', $wla)<a class="font-semibold text-slate-600 hover:text-slate-900" href="{{ route('wla.edit', $wla) }}">Edit</a>@endcan</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-12 text-center text-slate-500">No assessments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $assessments->links() }}</div>
    </section>
@endsection