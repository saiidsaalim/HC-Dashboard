@extends('layouts.app')

@section('page-title', 'Tambah Work Calendar')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('work-calendars.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; Work Calendars</a>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Tambah Work Calendar</h2>
        @include('workforce.work-calendars._form', ['workCalendar' => null, 'action' => route('work-calendars.store'), 'method' => 'POST'])
    </div>
@endsection
