@extends('layouts.app')

@section('page-title', 'Ubah Work Schedule')

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Ubah Work Schedule" eyebrow="Parameter WLA"
            description="Perbarui pola jam dan hari kerja."
            :back-href="route('work-schedules.index')" back-label="Work Schedules" />
        @include('workforce.work-schedules._form', ['action' => route('work-schedules.update', $workSchedule), 'method' => 'PUT'])
    </div>
@endsection
