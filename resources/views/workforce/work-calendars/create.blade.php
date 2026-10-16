@extends('layouts.app')

@section('page-title', 'Tambah Work Calendar')

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Tambah Work Calendar" eyebrow="Parameter WLA"
            description="Siapkan kalender kerja tahunan untuk perhitungan WLA."
            :back-href="route('work-calendars.index')" back-label="Work Calendars" />
        @include('workforce.work-calendars._form', ['workCalendar' => null, 'action' => route('work-calendars.store'), 'method' => 'POST'])
    </div>
@endsection
