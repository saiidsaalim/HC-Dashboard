@extends('layouts.app')

@section('page-title', 'Work Calendars')

@section('content')
    <x-ui.page-header title="Work Calendars" eyebrow="Parameter WLA"
        description="Kelola parameter hari dalam kalender kerja tahunan.">
        <x-slot:actions>
            <a href="{{ route('work-calendars.create') }}" class="ui-button ui-button-primary">Tambah Calendar</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session('status'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4">
            <x-ui.filter-panel id="work-calendar-filters" title="Filter Work Calendar"
                :reset-href="route('work-calendars.index')" :active="$search !== '' || $active !== ''">
                <label class="block text-sm font-semibold text-slate-700">Pencarian
                    <input name="search" value="{{ $search }}" placeholder="Cari tahun atau catatan"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                </label>
                <label class="block text-sm font-semibold text-slate-700">Status
                    <select name="active" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua status</option>
                        <option value="1" @selected($active === '1')>Aktif</option>
                        <option value="0" @selected($active === '0')>Nonaktif</option>
                    </select>
                </label>
            </x-ui.filter-panel>
        </div>
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
                            <td class="px-4 py-3"><x-ui.status-badge :tone="$workCalendar->active ? 'success' : 'neutral'">{{ $workCalendar->active ? 'Aktif' : 'Nonaktif' }}</x-ui.status-badge></td>
                            <td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('work-calendars.edit', $workCalendar) }}">Ubah</a><form method="POST" action="{{ route('work-calendars.destroy', $workCalendar) }}" data-confirm-delete data-confirm-submit data-confirm-title="Hapus Work Calendar ini?" data-confirm-text="Work Calendar hanya dapat dihapus jika tidak sedang digunakan. Data yang dihapus tidak dapat dikembalikan.">@csrf @method('DELETE')<button class="font-semibold text-red-600 hover:text-red-800">Hapus</button></form></div></td>
                        </tr>
                    @empty
                        <tr><x-ui.empty-state colspan="8" title="Belum ada Work Calendar" description="Tambahkan kalender untuk menyiapkan parameter perhitungan WLA." /></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $workCalendars->links() }}</div>
    </section>
@endsection
