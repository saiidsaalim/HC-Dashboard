@extends('layouts.app')

@section('page-title', 'Ubah Position')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Ubah Position" eyebrow="Master organisasi"
            description="Perbarui Position dan struktur Directorate, Department, Unit, serta Section induknya."
            :back-href="route('organization.positions.index')" back-label="Daftar Position" />
        @include('organization.positions._form', ['position' => $position, 'action' => route('organization.positions.update', $position), 'method' => 'PUT'])
    </div>
@endsection
