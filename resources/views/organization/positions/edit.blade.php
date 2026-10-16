@extends('layouts.app')

@section('page-title', 'Ubah Position')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Ubah Position" eyebrow="Master organisasi"
            description="Perbarui Position dan Unit induknya."
            :back-href="route('organization.positions.index')" back-label="Daftar Position" />
        @include('organization.positions._form', ['position' => $position, 'action' => route('organization.positions.update', $position), 'method' => 'PUT'])
    </div>
@endsection
