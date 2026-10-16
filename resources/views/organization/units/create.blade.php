@extends('layouts.app')

@section('page-title', 'Tambah Unit')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Tambah Unit" eyebrow="Master organisasi"
            description="Pilih Department aktif sebagai induk Unit ini."
            :back-href="route('organization.units.index')" back-label="Daftar Unit" />
        @if ($departments->isEmpty())
            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Belum ada Department aktif. Aktifkan atau buat Department terlebih dahulu.
            </div>
        @else
            @include('organization.units._form', ['unit' => null, 'action' => route('organization.units.store'), 'method' => 'POST'])
        @endif
    </div>
@endsection
