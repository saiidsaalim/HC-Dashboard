@extends('layouts.app')

@section('page-title', 'Work Schedules')

@section('content')
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm text-slate-500">Master pola jam dan hari kerja.</p>
            <h2 class="mt-1 font-display text-2xl font-bold text-slate-950">Work Schedules</h2>
        </div>
        <a href="{{ route('work-schedules.create') }}" class="w-fit rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Tambah Schedule</a>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="GET" class="grid gap-3 border-b border-slate-200 p-4 sm:grid-cols-[1fr_180px_auto]">
            <input name="search" value="{{ $search }}" placeholder="Cari kode, nama, atau tipe" class="rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
            <select name="active" class="rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                <option value="">Semua status</option>
                <option value="1" @selected($active === '1')>Aktif</option>
                <option value="0" @selected($active === '0')>Nonaktif</option>
            </select>
            <button class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Tipe</th><th class="px-4 py-3">Jam / hari</th><th class="px-4 py-3">Hari / minggu</th><th class="px-4 py-3">Jam / minggu</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($workSchedules as $workSchedule)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ $workSchedule->code }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $workSchedule->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workSchedule->schedule_type }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workSchedule->working_hours_per_day }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $workSchedule->working_days_per_week }}</td>
                            <td class="px-4 py-3 font-medium text-slate-700">{{ $workSchedule->working_hours_per_week }}</td>
                            <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $workSchedule->active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $workSchedule->active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('work-schedules.edit', $workSchedule) }}">Ubah</a><form method="POST" action="{{ route('work-schedules.destroy', $workSchedule) }}" onsubmit="return confirm('Hapus schedule ini?')">@csrf @method('DELETE')<button class="font-semibold text-red-600 hover:text-red-800">Hapus</button></form></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Belum ada Work Schedule.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $workSchedules->links() }}</div>
    </section>
@endsection
