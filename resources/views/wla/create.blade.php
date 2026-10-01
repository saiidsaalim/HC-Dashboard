@extends('layouts.app')

@section('page-title', 'New WLA Draft')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <a href="{{ route('wla') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; WLA Assessments</a>
            <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">New WLA Draft</h2>
        </div>
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @include('wla._assessment-form', ['action' => route('wla.store'), 'method' => 'POST'])

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase text-slate-500">Section 3</p>
            <h3 class="mt-1 font-display text-lg font-bold text-slate-950">Activities</h3>
            <p class="mt-3 text-sm text-slate-600">Save the draft to add, edit, or remove activity rows.</p>
        </section>

        @include('wla._foundation-preview', ['foundationPreview' => ['working_days' => 'Pending Calculation', 'working_hours_year' => 'Pending Calculation', 'effective_working_hours' => 'Pending Calculation', 'preview' => true]])
    </div>
@endsection