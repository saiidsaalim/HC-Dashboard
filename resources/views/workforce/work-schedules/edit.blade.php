@extends('layouts.app')

@section('page-title', 'Ubah Work Schedule')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('work-schedules.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; Work Schedules</a>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Ubah Work Schedule</h2>
        @include('workforce.work-schedules._form', ['action' => route('work-schedules.update', $workSchedule), 'method' => 'PUT'])
    </div>
@endsection
