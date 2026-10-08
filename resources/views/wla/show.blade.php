@extends('layouts.app')

@php($displayAssessmentCode = $wla->status === \App\Enums\WlaAssessmentStatus::Final
    ? data_get($finalSnapshot, 'assessment_code', 'WLA Final')
    : $wla->assessment_code)

@section('page-title', $displayAssessmentCode)

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->has('finalization'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('finalization') }}</div>
        @endif
        @if ($errors->has('wla_export'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('wla_export') }}</div>
        @endif
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('wla') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; WLA Assessments</a>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <h2 class="font-display text-2xl font-bold text-slate-950">{{ $displayAssessmentCode }}</h2>
                    <span @class([
                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                        'bg-emerald-100 text-emerald-800' => $wla->status === \App\Enums\WlaAssessmentStatus::Final,
                        'bg-slate-100 text-slate-700' => $wla->status === \App\Enums\WlaAssessmentStatus::Draft,
                    ])>{{ ucfirst($wla->status->value) }}</span>
                </div>
                @if ($wla->status === \App\Enums\WlaAssessmentStatus::Final)
                    <p class="mt-1 text-sm text-slate-500">Period {{ data_get($finalSnapshot, 'period') }} · Finalized by {{ data_get($finalSnapshot, 'finalizer.name') }}</p>
                @else
                    <p class="mt-1 text-sm text-slate-500">Period {{ $wla->period }} · Created by {{ $wla->creator->name }}</p>
                @endif
            </div>
            <div class="flex items-center gap-3">
                @if ($wla->status === \App\Enums\WlaAssessmentStatus::Final)
                    @can('view', $wla)
                        <a href="{{ route('wla.print', $wla) }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cetak</a>
                        <a href="{{ route('wla.export-excel', $wla) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Export Excel</a>
                    @endcan
                @endif
                @can('update', $wla)
                    <a href="{{ route('wla.edit', $wla) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit Draft</a>
                    <form method="POST" action="{{ route('wla.destroy', $wla) }}" onsubmit="return confirm('Delete this draft and its activities?')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete Draft</button>
                    </form>
                @endcan
                @can('finalize', $wla)
                    <form method="POST" action="{{ route('wla.finalize', $wla) }}" onsubmit="return confirm('Finalisasi WLA ini? Data dan hasil perhitungan akan dibekukan.')">
                        @csrf
                        <button @disabled($finalizationReasons !== []) class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">Finalisasi WLA</button>
                    </form>
                @endcan
            </div>
        </div>

        @if ($wla->status === \App\Enums\WlaAssessmentStatus::Final)
            @include('wla._final-snapshot', ['snapshot' => $finalSnapshot])
        @else
            @can('finalize', $wla)
                @if ($finalizationReasons !== [])
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p class="font-semibold">WLA belum dapat difinalisasi:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($finalizationReasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endcan

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
        @endif
    </div>
@endsection
