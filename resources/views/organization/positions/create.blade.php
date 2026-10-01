@extends('layouts.app')

@section('page-title', 'Tambah Position')

@section('content')
    <div class="mx-auto max-w-2xl"><a href="{{ route('organization.positions.index') }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; Position</a><h2 class="mt-3 font-display text-2xl font-bold text-slate-950">Tambah Position</h2><p class="mt-1 text-sm text-slate-500">Pilih Department, lalu Unit yang berada di dalamnya.</p>
        @if ($units->isEmpty())<div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Buat Department dan Unit terlebih dahulu.</div>@else@include('organization.positions._form', ['position' => null, 'action' => route('organization.positions.store'), 'method' => 'POST'])@endif
    </div>
@endsection