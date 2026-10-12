@extends('layouts.app')

@section('page-title', 'Ubah Position')

@section('content')
    <div class="mx-auto max-w-2xl">
        <div class="flex flex-wrap items-center gap-3 text-sm font-semibold">
            <a href="{{ route('organization.index') }}" class="text-sky-700 hover:text-sky-900">Master Organization</a>
            <span class="text-slate-300">/</span>
            <a href="{{ route('organization.positions.index') }}" class="text-sky-700 hover:text-sky-900">Position</a>
        </div>
        <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Ubah Position</h2>
        <p class="mt-1 text-sm text-slate-500">Perbarui Position dan Unit induknya.</p>
        @include('organization.positions._form', ['position' => $position, 'action' => route('organization.positions.update', $position), 'method' => 'PUT'])
    </div>
@endsection
