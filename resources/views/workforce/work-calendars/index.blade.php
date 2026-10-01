@extends('layouts.app')

@section('page-title', 'Work Calendars')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm text-slate-500">Master parameter hari dalam kalender kerja tahunan.</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-950">Work Calendars</h2>
        </div>
        <a href="{{ route('work-calendars.create') }}" class="w-fit rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Calendar</a>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-[1fr_180px_auto]">
            <input name="search" value="{{ $search }}" placeholder="Cari tahun atau catatan" class="rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            <select name="active" class="rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                <option value="">Semua status</option>
                <option value="1" @selected($active === '1')>Aktif</option>
                <option value="0" @selected($active === '0')>Nonaktif</option>
            </select>
            <button class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Tahun</th><th class="px-4 py-3">Hari</th><th class="px-4 py-3">Minggu</th><th class="px-4 py-3">Cuti Tahunan</th><th class="px-4 py-3">Libur Nasional</th><th class="px-4 py-3">Cuti Bersama</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($workCalendars as $workCalendar)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $workCalendar->year }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workCalendar->total_days }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workCalendar->total_weeks }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workCalendar->annual_leave }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workCalendar->national_holiday }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workCalendar->common_leave }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $workCalendar->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $workCalendar->active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('work-calendars.edit', $workCalendar) }}">Ubah</a><form method="POST" action="{{ route('work-calendars.destroy', $workCalendar) }}" onsubmit="return confirm('Hapus calendar ini?')">@csrf @method('DELETE')<button class="font-semibold text-red-600 hover:text-red-800">Hapus</button></form></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Belum ada Work Calendar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $workCalendars->links() }}</div>
    </section>
@endsection
