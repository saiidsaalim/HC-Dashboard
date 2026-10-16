@extends('layouts.app')

@section('page-title', 'New WLA Draft')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <x-ui.page-header title="Draft WLA Baru" eyebrow="Workload Analysis"
            description="Tentukan konteks assessment sebelum menambahkan aktivitas."
            :back-href="route('wla')" back-label="WLA Assessments" class="mb-0" />
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        @include('wla._assessment-form', ['action' => route('wla.store'), 'method' => 'POST'])

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-semibold uppercase text-slate-500">Bagian 3</p>
            <h3 class="mt-1 font-display text-lg font-bold text-slate-950">Aktivitas</h3>
            <p class="mt-3 text-sm text-slate-600">Simpan draft untuk menambah, mengubah, atau menghapus aktivitas.</p>
        </section>

        @include('wla._foundation-preview', ['calculationState' => [
            'calculation_available' => false,
            'calculation_complete' => false,
            'unavailable_reason' => 'Simpan draft untuk menjalankan kalkulasi WLA.',
            'preview' => true,
        ]])
    </div>
@endsection
