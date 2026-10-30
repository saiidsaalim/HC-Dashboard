@extends('layouts.app')

@section('page-title', 'Position')

@section('content')
    <x-ui.page-header title="Position" eyebrow="Master organisasi"
        description="Kelola Position yang berada di bawah Section."
        :back-href="route('organization.index')" back-label="Master Organization">
        <x-slot:actions>
            <a href="{{ route('organization.positions.create') }}" class="ui-button ui-button-primary">Tambah Position</a>
        </x-slot:actions>
    </x-ui.page-header>
    @if (session('status'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4">
            <x-ui.filter-panel id="position-filters" title="Filter Position"
                :reset-href="route('organization.positions.index')"
                :active="$search !== '' || $departmentId !== '' || $unitId !== '' || $sectionId !== '' || $active !== ''">
                <div x-data="{ unitId: @js($unitId) }" class="space-y-4">
                    <label class="block text-sm font-semibold text-slate-700">Pencarian
                        <input name="search" value="{{ $search }}" placeholder="Cari nama"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Department
                        <select name="department_id" @change="unitId = ''; $el.form.requestSubmit()"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Semua Department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($departmentId == $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Unit
                        <select name="unit_id" x-model="unitId"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Semua Unit</option>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Section
                        <select name="section_id" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Semua Section</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}" @selected($sectionId == $section->id)>{{ $section->name }}</option>
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
                </div>
            </x-ui.filter-panel>
        </div>
        <div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Level</th><th class="px-4 py-3">Formasi</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Section</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse ($positions as $position)<tr><td class="px-4 py-3 font-semibold text-slate-800">{{ $position->name }}</td><td class="px-4 py-3 text-slate-600">{{ $position->job_level ?? '-' }}</td><td class="px-4 py-3 text-slate-600">{{ $position->formation_count }}</td><td class="px-4 py-3 text-slate-600">{{ $position->unit->department->name }}</td><td class="px-4 py-3 text-slate-600">{{ $position->unit->name }}</td><td class="px-4 py-3 text-slate-600">{{ $position->section?->name ?? 'Data lama' }}</td><td class="px-4 py-3"><x-ui.status-badge :tone="$position->active ? 'success' : 'neutral'">{{ $position->active ? 'Aktif' : 'Nonaktif' }}</x-ui.status-badge></td><td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('organization.positions.edit', $position) }}">Ubah</a><form method="POST" action="{{ route('organization.positions.destroy', $position) }}" data-confirm-delete data-confirm-submit data-confirm-title="Hapus Position ini?" data-confirm-text="Data Position yang dihapus tidak dapat dikembalikan.">@csrf @method('DELETE')<button class="font-semibold text-red-600 hover:text-red-800">Hapus</button></form></div></td></tr>@empty<tr><x-ui.empty-state colspan="8" title="Belum ada Position" description="Sesuaikan filter atau tambahkan Position baru." /></tr>@endforelse</tbody>
        </table></div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $positions->links() }}</div>
    </section>
@endsection
