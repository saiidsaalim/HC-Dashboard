@extends('layouts.app')

@php
    $movementLabel = $movementLabel ?? 'Mutasi';
    $routePrefix = $routePrefix ?? 'mutasi';
@endphp

@section('page-title', 'Buat Batch '.$movementLabel)

@section('content')
    <x-ui.page-header :title="'Buat Batch '.$movementLabel" eyebrow="Talent movement"
        :description="'Siapkan satu atau beberapa '.strtolower($movementLabel).' pegawai dalam satu kelompok tanpa menggabungkan riwayat persetujuannya.'">
        <x-slot:actions>
            <a href="{{ route($routePrefix) }}" class="ui-button ui-button-secondary">Kembali ke {{ $movementLabel }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700" role="alert">
            <p class="font-bold">Batch {{ $movementLabel }} belum dapat disimpan.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route($routePrefix.'.batches.store') }}"
        x-data="{
            employees: @js($employeeOptions),
            directorates: @js($movementDirectorates),
            departments: @js($movementDepartments),
            units: @js($movementUnits),
            sections: @js($movementSections),
            positions: @js($movementPositions),
            levels: @js($movementLevels),
            search: '',
            defaultTmt: @js(old('default_tmt', now()->addMonth()->format('Y-m-d'))),
            selectedIds: [],
            items: @js(old('items', [])),
            init() {
                const normalizedItems = {};
                Object.values(this.items ?? {}).forEach((item) => {
                    if (item?.employee_id) normalizedItems[Number(item.employee_id)] = item;
                });
                this.items = normalizedItems;
                this.selectedIds = Object.keys(normalizedItems).map(Number);
            },
            filteredEmployees() {
                const query = this.search.trim().toLocaleLowerCase();
                if (query === '') return this.employees;
                return this.employees.filter((employee) => [
                    employee.sap, employee.personal_number, employee.name, employee.directorate,
                    employee.department, employee.unit, employee.section, employee.position,
                ].some((value) => String(value ?? '').toLocaleLowerCase().includes(query)));
            },
            selectedEmployees() {
                return this.selectedIds
                    .map((id) => this.employees.find((employee) => Number(employee.id) === Number(id)))
                    .filter(Boolean);
            },
            isSelected(employeeId) {
                return this.selectedIds.some((id) => Number(id) === Number(employeeId));
            },
            toggleEmployee(employee, checked) {
                const id = Number(employee.id);
                if (checked && !this.isSelected(id)) {
                    this.selectedIds.push(id);
                    this.items[id] = {
                        employee_id: id,
                        new_directorate_id: '', new_department_id: '', new_unit_id: '',
                        new_section_id: '', new_position_id: '', tmt: this.defaultTmt,
                        pg: '', jg_lama: '', level_baru: '', jg_baru: '',
                    };
                }
                if (!checked) {
                    this.selectedIds = this.selectedIds.filter((selectedId) => Number(selectedId) !== id);
                    delete this.items[id];
                }
            },
            removeEmployee(employee) {
                this.toggleEmployee(employee, false);
            },
            applyDefaultTmt() {
                this.selectedIds.forEach((id) => { this.items[id].tmt = this.defaultTmt; });
            },
            availableDepartments(item) {
                return this.departments.filter((department) => Number(department.directorate_id) === Number(item.new_directorate_id));
            },
            availableUnits(item) {
                return this.units.filter((unit) => Number(unit.department_id) === Number(item.new_department_id));
            },
            availableSections(item) {
                return this.sections.filter((section) => Number(section.unit_id) === Number(item.new_unit_id));
            },
            availablePositions(item) {
                return this.positions.filter((position) => Number(position.unit_id) === Number(item.new_unit_id)
                    && (position.section_id === null || Number(position.section_id) === Number(item.new_section_id)));
            },
        }" class="space-y-6">
        @csrf

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                    <x-dashboard-icon name="document" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="font-display text-lg font-bold text-slate-950">Informasi Batch</h2>
                    <p class="mt-1 text-sm text-slate-500">Informasi ini digunakan untuk mengelompokkan seluruh item {{ $movementLabel }}.</p>
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <label class="text-sm font-semibold text-slate-700">Nama Batch
                    <input name="name" value="{{ old('name') }}" required maxlength="255"
                        placeholder="Contoh: {{ $movementLabel }} Organisasi Januari 2027" class="ui-input mt-1.5 block w-full">
                </label>
                <div>
                    <label for="batch-default-tmt" class="text-sm font-semibold text-slate-700">TMT Default</label>
                    <div class="mt-1.5 flex gap-2">
                        <input id="batch-default-tmt" name="default_tmt" type="date" x-model="defaultTmt" required class="ui-input block min-w-0 flex-1">
                        <button type="button" @click="applyDefaultTmt()" class="ui-button ui-button-secondary shrink-0">Terapkan</button>
                    </div>
                </div>
                <label class="text-sm font-semibold text-slate-700 lg:col-span-2">Keterangan
                    <textarea name="description" rows="3" maxlength="2000" class="ui-input mt-1.5 block w-full"
                        placeholder="Keterangan atau alasan pengelompokan {{ strtolower($movementLabel) }} ini.">{{ old('description') }}</textarea>
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="font-display text-lg font-bold text-slate-950">1. Pilih Pegawai</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih minimal satu pegawai. Pegawai lain dapat ditambahkan setelah batch dibuat.</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-sky-50 px-3 py-1.5 text-sm font-bold text-sky-700 ring-1 ring-sky-200"
                    x-text="`${selectedIds.length} pegawai dipilih`"></span>
            </div>

            <div class="relative mt-5">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <x-dashboard-icon name="users" class="h-5 w-5" />
                </span>
                <input type="search" x-model="search" placeholder="Cari SAP, Personal Number, nama, atau organisasi..."
                    class="ui-input block w-full py-3 pl-11 pr-4">
            </div>

            <div class="mt-4 grid max-h-80 gap-2 overflow-y-auto pr-1 md:grid-cols-2 xl:grid-cols-3">
                <template x-for="employee in filteredEmployees()" :key="employee.id">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition"
                        :class="isSelected(employee.id) ? 'border-sky-300 bg-sky-50' : 'border-slate-200 bg-white hover:bg-slate-50'">
                        <input type="checkbox" class="mt-1 rounded border-slate-300 text-sky-700 focus:ring-sky-500"
                            :checked="isSelected(employee.id)" @change="toggleEmployee(employee, $event.target.checked)">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-bold text-slate-900" x-text="`${employee.sap} — ${employee.name || 'Nama belum tersedia'}`"></span>
                            <span class="mt-1 block truncate text-xs text-slate-500" x-text="employee.position || 'Position belum tersedia'"></span>
                            <span class="mt-0.5 block truncate text-xs text-slate-400" x-text="employee.department || employee.unit || 'Organisasi belum tersedia'"></span>
                        </span>
                    </label>
                </template>
            </div>
            <p x-show="filteredEmployees().length === 0" class="mt-4 text-center text-sm text-slate-500">Pegawai tidak ditemukan.</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            <div>
                <h2 class="font-display text-lg font-bold text-slate-950">2. Atur Tujuan per Pegawai</h2>
                <p class="mt-1 text-sm text-slate-500">Organisasi tujuan, TMT, PG, Level, dan JG dapat berbeda untuk setiap pegawai.</p>
            </div>

            <div x-show="selectedIds.length === 0" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                Pilih pegawai terlebih dahulu untuk mengisi tujuan {{ $movementLabel }}.
            </div>

            <div class="mt-5 space-y-4">
                <template x-for="(employee, rowIndex) in selectedEmployees()" :key="employee.id">
                    <article class="overflow-hidden rounded-2xl border border-slate-200">
                        <header class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wider text-sky-700" x-text="`Pegawai ${rowIndex + 1}`"></p>
                                <p class="mt-1 truncate font-display text-base font-bold text-slate-950" x-text="`${employee.sap} — ${employee.name || 'Nama belum tersedia'}`"></p>
                                <p class="mt-1 text-xs text-slate-500" x-text="`${employee.position || 'Position belum tersedia'} · ${employee.level || 'Level belum tersedia'}`"></p>
                            </div>
                            <button type="button" @click="removeEmployee(employee)" class="text-sm font-semibold text-rose-600 hover:text-rose-700">Hapus dari batch</button>
                        </header>

                        <div class="grid gap-5 p-4 xl:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Organisasi Saat Ini</p>
                                <dl class="mt-3 space-y-2 text-sm">
                                    <div><dt class="text-xs text-slate-400">Directorate</dt><dd class="font-semibold text-slate-700" x-text="employee.directorate || '—'"></dd></div>
                                    <div><dt class="text-xs text-slate-400">Department</dt><dd class="font-semibold text-slate-700" x-text="employee.department || '—'"></dd></div>
                                    <div><dt class="text-xs text-slate-400">Unit / Section</dt><dd class="font-semibold text-slate-700" x-text="[employee.unit, employee.section].filter(Boolean).join(' · ') || '—'"></dd></div>
                                    <div><dt class="text-xs text-slate-400">Position</dt><dd class="font-semibold text-slate-700" x-text="employee.position || '—'"></dd></div>
                                </dl>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <input type="hidden" :name="`items[${rowIndex}][employee_id]`" :value="employee.id">
                                <label class="text-xs font-bold text-slate-700">Directorate Tujuan
                                    <select :name="`items[${rowIndex}][new_directorate_id]`" x-model="items[employee.id].new_directorate_id"
                                        @change="items[employee.id].new_department_id = ''; items[employee.id].new_unit_id = ''; items[employee.id].new_section_id = ''; items[employee.id].new_position_id = ''" required class="ui-input mt-1 block w-full text-sm">
                                        <option value="">Pilih Directorate</option>
                                        <template x-for="item in directorates" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                </label>
                                <label class="text-xs font-bold text-slate-700">Department Tujuan
                                    <select :name="`items[${rowIndex}][new_department_id]`" x-model="items[employee.id].new_department_id"
                                        @change="items[employee.id].new_unit_id = ''; items[employee.id].new_section_id = ''; items[employee.id].new_position_id = ''"
                                        :disabled="!items[employee.id].new_directorate_id" required class="ui-input mt-1 block w-full text-sm disabled:bg-slate-100">
                                        <option value="">Pilih Department</option>
                                        <template x-for="item in availableDepartments(items[employee.id])" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                </label>
                                <label class="text-xs font-bold text-slate-700">Unit Tujuan
                                    <select :name="`items[${rowIndex}][new_unit_id]`" x-model="items[employee.id].new_unit_id"
                                        @change="items[employee.id].new_section_id = ''; items[employee.id].new_position_id = ''"
                                        :disabled="!items[employee.id].new_department_id" required class="ui-input mt-1 block w-full text-sm disabled:bg-slate-100">
                                        <option value="">Pilih Unit</option>
                                        <template x-for="item in availableUnits(items[employee.id])" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                </label>
                                <label class="text-xs font-bold text-slate-700">Section Tujuan <span class="font-normal text-slate-400">(opsional)</span>
                                    <select :name="`items[${rowIndex}][new_section_id]`" x-model="items[employee.id].new_section_id"
                                        @change="items[employee.id].new_position_id = ''" :disabled="!items[employee.id].new_unit_id"
                                        class="ui-input mt-1 block w-full text-sm disabled:bg-slate-100">
                                        <option value="">Tanpa Section</option>
                                        <template x-for="item in availableSections(items[employee.id])" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                </label>
                                <label class="text-xs font-bold text-slate-700 sm:col-span-2">Position Tujuan
                                    <select :name="`items[${rowIndex}][new_position_id]`" x-model="items[employee.id].new_position_id"
                                        :disabled="!items[employee.id].new_unit_id" required class="ui-input mt-1 block w-full text-sm disabled:bg-slate-100">
                                        <option value="">Pilih Position</option>
                                        <template x-for="item in availablePositions(items[employee.id])" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                </label>
                                <label class="text-xs font-bold text-slate-700">TMT
                                    <input type="date" :name="`items[${rowIndex}][tmt]`" x-model="items[employee.id].tmt" required class="ui-input mt-1 block w-full text-sm">
                                </label>
                                <label class="text-xs font-bold text-slate-700">PG
                                    <input :name="`items[${rowIndex}][pg]`" x-model="items[employee.id].pg" required class="ui-input mt-1 block w-full text-sm">
                                </label>
                                <label class="text-xs font-bold text-slate-700">JG Lama
                                    <input :name="`items[${rowIndex}][jg_lama]`" x-model="items[employee.id].jg_lama" required class="ui-input mt-1 block w-full text-sm">
                                </label>
                                <label class="text-xs font-bold text-slate-700">Level Baru
                                    <select :name="`items[${rowIndex}][level_baru]`" x-model="items[employee.id].level_baru" required class="ui-input mt-1 block w-full text-sm">
                                        <option value="">Pilih Level Baru</option>
                                        <template x-for="level in levels" :key="level"><option :value="level" x-text="level"></option></template>
                                    </select>
                                </label>
                                <label class="text-xs font-bold text-slate-700 sm:col-span-2">JG Baru
                                    <input :name="`items[${rowIndex}][jg_baru]`" x-model="items[employee.id].jg_baru" required class="ui-input mt-1 block w-full text-sm">
                                </label>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
        </section>

        <div class="sticky bottom-3 z-20 flex flex-col-reverse gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl backdrop-blur sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-500">Setelah disimpan, seluruh item berstatus Draft dan dapat diperiksa sebelum diajukan.</p>
            <div class="flex flex-col-reverse gap-3 sm:flex-row">
                <a href="{{ route($routePrefix) }}" class="ui-button ui-button-secondary text-center">Batal</a>
                <button type="submit" :disabled="selectedIds.length < 1" class="ui-button ui-button-primary disabled:cursor-not-allowed disabled:opacity-50">
                    Simpan Batch {{ $movementLabel }}
                </button>
            </div>
        </div>
    </form>
@endsection
