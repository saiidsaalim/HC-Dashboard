@extends('layouts.app')

@section('page-title', 'Tambah Position')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Tambah Position" eyebrow="Master organisasi"
            description="Pilih Department, Unit, lalu Section sebagai induk Position."
            :back-href="route('organization.positions.index')" back-label="Daftar Position" />
        @if ($units->isEmpty())
            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Belum ada Unit aktif di dalam Department aktif. Siapkan Department dan Unit terlebih dahulu.
            </div>
        @else
            @include('organization.positions._form', ['position' => null, 'action' => route('organization.positions.store'), 'method' => 'POST'])
        @endif
    </div>
@endsection
