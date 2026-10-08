@extends('layouts.app')

@section('page-title', $wla->assessment_code)

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('wla') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; WLA Assessments</a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h2 class="font-display text-2xl font-bold text-slate-950">{{ $wla->assessment_code }}</h2>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ ucfirst($wla->status->value) }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-500">Period {{ $wla->period }} · Created by {{ $wla->creator->name }}</p>
            </div>
            <div class="flex items-center gap-3">
                @can('update', $wla)
                    <a href="{{ route('wla.edit', $wla) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit Draft</a>
                    <form method="POST" action="{{ route('wla.destroy', $wla) }}" onsubmit="return confirm('Delete this draft and its activities?')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete Draft</button>
                    </form>
                @endcan
            </div>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h3 class="font-display text-lg font-bold text-slate-950">Assessment Context</h3>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-xs uppercase text-slate-500">Department</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->department->name }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Unit</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->unit->name }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Position</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->position->name }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Work Schedule</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->workSchedule->name }} · {{ $wla->workSchedule->calculationTypeForWla()?->label() ?? 'Perlu klasifikasi' }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Work Calendar</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->workCalendar->year }} · {{ $calculationState['total_days'] }} hari · cuti tahunan {{ $wla->workCalendar->annual_leave }} · libur nasional {{ $wla->workCalendar->national_holiday }} · cuti bersama {{ $wla->workCalendar->common_leave }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Efficiency Factor</dt><dd class="mt-1 font-medium text-slate-800">{{ number_format((float) $wla->efficiency_factor * 100, 2) }}%</dd></div>
            </dl>
        </section>

        @include('wla._activities', ['wla' => $wla])
        @include('wla._foundation-preview', ['assessment' => $wla, 'calculationState' => $calculationState])
    </div>
@endsection
