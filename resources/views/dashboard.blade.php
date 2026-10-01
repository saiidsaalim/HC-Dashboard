@extends('layouts.app')

@section('page-title', 'Dashboard')

@section('content')
    @php($toneClasses = ['amber' => 'bg-amber-50 text-amber-600', 'sky' => 'bg-sky-50 text-sky-600', 'emerald' => 'bg-emerald-50 text-emerald-600', 'violet' => 'bg-violet-50 text-violet-600'])
    <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end"><div><p class="text-sm text-slate-500">Selamat datang kembali, {{ auth()->user()->name }}.</p><h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">Ringkasan hari ini</h2></div><span class="inline-flex w-fit items-center gap-2 rounded-full bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Sistem aktif</span></div>
    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <article class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:shadow-lg"><div class="flex items-start justify-between"><div class="rounded-xl p-3 {{ $toneClasses[$stat['tone']] }}"><x-dashboard-icon :name="$stat['icon']" /></div><span class="text-xs font-semibold text-emerald-600">+{{ $loop->index + 3 }}.{{ $loop->index }}%</span></div><p class="mt-6 text-sm font-medium text-slate-500">{{ $stat['label'] }}</p><p class="mt-1 font-display text-3xl font-bold text-slate-950">{{ number_format($stat['value'], 0, ',', '.') }}</p><p class="mt-2 text-xs leading-5 text-slate-400">{{ $stat['description'] }}</p></article>
        @endforeach
    </div>
    <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><div><h3 class="font-display text-lg font-bold text-slate-950">Aktivitas terbaru</h3><p class="mt-1 text-sm text-slate-500">Pantau pembaruan data kepegawaian secara ringkas.</p></div><span class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">Hari ini</span></div><div class="mt-6 grid gap-3 md:grid-cols-3"><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Status data</p><p class="mt-2 text-sm font-semibold text-slate-700">Semua modul tersinkronisasi</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Perlu perhatian</p><p class="mt-2 text-sm font-semibold text-slate-700">12 pengajuan menunggu review</p></div><div class="rounded-xl bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Pembaruan terakhir</p><p class="mt-2 text-sm font-semibold text-slate-700">Beberapa detik yang lalu</p></div></div></div>
@endsection
