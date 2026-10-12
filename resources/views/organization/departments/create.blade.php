@extends('layouts.app')

@section('page-title', 'Tambah Department')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="flex flex-wrap items-center gap-3 text-sm font-semibold">
            <a href="{{ route('organization.index') }}" class="text-sky-700 hover:text-sky-900">Master Organization</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('organization.departments.index') }}" class="text-sky-700 hover:text-sky-900">Department</a>
        </div>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Tambah Department</h2>
        <p class="mt-1 text-sm text-slate-500">Buat master Department untuk struktur organisasi.</p>
        @include('organization.departments._form', ['department' => null, 'action' => route('organization.departments.store'), 'method' => 'POST'])
    </div>
@endsection
