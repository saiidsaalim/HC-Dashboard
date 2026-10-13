@extends('layouts.app')

@section('page-title', 'WLA Saya')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <p class="text-sm text-slate-500">Daftar tugas pengisian aktivitas beban kerja Anda.</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-950">WLA Saya</h2>
        </div>

        @if ($employee === null)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                Akun Anda belum terhubung ke data Employee. Hubungi Super Admin untuk melakukan pengaitan akun.
            </div>
        @else
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <div><dt class="text-xs uppercase text-slate-500">Department</dt><dd class="mt-1 font-semibold text-slate-800">{{ $employee->department?->name ?? 'Belum tersedia' }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">Unit</dt><dd class="mt-1 font-semibold text-slate-800">{{ $employee->unit?->name ?? 'Belum tersedia' }}</dd></div>
                    <div><dt class="text-xs uppercase text-slate-500">Position</dt><dd class="mt-1 font-semibold text-slate-800">{{ $employee->position?->name ?? 'Belum tersedia' }}</dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Position</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Kelengkapan</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($participants as $participant)
                                @php($progress = $participant->activities_count > 0 ? 100 : 0)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $participant->assessment->assessment_code }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $participant->assessment->period }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $participant->assessment->position->name }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $participant->status->label() }}</span></td>
                                    <td class="px-4 py-3">
                                        <div class="h-2 w-28 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-emerald-500" style="width: {{ $progress }}%"></div></div>
                                        <span class="mt-1 block text-xs text-slate-500">{{ $progress }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-right"><a href="{{ route('wla.staff.show', $participant) }}" class="font-semibold text-sky-700 hover:text-sky-900">Buka</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada tugas pengisian WLA untuk Anda.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
            {{ $participants->links() }}
        @endif
    </div>
@endsection
