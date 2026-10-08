@extends('layouts.app')

@section('page-title', 'Edit WLA Draft')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <a href="{{ route('wla.show', $assessment) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; {{ $assessment->assessment_code }}</a>
            <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Edit WLA Draft</h2>
        </div>
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @include('wla._assessment-form', ['action' => route('wla.update', $assessment), 'method' => 'PUT'])
        @include('wla._activities', ['wla' => $assessment])
        @include('wla._foundation-preview', ['assessment' => $assessment, 'calculationState' => $calculationState])
    </div>
@endsection
