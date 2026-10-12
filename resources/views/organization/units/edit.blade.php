@extends('layouts.app')

@section('page-title', 'Ubah Unit')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="flex flex-wrap items-center gap-3 text-sm font-semibold">
            <a href="{{ route('organization.index') }}" class="text-sky-700 hover:text-sky-900">Master Organization</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('organization.units.index') }}" class="text-sky-700 hover:text-sky-900">Unit</a>
        </div>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Ubah Unit</h2>
        <p class="mt-1 text-sm text-slate-500">Perbarui Unit dan Department induknya.</p>
        @include('organization.units._form', ['unit' => $unit, 'action' => route('organization.units.update', $unit), 'method' => 'PUT'])
    </div>
@endsection
