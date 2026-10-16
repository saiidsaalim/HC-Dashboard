@extends('layouts.app')

@section('page-title', 'Tambah Work Schedule')

@section('content')
    <div class="mx-auto max-w-3xl">
        <x-ui.page-header title="Tambah Work Schedule" eyebrow="Parameter WLA"
            description="Buat pola jam dan hari kerja untuk assessment WLA."
            :back-href="route('work-schedules.index')" back-label="Work Schedules" />
        @include('workforce.work-schedules._form', ['workSchedule' => null, 'action' => route('work-schedules.store'), 'method' => 'POST'])
    </div>
@endsection
