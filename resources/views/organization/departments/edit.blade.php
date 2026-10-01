@extends('layouts.app')

@section('page-title', 'Ubah Department')

@section('content')
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('organization.departments.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; Department</a>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Ubah Department</h2>
        <p class="mt-1 text-sm text-slate-500">Perbarui kode, nama, atau status Department.</p>
        @include('organization.departments._form', ['department' => $department, 'action' => route('organization.departments.update', $department), 'method' => 'PUT'])
    </div>
@endsection