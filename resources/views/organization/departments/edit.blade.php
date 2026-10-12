@extends('layouts.app')

@section('page-title', 'Ubah Department')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="flex flex-wrap items-center gap-3 text-sm font-semibold">
            <a href="{{ route('organization.index') }}" class="text-sky-700 hover:text-sky-900">Master Organization</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('organization.departments.index') }}" class="text-sky-700 hover:text-sky-900">Department</a>
        </div>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Ubah Department</h2>
        <p class="mt-1 text-sm text-slate-500">Perbarui kode, nama, atau status Department.</p>
        @include('organization.departments._form', ['department' => $department, 'action' => route('organization.departments.update', $department), 'method' => 'PUT'])
    </div>
@endsection
