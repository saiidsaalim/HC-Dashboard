@extends('layouts.app')

@section('page-title', 'Ubah Unit')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Ubah Unit" eyebrow="Master organisasi"
            description="Perbarui Unit dan Department induknya."
            :back-href="route('organization.units.index')" back-label="Daftar Unit" />
        @include('organization.units._form', ['unit' => $unit, 'action' => route('organization.units.update', $unit), 'method' => 'PUT'])
    </div>
@endsection
