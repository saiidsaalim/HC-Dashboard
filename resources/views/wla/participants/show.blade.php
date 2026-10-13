@extends('layouts.app')

@section('page-title', 'Review Pengisian Staff')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif

        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <a href="{{ route('wla.show', $wla) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900">&larr; {{ $wla->assessment_code }}</a>
                <h2 class="mt-3 font-display text-2xl font-bold text-slate-950">{{ $participant->employee->user?->name ?? $participant->employee->personal_number ?? $participant->employee->sap }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $wla->department->name }} · {{ $wla->unit->name }} · {{ $wla->position->name }}</p>
            </div>
            <span class="w-fit rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ $participant->status->label() }}</span>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <dl class="grid gap-4 sm:grid-cols-5">
                <div><dt class="text-xs uppercase text-slate-500">Ditugaskan oleh</dt><dd class="mt-1 font-semibold text-slate-800">{{ $participant->assigner ? $participant->assigner->name.' ('.$participant->assigner->roleEnum()->value.')' : 'Pemberi tugas tidak tercatat (data lama)' }}</dd><dd class="mt-0.5 text-xs text-slate-500">{{ $participant->created_at->translatedFormat('d F Y, H.i') }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Dikirim</dt><dd class="mt-1 font-semibold text-slate-800">{{ $participant->submitted_at?->translatedFormat('d M Y H:i') ?? 'Belum dikirim' }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Direview</dt><dd class="mt-1 font-semibold text-slate-800">{{ $participant->reviewed_at?->translatedFormat('d M Y H:i') ?? 'Belum direview' }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Reviewer</dt><dd class="mt-1 font-semibold text-slate-800">{{ $participant->reviewer?->name ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Subtotal</dt><dd class="mt-1 font-semibold text-slate-800">{{ number_format((float) $subtotal, 4, ',', '.') }} jam</dd></div>
            </dl>
        </section>

        @if ($participant->review_note)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><span class="font-semibold">Catatan review:</span> {{ $participant->review_note }}</div>
        @endif

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5"><h3 class="font-display text-lg font-bold text-slate-950">Aktivitas Staff</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">No</th><th class="px-4 py-3">Aktivitas</th><th class="px-4 py-3">Frekuensi</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Waktu (Jam)</th><th class="px-4 py-3">Beban Tahunan (Jam)</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($participant->activities as $activity)
                            <tr><td class="px-4 py-3">{{ $loop->iteration }}</td><td class="px-4 py-3 font-medium text-slate-800">{{ $activity->activity_name }}</td><td class="px-4 py-3 text-slate-600">{{ $activity->frequency }}</td><td class="px-4 py-3 text-slate-600">{{ $activity->period_unit->value }}</td><td class="px-4 py-3 text-slate-600">{{ $activity->time_allocated_hours }}</td><td class="px-4 py-3 font-semibold text-slate-700">{{ $activity->annual_workload_hours }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Pengisian belum mempunyai aktivitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @can('review', $participant)
            <section class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-2">
                <form method="POST" action="{{ route('wla.participants.return', [$wla, $participant]) }}" data-confirm-submit data-confirm-title="Kembalikan pengisian ini?" data-confirm-text="Staff dapat memperbaiki aktivitas berdasarkan catatan review." data-confirm-button-text="Ya, kembalikan" data-confirm-button-color="#d97706">
                    @csrf
                    <label class="text-sm font-semibold text-slate-700">Catatan review<textarea name="review_note" required maxlength="2000" rows="3" class="mt-1 block w-full rounded-lg border-slate-300 text-sm"></textarea></label>
                    <button class="mt-3 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white">Kembalikan</button>
                </form>
                <div class="flex items-end justify-end">
                    <form method="POST" action="{{ route('wla.participants.approve', [$wla, $participant]) }}" data-confirm-submit data-confirm-title="Setujui pengisian WLA?" data-confirm-text="Pengisian akan dikunci dan tersedia sebagai bahan penyusunan WLA resmi." data-confirm-button-text="Ya, setujui" data-confirm-button-color="#059669">@csrf<button @disabled($participant->activities->isEmpty()) class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Setujui</button></form>
                </div>
            </section>
        @endcan

        @can('importApproved', $participant)
            <div class="flex justify-end">
                <form method="POST" action="{{ route('wla.participants.import-approved', [$wla, $participant]) }}" data-confirm-submit data-confirm-title="Gunakan aktivitas yang disetujui?" data-confirm-text="Aktivitas akan ditambahkan ke daftar Aktivitas WLA dan memengaruhi kalkulasi." data-confirm-button-text="Ya, gunakan" data-confirm-button-color="#0284c7">@csrf<button class="rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white">Gunakan sebagai Aktivitas</button></form>
            </div>
        @endcan
    </div>
@endsection
