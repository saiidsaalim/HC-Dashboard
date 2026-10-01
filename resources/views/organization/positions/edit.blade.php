@extends('layouts.app')

@section('page-title', 'Ubah Position')

@section('content')
    <div class="mx-auto max-w-2xl"><a href="{{ route('organization.positions.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; Position</a><h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Ubah Position</h2><p class="mt-1 text-sm text-slate-500">Perbarui Position dan Unit induknya.</p>
        @include('organization.positions._form', ['position' => $position, 'action' => route('organization.positions.update', $position), 'method' => 'PUT'])
    </div>
@endsection