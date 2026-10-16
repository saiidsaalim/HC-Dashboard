@extends('layouts.app')

@section('page-title', 'Tambah Department')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Tambah Department" eyebrow="Master organisasi"
            description="Buat master Department untuk struktur organisasi."
            :back-href="route('organization.departments.index')" back-label="Daftar Department" />
        @include('organization.departments._form', ['department' => null, 'action' => route('organization.departments.store'), 'method' => 'POST'])
    </div>
@endsection
