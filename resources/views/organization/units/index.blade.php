@extends('layouts.app')

@section('page-title', 'Unit')

@section('content')
    <x-ui.page-header title="Unit" eyebrow="Master organisasi"
        description="Kelola Unit di bawah Department, sebelum level Section."
        :back-href="route('organization.index')" back-label="Master Organization">
        <x-slot:actions>
            <a href="{{ route('organization.departments.index') }}" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">Daftar Department</a>
            <a href="{{ route('organization.positions.index') }}" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">Daftar Position</a>
            <a href="{{ route('organization.sections.index') }}" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">Daftar Section</a>
            <a href="{{ route('organization.units.create') }}" class="ui-button ui-button-primary">Tambah Unit</a>
        </x-slot:actions>
    </x-ui.page-header>
    @if (session('status'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4">
            <x-ui.filter-panel id="unit-filters" title="Filter Unit"
                :reset-href="route('organization.units.index')"
                :active="$search !== '' || $departmentId !== '' || $active !== ''">
                <label class="block text-sm font-semibold text-slate-700">Pencarian
                    <input name="search" value="{{ $search }}" placeholder="Cari nama"
                        class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                </label>
                <label class="block text-sm font-semibold text-slate-700">Department
                    <select name="department_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                        <option value="">Semua Department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($departmentId == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
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
        <div class="overflow-x-auto"><table class="w-full min-w-[640px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Section</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse ($units as $unit)<tr><td class="px-4 py-3 font-semibold text-slate-800">{{ $unit->name }}</td><td class="px-4 py-3 text-slate-600">{{ $unit->department->name }}</td><td class="px-4 py-3 text-slate-600">{{ $unit->sections_count }}</td><td class="px-4 py-3"><x-ui.status-badge :tone="$unit->active ? 'success' : 'neutral'">{{ $unit->active ? 'Aktif' : 'Nonaktif' }}</x-ui.status-badge></td><td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('organization.units.edit', $unit) }}">Ubah</a><form method="POST" action="{{ route('organization.units.destroy', $unit) }}" data-confirm-delete data-confirm-submit data-confirm-title="Hapus Unit ini?" data-confirm-text="Unit hanya dapat dihapus jika tidak memiliki Position. Data yang dihapus tidak dapat dikembalikan.">@csrf @method('DELETE')<button class="font-semibold text-red-600 hover:text-red-800">Hapus</button></form></div></td></tr>@empty<tr><x-ui.empty-state colspan="5" title="Belum ada Unit" description="Sesuaikan filter atau tambahkan Unit baru." /></tr>@endforelse</tbody>
        </table></div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $units->links() }}</div>
    </section>
@endsection
