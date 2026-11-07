@extends('layouts.app')

@section('page-title', 'Position')

@section('content')
    <x-ui.page-header title="Position" eyebrow="Master organisasi"
        description="Kelola Position dalam Unit, dengan Section bila tersedia."
        :back-href="route(session('active_workspace', 'hc-od-career-management') === 'hc-od-career-management' && auth()->user()->hasPermission('formation.view') ? 'formasi.master' : 'organization.index')" back-label="Master Organisasi">
        <x-slot:actions>
            @can('create', \App\Models\Position::class)
                <a href="{{ route('organization.positions.create') }}" class="ui-button ui-button-primary">Tambah Position</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>
    @if (session('status'))<div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-4">
            <x-ui.filter-panel id="position-filters" title="Filter Position"
                :reset-href="route('organization.positions.index', ['search' => $search, 'per_page' => $perPage])"
                :active="$departmentId !== '' || $unitId !== '' || $sectionId !== '' || $active !== ''">
                <x-slot:actions>
                    @include('organization._table-toolbar', ['searchFieldId' => 'position-search', 'perPageFieldId' => 'position-per-page'])
                </x-slot:actions>
                <input type="hidden" name="search" value="{{ $search }}">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <div x-data="{
                    departmentId: @js($departmentId), unitId: @js($unitId), sectionId: @js($sectionId),
                    units: @js($units->map(fn ($unit) => ['id' => $unit->id, 'department_id' => $unit->department_id, 'label' => $unit->name.' · '.$unit->department?->name])->values()),
                    sections: @js($sections->map(fn ($section) => ['id' => $section->id, 'unit_id' => $section->unit_id, 'department_id' => $section->unit?->department_id, 'label' => $section->name.' · '.$section->unit?->name])->values()),
                }" class="space-y-4">
                    <label class="block text-sm font-semibold text-slate-700">Department
                        <select name="department_id" x-model="departmentId" @change="unitId = ''; sectionId = ''"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Semua Department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($departmentId == $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Unit
                        <select name="unit_id" x-model="unitId" @change="sectionId = ''"
                            class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Semua Unit</option>
                            <template x-for="unit in units.filter(item => !departmentId || String(item.department_id) === departmentId)" :key="unit.id">
                                <option :value="String(unit.id)" x-text="unit.label"></option>
                            </template>
                        </select>
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">Section
                        <select name="section_id" x-model="sectionId" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Semua Section</option>
                            <template x-for="section in sections.filter(item => (!unitId || String(item.unit_id) === unitId) &amp;&amp; (!departmentId || String(item.department_id) === departmentId))" :key="section.id">
                                <option :value="String(section.id)" x-text="section.label"></option>
                            </template>
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
        <div class="overflow-x-auto"><table class="w-full min-w-[1080px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">SAP Position ID</th><th class="px-4 py-3">Level</th><th class="px-4 py-3">Formasi</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Unit</th><th class="px-4 py-3">Section</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Aksi</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($positions as $position)
                    <tr>
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ $position->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $position->external_position_id ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $position->job_level ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $position->formation_is_confirmed ? $position->formation_count : 'Belum ditetapkan' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $position->unit->department->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $position->unit->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $position->section?->name ?? 'Data lama' }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :tone="$position->active ? 'success' : 'neutral'">{{ $position->active ? 'Aktif' : 'Nonaktif' }}</x-ui.status-badge></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-3">
                                @can('update', $position)
                                    <a class="font-semibold text-sky-700 hover:text-sky-900" href="{{ route('organization.positions.edit', $position) }}">Ubah</a>
                                @endcan
                                @can('delete', $position)
                                    <form method="POST" action="{{ route('organization.positions.destroy', $position) }}" data-confirm-delete data-confirm-submit data-confirm-title="Hapus Position ini?" data-confirm-text="Data Position yang dihapus tidak dapat dikembalikan.">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-semibold text-red-600 hover:text-red-800">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><x-ui.empty-state colspan="9" title="Belum ada Position" description="Sesuaikan filter atau tambahkan Position baru." /></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $positions->links() }}</div>
    </section>
@endsection
