@extends('layouts.app')

@section('page-title', 'Ubah Department')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Ubah Department" eyebrow="Master organisasi"
            description="Perbarui kode, nama, atau status Department."
            :back-href="route('organization.departments.index')" back-label="Daftar Department" />
        @include('organization.departments._form', ['department' => $department, 'action' => route('organization.departments.update', $department), 'method' => 'PUT'])
    </div>
@endsection
