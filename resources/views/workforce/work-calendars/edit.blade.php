@extends('layouts.app')

@section('page-title', 'Ubah Work Calendar')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('work-calendars.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; Work Calendars</a>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Ubah Work Calendar</h2>
        @include('workforce.work-calendars._form', ['action' => route('work-calendars.update', $workCalendar), 'method' => 'PUT'])
    </div>
@endsection
