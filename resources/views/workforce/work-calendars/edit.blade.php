@extends('layouts.app')

@section('page-title', 'Ubah Work Calendar')

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Ubah Work Calendar" eyebrow="Parameter WLA"
            description="Perbarui parameter kalender kerja tahunan."
            :back-href="route('work-calendars.index')" back-label="Work Calendars" />
        @include('workforce.work-calendars._form', ['action' => route('work-calendars.update', $workCalendar), 'method' => 'PUT'])
    </div>
@endsection
