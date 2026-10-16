@extends('layouts.app')

@php($displayAssessmentCode = $wla->status === \App\Enums\WlaAssessmentStatus::Final
    ? data_get($finalSnapshot, 'assessment_code', 'WLA Final')
    : $wla->assessment_code)
@php($assessmentDescription = $wla->status === \App\Enums\WlaAssessmentStatus::Final
    ? 'Periode '.data_get($finalSnapshot, 'period').' · Difinalisasi oleh '.data_get($finalSnapshot, 'finalizer.name')
    : 'Periode '.$wla->period.' · Dibuat oleh '.$wla->creator->name)

@section('page-title', $displayAssessmentCode)

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->has('finalization'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('finalization') }}</div>
        @endif
        @if ($errors->has('wla_export'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first('wla_export') }}</div>
        @endif
        <x-ui.page-header :title="$displayAssessmentCode" eyebrow="Workload Analysis"
            :description="$assessmentDescription" :back-href="route('wla')" back-label="WLA Assessments" class="mb-0">
            <x-slot:actions>
                <x-ui.status-badge :tone="$wla->status === \App\Enums\WlaAssessmentStatus::Final ? 'success' : 'neutral'">
                    {{ ucfirst($wla->status->value) }}
                </x-ui.status-badge>
                @if ($wla->status === \App\Enums\WlaAssessmentStatus::Final)
                    @can('view', $wla)
                        <a href="{{ route('wla.print', $wla) }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cetak</a>
                        <a href="{{ route('wla.export-excel', $wla) }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600">Export Excel</a>
                    @endcan
                @endif
                @can('update', $wla)
                    <a href="{{ route('wla.edit', $wla) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit Draft</a>
                    <form method="POST" action="{{ route('wla.destroy', $wla) }}" data-confirm-submit data-confirm-title="Hapus Draft WLA ini?" data-confirm-text="Seluruh aktivitas dalam Draft ini akan ikut dihapus dan tidak dapat dikembalikan." data-confirm-button-text="Ya, hapus Draft" data-cancel-button-text="Batal" data-confirm-button-color="#dc2626" data-cancel-button-color="#64748b">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">Delete Draft</button>
                    </form>
                @endcan
                @can('finalize', $wla)
                    <form method="POST" action="{{ route('wla.finalize', $wla) }}" data-confirm-submit data-confirm-title="Finalisasi WLA ini?" data-confirm-text="Setelah difinalisasi, assessment dan aktivitas tidak dapat diubah atau dihapus." data-confirm-button-text="Ya, finalisasi" data-cancel-button-text="Periksa kembali" data-confirm-button-color="#059669" data-cancel-button-color="#64748b">
                        @csrf
                        <button @disabled($finalizationReasons !== []) class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">Finalisasi WLA</button>
                    </form>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        @if ($wla->status === \App\Enums\WlaAssessmentStatus::Final)
            @include('wla._final-snapshot', ['snapshot' => $finalSnapshot])
        @else
            @can('finalize', $wla)
                @if ($finalizationReasons !== [])
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        <p class="font-semibold">WLA belum dapat difinalisasi:</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($finalizationReasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endcan

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h3 class="font-display text-lg font-bold text-slate-950">Assessment Context</h3>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-xs uppercase text-slate-500">Department</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->department->name }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">Unit</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->unit->name }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">Position</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->position->name }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">Work Schedule</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->workSchedule->name }} · {{ $wla->workSchedule->calculationTypeForWla()?->label() ?? 'Perlu klasifikasi' }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">Work Calendar</dt><dd class="mt-1 font-medium text-slate-800">{{ $wla->workCalendar->year }} · {{ $calculationState['total_days'] }} hari · cuti tahunan {{ $wla->workCalendar->annual_leave }} · libur nasional {{ $wla->workCalendar->national_holiday }} · cuti bersama {{ $wla->workCalendar->common_leave }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">Efficiency Factor</dt><dd class="mt-1 font-medium text-slate-800">{{ number_format((float) $wla->efficiency_factor * 100, 2) }}%</dd></div>
                </dl>
            </section>

            @include('wla._activities', ['wla' => $wla])

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">Pengisian Staff</p>
                        <h3 class="mt-1 font-display text-lg font-bold text-slate-950">Peserta dan Status Pengisian</h3>
                    </div>
                    @can('manageParticipants', [\App\Models\WlaParticipant::class, $wla])
                        <form method="POST" action="{{ route('wla.participants.store', $wla) }}" class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-end">
                            @csrf
                            <label class="min-w-0 text-xs font-medium text-slate-600">Tambahkan Staff
                                <select name="employee_id" required class="mt-1 block w-full min-w-0 rounded-lg border-slate-300 text-sm sm:min-w-64">
                                    <option value="">Pilih Employee</option>
                                    @foreach ($participantOptions as $employee)
                                        <option value="{{ $employee->id }}">{{ $employee->user?->name ?? $employee->personal_number ?? $employee->sap }} · {{ $employee->sap }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button @disabled($participantOptions->isEmpty()) class="w-full rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 sm:w-auto">Tambah Peserta</button>
                        </form>
                    @endcan
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-3">Staff</th><th class="px-3 py-3">Status</th><th class="px-3 py-3">Dikirim</th><th class="px-3 py-3">Direview</th><th class="px-3 py-3">Pemakaian</th><th class="px-3 py-3 text-right">Aksi</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($wla->participants as $participant)
                                <tr>
                                    <td class="px-3 py-3">
                                        <p class="font-semibold text-slate-800">{{ $participant->employee->user?->name ?? $participant->employee->personal_number ?? $participant->employee->sap }}</p>
                                        @if ($participant->assigner)
                                            <p class="mt-1 text-xs text-slate-600">Ditugaskan oleh: {{ $participant->assigner->name }} ({{ $participant->assigner->roleEnum()->value }})</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $participant->created_at->translatedFormat('d F Y, H.i') }}</p>
                                        @else
                                            <p class="mt-1 text-xs text-slate-500">Pemberi tugas tidak tercatat (data lama)</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $participant->status->label() }}</span></td>
                                    <td class="px-3 py-3 text-slate-600">{{ $participant->submitted_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-3 text-slate-600">{{ $participant->reviewed_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-3 text-slate-600">{{ $participant->applied_at ? 'Sudah digunakan' : 'Belum digunakan' }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex justify-end gap-3">
                                            <a href="{{ route('wla.participants.show', [$wla, $participant]) }}" class="font-semibold text-sky-700 hover:text-sky-900">Review</a>
                                            @can('manageParticipants', [\App\Models\WlaParticipant::class, $wla])
                                                <form method="POST" action="{{ route('wla.participants.destroy', [$wla, $participant]) }}" data-confirm-submit data-confirm-title="Hapus peserta ini?" data-confirm-text="Seluruh aktivitas pengisian peserta akan dihapus." data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626">@csrf @method('DELETE')<button class="font-semibold text-red-600">Hapus</button></form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-3 py-8 text-center text-slate-500">Belum ada Staff yang ditugaskan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @include('wla._foundation-preview', ['assessment' => $wla, 'calculationState' => $calculationState])
        @endif

    </div>
@endsection
