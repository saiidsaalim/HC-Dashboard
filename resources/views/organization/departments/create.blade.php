@extends('layouts.app')

@section('page-title', 'Tambah Department')

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('organization.departments.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; Department</a>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Tambah Department</h2>
        <p class="mt-1 text-sm text-slate-500">Buat master Department untuk struktur organisasi.</p>
        @include('organization.departments._form', ['department' => null, 'action' => route('organization.departments.store'), 'method' => 'POST'])
    </div>
@endsection