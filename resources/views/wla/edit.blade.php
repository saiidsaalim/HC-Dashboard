@extends('layouts.app')

@section('page-title', 'Edit WLA Draft')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <x-ui.page-header title="Ubah Draft WLA" eyebrow="Workload Analysis"
            description="Perbarui konteks dan aktivitas assessment sebelum finalisasi."
            :back-href="route('wla.show', $assessment)" :back-label="$assessment->assessment_code" class="mb-0" />
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @include('wla._assessment-form', ['action' => route('wla.update', $assessment), 'method' => 'PUT'])
        @include('wla._activities', ['wla' => $assessment])
        @include('wla._foundation-preview', ['assessment' => $assessment, 'calculationState' => $calculationState])
    </div>
@endsection
