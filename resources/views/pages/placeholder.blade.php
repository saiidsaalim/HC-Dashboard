@extends('layouts.app')

@section('page-title', $title)

@section('content')
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-600">
            <x-dashboard-icon name="document" class="h-7 w-7" /></div>
        <h2 class="mt-5 font-display text-2xl font-bold text-slate-950">{{ $title }}</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">{{ $description }}</p>
        <p class="mt-6 text-xs font-semibold uppercase tracking-widest text-slate-400">Modul siap dikembangkan</p>
    </div>
@endsection
