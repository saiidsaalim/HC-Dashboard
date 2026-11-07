@extends('layouts.app')

@section('page-title', 'Tetapkan Kebutuhan Formasi')

@section('content')
    @include('pages._formasi-tabs')

    @php
        $hasOldRequirementInput = is_scalar(old('formation_position_id')) && (string) old('formation_position_id') === (string) $position->id;
        $requirementInput = $hasOldRequirementInput ? old('formation_count') : $position->formation_count;
        $requirementInput = is_scalar($requirementInput) ? $requirementInput : '';
    @endphp
    <div class="mx-auto max-w-2xl">
        <x-ui.page-header title="Tetapkan Kebutuhan Formasi" eyebrow="Formasi"
            description="Tetapkan jumlah kebutuhan untuk posisi ini secara manual."
            :back-href="$returnUrl" back-label="Analisis Formasi" />

        <section class="ui-surface mb-5 p-5 sm:p-6" aria-label="Posisi yang dipilih">
            <h2 class="font-display text-xl font-bold text-slate-950">{{ $position->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">Position ID {{ $position->id }} · {{ $position->code }}{{ $position->active ? '' : ' · Nonaktif' }}</p>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                @foreach ([
                    'Level' => $position->job_level ?: 'Belum diisi',
                    'Directorate' => $position->unit?->department?->directorate?->name ?? 'Belum terhubung',
                    'Department' => $position->unit?->department?->name ?? 'Belum terhubung',
                    'Unit' => $position->unit?->name ?? 'Belum terhubung',
                    'Section' => $position->section?->name ?? 'Tanpa Section',
                    'Pegawai Terhubung' => number_format($position->employees_count, 0, ',', '.'),
                ] as $label => $value)
                    <div>
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 font-semibold text-slate-800">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-5 text-sm text-slate-600">
                Kebutuhan saat ini: <strong>{{ $position->formation_is_confirmed ? $position->formation_count : 'Belum ditetapkan' }}</strong>.
                Pegawai terhubung dihitung dari Position ID; kelengkapan pemetaan belum dipastikan.
            </p>
        </section>

        <form method="POST" action="{{ route('formasi.requirement.update', $position) }}" class="ui-surface space-y-5 p-5 sm:p-6">
            @csrf
            @method('PATCH')
            @foreach ($returnParameters as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <div>
                <label for="formation_count" class="text-sm font-semibold text-slate-700">Jumlah Kebutuhan Formasi</label>
                <input id="formation_count" name="formation_count" type="number" required min="1" max="999"
                    value="{{ $requirementInput }}" aria-describedby="formation-help"
                    class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                <p id="formation-help" class="mt-1 text-xs text-slate-500">Nilai awal belum dianggap sebagai kebutuhan sampai ditetapkan.</p>
                @error('formation_count')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="inline-flex items-start gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="confirm_formation" value="1" required @checked($hasOldRequirementInput && in_array(old('confirm_formation'), ['yes', 'on', 1, '1', true, 'true'], true))
                        class="mt-0.5 rounded border-slate-300 text-amber-500 focus:ring-amber-400">
                    Saya menetapkan jumlah di atas sebagai kebutuhan formasi posisi ini.
                </label>
                @error('confirm_formation')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                <a href="{{ $returnUrl }}" class="ui-button ui-button-secondary w-full sm:w-auto">Batal</a>
                <button class="ui-button ui-button-primary w-full sm:w-auto">Tetapkan Kebutuhan</button>
            </div>
        </form>
    </div>
@endsection
