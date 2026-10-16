@extends('layouts.app')

@section('page-title', 'WLA Saya')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('wla.staff.index') }}" class="ui-button ui-button-secondary w-fit"
                    aria-label="Kembali ke WLA Saya">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                        stroke-width="1.8" aria-hidden="true">
                        <path d="M16 10H4m5-5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    WLA Saya
                </a>
                <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">{{ $participant->assessment->assessment_code }}</h2>
                <p class="mt-1 text-sm text-slate-500">Periode {{ $participant->assessment->period }} · {{ $participant->assessment->position->name }}</p>
            </div>
            <span class="w-fit rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ $participant->status->label() }}</span>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div><dt class="text-xs uppercase text-slate-500">Department</dt><dd class="mt-1 font-semibold text-slate-800">{{ $participant->assessment->department->name }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Unit</dt><dd class="mt-1 font-semibold text-slate-800">{{ $participant->assessment->unit->name }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Position</dt><dd class="mt-1 font-semibold text-slate-800">{{ $participant->assessment->position->name }}</dd></div>
            </dl>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Penugasan</p>
            @if ($participant->assigner)
                <p class="mt-1 font-semibold text-slate-800">Ditugaskan oleh: {{ $participant->assigner->name }} ({{ $participant->assigner->roleEnum()->value }})</p>
                <p class="mt-1 text-sm text-slate-500">{{ $participant->created_at->translatedFormat('d F Y, H.i') }}</p>
            @else
                <p class="mt-1 text-sm text-slate-500">Pemberi tugas tidak tercatat (data lama)</p>
            @endif
        </section>

        @if ($participant->status === \App\Enums\WlaParticipantStatus::Returned && $participant->review_note)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-semibold">Catatan Manager</p>
                <p class="mt-1 whitespace-pre-line">{{ $participant->review_note }}</p>
            </div>
        @endif

        @if (! $isEditable)
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">Pengisian ini bersifat read-only pada status saat ini.</div>
        @endif

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase text-slate-500">Aktivitas Anda</p><h3 class="mt-1 font-display text-lg font-bold text-slate-950">Rincian Beban Kerja</h3></div>
                <div class="text-right"><p class="text-xs uppercase text-slate-500">Subtotal beban tahunan</p><p class="text-xl font-bold text-slate-950">{{ number_format((float) $subtotal, 4, ',', '.') }} jam</p></div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[920px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr><th class="px-3 py-3">No</th><th class="px-3 py-3">Aktivitas</th><th class="px-3 py-3">Frekuensi</th><th class="px-3 py-3">Periode</th><th class="px-3 py-3">Waktu (Jam)</th><th class="px-3 py-3">Beban Tahunan (Jam)</th><th class="px-3 py-3">Aksi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($participant->activities as $activity)
                            @php($formId = 'participant-activity-'.$activity->id)
                            <tr>
                                <td class="px-3 py-3">{{ $loop->iteration }}</td>
                                <td class="px-3 py-3">
                                    @if ($isEditable)<input form="{{ $formId }}" name="activity_name" value="{{ $activity->activity_name }}" required maxlength="255" class="w-72 rounded-lg border-slate-300 text-sm">@else{{ $activity->activity_name }}@endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($isEditable)<input form="{{ $formId }}" type="number" name="frequency" value="{{ $activity->frequency }}" min="0.01" max="{{ \App\Models\WlaActivity::MAX_INPUT_VALUE }}" step="0.01" required class="w-28 rounded-lg border-slate-300 text-sm">@else{{ $activity->frequency }}@endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($isEditable)<select form="{{ $formId }}" name="period_unit" required class="rounded-lg border-slate-300 text-sm">@foreach (\App\Enums\WlaPeriodUnit::cases() as $unit)<option value="{{ $unit->value }}" @selected($activity->period_unit === $unit)>{{ $unit->value }}</option>@endforeach</select>@else{{ $activity->period_unit->value }}@endif
                                </td>
                                <td class="px-3 py-3">
                                    @if ($isEditable)<input form="{{ $formId }}" type="number" name="time_allocated_hours" value="{{ $activity->time_allocated_hours }}" min="0.01" max="{{ \App\Models\WlaActivity::MAX_INPUT_VALUE }}" step="0.01" required class="w-32 rounded-lg border-slate-300 text-sm">@else{{ $activity->time_allocated_hours }}@endif
                                </td>
                                <td class="px-3 py-3 font-semibold text-slate-700">{{ $activity->annual_workload_hours }}</td>
                                <td class="px-3 py-3">
                                    @if ($isEditable)
                                        <div class="flex gap-3">
                                            <form id="{{ $formId }}" method="POST" action="{{ route('wla.staff.activities.update', [$participant, $activity]) }}">@csrf @method('PUT')<button class="font-semibold text-sky-700">Simpan</button></form>
                                            <form method="POST" action="{{ route('wla.staff.activities.destroy', [$participant, $activity]) }}" data-confirm-submit data-confirm-title="Hapus aktivitas ini?" data-confirm-text="Aktivitas yang dihapus tidak dapat dikembalikan." data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626">@csrf @method('DELETE')<button class="font-semibold text-red-600">Hapus</button></form>
                                        </div>
                                    @else
                                        <span class="text-slate-400">Read-only</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-8 text-center text-slate-500">Belum ada aktivitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($isEditable)
                <form method="POST" action="{{ route('wla.staff.activities.store', $participant) }}" class="mt-5 border-t border-slate-200 pt-5">
                    @csrf
                    <h4 class="font-semibold text-slate-800">Tambah Aktivitas</h4>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="text-xs font-medium text-slate-600">Nama Aktivitas<input name="activity_name" required maxlength="255" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                        <label class="text-xs font-medium text-slate-600">Frekuensi<input type="number" name="frequency" min="0.01" max="{{ \App\Models\WlaActivity::MAX_INPUT_VALUE }}" step="0.01" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                        <label class="text-xs font-medium text-slate-600">Periode<select name="period_unit" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm">@foreach (\App\Enums\WlaPeriodUnit::cases() as $unit)<option value="{{ $unit->value }}">{{ $unit->value }}</option>@endforeach</select></label>
                        <label class="text-xs font-medium text-slate-600">Waktu per Aktivitas (Jam)<input type="number" name="time_allocated_hours" min="0.01" max="{{ \App\Models\WlaActivity::MAX_INPUT_VALUE }}" step="0.01" required class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></label>
                    </div>
                    <button class="mt-4 rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Tambah Aktivitas</button>
                </form>
            @endif
        </section>

        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">Hasil pengisian ini digunakan sebagai bahan analisis kebutuhan tenaga kerja dan bukan penilaian kinerja individu.</div>

        @if ($isEditable)
            <div class="flex flex-col items-stretch justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center">
                <div><p class="font-semibold text-slate-900">Kelengkapan: {{ $isComplete ? 'Lengkap' : 'Belum lengkap' }}</p><p class="text-sm text-slate-500">Minimal satu aktivitas diperlukan sebelum pengiriman.</p></div>
                <form method="POST" action="{{ route('wla.staff.submit', $participant) }}" data-confirm-submit data-confirm-title="Kirim WLA ke Manager?" data-confirm-text="Setelah dikirim, aktivitas tidak dapat diubah sampai dikembalikan oleh Manager." data-confirm-button-text="Ya, kirim" data-confirm-button-color="#059669">@csrf<button @disabled(! $isComplete) class="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50 sm:w-auto">Kirim ke Manager</button></form>
            </div>
        @endif

    </div>
@endsection
