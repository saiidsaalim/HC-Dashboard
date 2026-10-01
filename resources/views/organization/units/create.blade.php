@extends('layouts.app')

@section('page-title', 'Tambah Unit')

@section('content')
    <div class="mx-auto max-w-2xl"><a href="{{ route('organization.units.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; Unit</a><h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Tambah Unit</h2><p class="mt-1 text-sm text-slate-500">Pilih Department induk untuk Unit ini.</p>
        @if ($departments->isEmpty())<div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Buat Department terlebih dahulu.</div>@else@include('organization.units._form', ['unit' => null, 'action' => route('organization.units.store'), 'method' => 'POST'])@endif
    </div>
@endsection