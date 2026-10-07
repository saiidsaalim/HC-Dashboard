@extends('layouts.app')

@section('page-title', 'Data Pegawai')

@section('content')
    <div x-data="{
        open: false,
        organizationUnits: @js($organizationUnits->map(fn ($unit) => ['id' => $unit->id, 'department_id' => $unit->department_id, 'code' => $unit->code, 'name' => $unit->name])->values()),
        organizationPositions: @js($organizationPositions->map(fn ($position) => ['id' => $position->id, 'unit_id' => $position->unit_id, 'department_id' => $position->unit->department_id, 'code' => $position->code, 'name' => $position->name])->values()),
        async chooseExport(pdfUrl, excelUrl) {
            const result = await Swal.fire({
                title: 'Pilih format laporan',
                text: 'Seluruh data detail pegawai akan disertakan.',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: 'PDF',
                denyButtonText: 'Excel',
                cancelButtonText: 'Batal',
            });

            if (result.isConfirmed) {
                window.location.href = pdfUrl;
            } else if (result.isDenied) {
                window.location.href = excelUrl;
            }
        },
    }">
        <div class="mb-8 flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
                <p class="text-sm text-slate-500">Kelola data utama dan informasi pegawai.</p>
                <h2 class="mt-1 font-display text-3xl font-bold tracking-tight text-slate-950">Data Pegawai</h2>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <button type="button"
                    data-pdf-url="{{ route('data-pegawai.print', ['autoprint' => 1]) }}"
                    data-excel-url="{{ route('data-pegawai.export') }}"
                    @click="chooseExport($el.dataset.pdfUrl, $el.dataset.excelUrl)"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z" />
                    </svg>
                    Ekspor Semua Data
                </button>
                @if ($canManageEmployees)
                    <a href="{{ route('data-pegawai.import.review.index') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        Riwayat import
                    </a>
                    <form method="POST" action="{{ route('data-pegawai.import') }}" enctype="multipart/form-data"
                        class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        @csrf
                        <input type="file" name="file" accept=".xlsx,.csv,.txt" required
                            class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-500 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700 sm:w-auto">
                        <button type="submit"
                            class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">
                            Import SAP
                        </button>
                    </form>
                    <button type="button" @click="open = true"
                        class="rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Tambah Pegawai
                    </button>
                @endif
            </div>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
                        @if (session('import_batch_id') && $canManageEmployees)
                            <a href="{{ route('data-pegawai.import.review.show', session('import_batch_id')) }}"
                                class="ml-2 font-semibold underline underline-offset-2">Buka review batch</a>
                        @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Data pegawai tidak dapat diproses.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($canManageEmployees)
            <div x-cloak x-show="open" x-transition.opacity @keydown.escape.window="open = false"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/60 p-4 sm:p-8"
                @click.self="open = false">
                <section class="my-4 w-full max-w-6xl rounded-2xl bg-white p-6 shadow-2xl sm:my-8 sm:p-8" role="dialog"
                    aria-modal="true" aria-labelledby="employee-modal-title">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 id="employee-modal-title" class="font-display text-xl font-bold text-slate-950">Tambah Data Pegawai</h3>
                        <p class="mt-1 text-sm text-slate-500">Isi informasi pegawai sesuai data SAP dan struktur organisasi.</p>
                    </div>
                    <button type="button" @click="open = false" aria-label="Tutup"
                        class="rounded-lg px-3 py-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">&times;</button>
                </div>

                <form method="POST" action="{{ route('data-pegawai.store') }}" x-data="{ departmentId: @js(old('department_id', '')), unitId: @js(old('unit_id', '')), positionId: @js(old('position_id', '')) }" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @csrf
                    <div>
                        <label for="sap" class="text-sm font-semibold text-slate-700">SAP</label>
                        <input id="sap" name="sap" value="{{ old('sap') }}" required
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="id_number" class="text-sm font-semibold text-slate-700">ID Number</label>
                        <input id="id_number" name="id_number" value="{{ old('id_number') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="agkn" class="text-sm font-semibold text-slate-700">AGKN</label>
                        <input id="agkn" name="agkn" value="{{ old('agkn') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="personal_number" class="text-sm font-semibold text-slate-700">Personal Number</label>
                        <input id="personal_number" name="personal_number" value="{{ old('personal_number') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="position_id_source" class="text-sm font-semibold text-slate-700">Position ID Source</label>
                        <input id="position_id_source" name="position_id_source" value="{{ old('position_id_source') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="create-department-id" class="text-sm font-semibold text-slate-700">Department</label>
                        <select id="create-department-id" name="department_id" x-model="departmentId" @change="unitId = ''; positionId = ''"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Pilih Department</option>
                            @foreach ($organizationDepartments as $departmentOption)
                                <option value="{{ $departmentOption->id }}">{{ $departmentOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="create-unit-id" class="text-sm font-semibold text-slate-700">Unit</label>
                        <select id="create-unit-id" name="unit_id" x-model="unitId" @change="positionId = ''"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Pilih Unit</option>
                            <template x-for="unit in organizationUnits.filter(item => !departmentId || String(item.department_id) === String(departmentId))" :key="unit.id">
                                <option :value="String(unit.id)" x-text="unit.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label for="create-position-id" class="text-sm font-semibold text-slate-700">Position</label>
                        <select id="create-position-id" name="position_id" x-model="positionId"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                            <option value="">Pilih Position</option>
                            <template x-for="position in organizationPositions.filter(item => (!departmentId || String(item.department_id) === String(departmentId)) && (!unitId || String(item.unit_id) === String(unitId))" :key="position.id">
                                <option :value="String(position.id)" x-text="position.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label for="organizational_unit" class="text-sm font-semibold text-slate-700">Organizational Unit</label>
                        <input id="organizational_unit" name="organizational_unit" value="{{ old('organizational_unit') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="position" class="text-sm font-semibold text-slate-700">Position</label>
                        <input id="position" name="position" value="{{ old('position') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="txt_dir" class="text-sm font-semibold text-slate-700">TXT_DIR</label>
                        <input id="txt_dir" name="txt_dir" value="{{ old('txt_dir') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="txt_dept" class="text-sm font-semibold text-slate-700">TXT_DEPT</label>
                        <input id="txt_dept" name="txt_dept" value="{{ old('txt_dept') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="txt_biro" class="text-sm font-semibold text-slate-700">TXT_BIRO</label>
                        <input id="txt_biro" name="txt_biro" value="{{ old('txt_biro') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="txt_sect" class="text-sm font-semibold text-slate-700">TXT_SECT</label>
                        <input id="txt_sect" name="txt_sect" value="{{ old('txt_sect') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="employee_subgroup" class="text-sm font-semibold text-slate-700">Employee Subgroup</label>
                        <input id="employee_subgroup" name="employee_subgroup" value="{{ old('employee_subgroup') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="cost_ctr" class="text-sm font-semibold text-slate-700">Cost Ctr</label>
                        <input id="cost_ctr" name="cost_ctr" value="{{ old('cost_ctr') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="cost_center" class="text-sm font-semibold text-slate-700">Cost Center</label>
                        <input id="cost_center" name="cost_center" value="{{ old('cost_center') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="masa_kontrak" class="text-sm font-semibold text-slate-700">Masa Kontrak</label>
                        <input id="masa_kontrak" name="masa_kontrak" type="date" value="{{ old('masa_kontrak') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="abrevation_position" class="text-sm font-semibold text-slate-700">abrevation position</label>
                        <input id="abrevation_position" name="abrevation_position" value="{{ old('abrevation_position') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="abrevation_organization" class="text-sm font-semibold text-slate-700">abrevation organization</label>
                        <input id="abrevation_organization" name="abrevation_organization" value="{{ old('abrevation_organization') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="personnel_area" class="text-sm font-semibold text-slate-700">Personnel Area</label>
                        <input id="personnel_area" name="personnel_area" value="{{ old('personnel_area') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="birth_date" class="text-sm font-semibold text-slate-700">Birth Date</label>
                        <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="tempat_lahir" class="text-sm font-semibold text-slate-700">Tempat Lahir</label>
                        <input id="tempat_lahir" name="tempat_lahir" value="{{ old('tempat_lahir') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="gender_key" class="text-sm font-semibold text-slate-700">Gender Key</label>
                        <input id="gender_key" name="gender_key" value="{{ old('gender_key') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="religious" class="text-sm font-semibold text-slate-700">Religious</label>
                        <input id="religious" name="religious" value="{{ old('religious') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="pendidikan" class="text-sm font-semibold text-slate-700">Pendidikan</label>
                        <input id="pendidikan" name="pendidikan" value="{{ old('pendidikan') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="usia" class="text-sm font-semibold text-slate-700">Usia</label>
                        <input id="usia" name="usia" type="number" min="0" value="{{ old('usia') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="hiring" class="text-sm font-semibold text-slate-700">Hiring</label>
                        <input id="hiring" name="hiring" type="date" value="{{ old('hiring') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="organilk" class="text-sm font-semibold text-slate-700">Organilk</label>
                        <input id="organilk" name="organilk" type="date" value="{{ old('organilk') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="email" class="text-sm font-semibold text-slate-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div class="sm:col-span-2 xl:col-span-4">
                        <label for="alamat" class="text-sm font-semibold text-slate-700">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">{{ old('alamat') }}</textarea>
                    </div>
                    <div class="flex justify-end sm:col-span-2 xl:col-span-4">
                        <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 sm:w-auto">
                            Simpan Data Pegawai
                        </button>
                    </div>
                </form>
                </section>
            </div>
        @endif
    </div>

    <div class="mt-8 space-y-6">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex w-full flex-col items-stretch justify-between gap-4 border-b border-slate-200 p-4 sm:p-6 xl:flex-row xl:items-center">
                <div>
                    <h3 class="font-display text-lg font-bold text-slate-950">Daftar pegawai</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $employees->total() }} data pegawai</p>
                </div>
                <div class="flex flex-col gap-3 xl:items-end">
                    <form method="GET" action="{{ route('data-pegawai') }}" x-data="{ departmentId: @js($departmentId), unitId: @js($unitId), positionId: @js($positionId) }" class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
                        <div class="col-span-2 sm:col-span-1">
                            <label for="employee-search" class="sr-only">Cari pegawai</label>
                            <input id="employee-search" name="search" value="{{ $search }}" type="search"
                                placeholder="Cari SAP, Personal Number, TXT_DEPT, Position, E-mail..."
                                class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 sm:w-56">
                        </div>
                        <div>
                            <label for="employee-department-filter" class="sr-only">Filter TXT_DEPT</label>
                            <select id="employee-department-filter" name="department_id" x-model="departmentId" @change="unitId = ''; positionId = ''"
                                class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 sm:w-40">
                                <option value="">Semua TXT_DEPT</option>
                                @foreach ($organizationDepartments as $departmentOption)
                                    <option value="{{ $departmentOption->id }}">{{ $departmentOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="employee-unit-filter" class="sr-only">Filter unit</label>
                            <select id="employee-unit-filter" name="unit_id" x-model="unitId" @change="positionId = ''"
                                class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 sm:w-40">
                                <option value="">Semua unit</option>
                                <template x-for="unit in organizationUnits.filter(item => !departmentId || String(item.department_id) === String(departmentId))" :key="unit.id">
                                    <option :value="String(unit.id)" x-text="unit.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label for="employee-position-filter" class="sr-only">Filter position</label>
                            <select id="employee-position-filter" name="position_id" x-model="positionId"
                                class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 sm:w-40">
                                <option value="">Semua position</option>
                                <template x-for="position in organizationPositions.filter(item => (!departmentId || String(item.department_id) === String(departmentId)) && (!unitId || String(item.unit_id) === String(unitId))" :key="position.id">
                                    <option :value="String(position.id)" x-text="position.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label for="employee-sort" class="sr-only">Urutkan berdasarkan</label>
                            <select id="employee-sort" name="sort_by"
                                class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 sm:w-40">
                                <option value="latest" @selected($sortBy === 'latest')>Terbaru ditambahkan</option>
                                <option value="organic_newest" @selected($sortBy === 'organic_newest')>Organik terbaru</option>
                                <option value="organic_oldest" @selected($sortBy === 'organic_oldest')>Organik terlama</option>
                            </select>
                        </div>
                        <div class="col-span-2 flex gap-2 sm:col-span-1">
                            <button type="submit" class="rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700">Cari</button>
                            @if ($search !== '' || $department !== '' || $position !== '' || $departmentId !== '' || $unitId !== '' || $positionId !== '' || $sortBy !== 'latest')
                                <a href="{{ route('data-pegawai') }}" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50">Reset</a>
                            @endif
                        </div>
                    </form>
                    @if ($canManageEmployees)
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-slate-600">
                                <input id="select-all-employees" type="checkbox"
                                    onchange="document.querySelectorAll('.employee-checkbox').forEach((checkbox) => checkbox.checked = this.checked)"
                                    class="rounded border-slate-300 text-slate-950 focus:ring-amber-400">
                                Pilih semua di halaman ini
                            </label>
                            <button type="submit" form="bulk-delete-employees"
                                class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">Hapus terpilih</button>
                            <button type="submit" form="delete-all-employees"
                                class="rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-50">Hapus Semua Data</button>
                        </div>
                        <form id="bulk-delete-employees" method="POST" action="{{ route('data-pegawai.destroy-many') }}"
                            onsubmit="event.preventDefault(); const form = this; const selected = document.querySelectorAll('.employee-checkbox:checked'); if (!selected.length) { Swal.fire({ icon: 'info', title: 'Pilih data terlebih dahulu', text: 'Centang minimal satu pegawai.' }); return; } Swal.fire({ icon: 'warning', title: 'Hapus pegawai terpilih?', text: 'Data yang dihapus tidak dapat dikembalikan.', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) form.submit(); });">
                        @csrf
                        @method('DELETE')
                        </form>
                        <form id="delete-all-employees" method="POST" action="{{ route('data-pegawai.destroy-all') }}"
                            onsubmit="event.preventDefault(); const form = this; Swal.fire({ icon: 'warning', title: 'Hapus semua data pegawai?', text: 'Seluruh data pegawai akan dihapus permanen dan tidak dapat dipulihkan.', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus semua', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) form.submit(); });">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endif
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="px-4 py-3">SAP</th>
                            <th class="min-w-[150px] px-4 py-3">Personal Number</th>
                            <th class="min-w-[150px] px-4 py-3">Department</th>
                            <th class="min-w-[150px] px-4 py-3">Position</th>
                            <th class="px-4 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($employees as $employee)
                            <tr class="transition-colors hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-700">{{ $employee->sap }}</td>
                                <td class="px-4 py-3 font-medium text-slate-700">{{ $employee->personal_number ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $employee->txt_dept ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $employee->position ?: '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        @if ($canManageEmployees)
                                            <input type="checkbox" form="bulk-delete-employees" name="employee_ids[]" value="{{ $employee->id }}"
                                                aria-label="Pilih {{ $employee->sap }}"
                                                class="employee-checkbox rounded border-slate-300 text-slate-950 focus:ring-amber-400"
                                                onchange="document.getElementById('select-all-employees').checked = document.querySelectorAll('.employee-checkbox').length > 0 && document.querySelectorAll('.employee-checkbox:not(:checked)').length === 0">
                                        @endif
                                        <a href="{{ route('employees.show', $employee->id) }}"
                                            class="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-700 transition hover:bg-blue-100 hover:text-blue-800">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                            Detail
                                        </a>
                                        @if ($canManageEmployees)
                                            <button type="button" onclick="document.getElementById('edit-employee-{{ $employee->id }}').showModal()"
                                                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 transition hover:bg-amber-100">
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('data-pegawai.destroy', $employee) }}" class="inline-block" onsubmit="event.preventDefault(); const form = this; Swal.fire({ title: 'Hapus pegawai ini?', text: 'Data yang dihapus tidak dapat dikembalikan.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal' }).then((result) => { if (result.isConfirmed) form.submit(); });">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 transition hover:bg-red-100">
                                                    Hapus
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                    Belum ada data pegawai.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($employees->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 sm:px-6">
                    {{ $employees->links() }}
                </div>
            @endif
        </div>

        @if ($canManageEmployees)
            @foreach ($employees as $employee)
            <dialog id="edit-employee-{{ $employee->id }}" class="w-[calc(100%-2rem)] max-w-6xl rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60">
                <section class="max-h-[90vh] overflow-y-auto bg-white p-6 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Edit Data</p>
                            <h3 class="mt-1 font-display text-2xl font-bold text-slate-950">SAP: {{ $employee->sap }}</h3>
                        </div>
                        <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup"
                            class="rounded-lg px-3 py-2 text-xl leading-none text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">&times;</button>
                    </div>
                    <form method="POST" action="{{ route('data-pegawai.update', $employee) }}" x-data="{ departmentId: @js((string) $employee->department_id), unitId: @js((string) $employee->unit_id), positionId: @js((string) $employee->position_id) }" class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="edit-sap-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">SAP</label>
                            <input id="edit-sap-{{ $employee->id }}" name="sap" value="{{ old('sap', $employee->sap) }}" required class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-id_number-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">ID Number</label>
                            <input id="edit-id_number-{{ $employee->id }}" name="id_number" value="{{ old('id_number', $employee->id_number) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-agkn-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">AGKN</label>
                            <input id="edit-agkn-{{ $employee->id }}" name="agkn" value="{{ old('agkn', $employee->agkn) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-personal_number-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Personal Number</label>
                            <input id="edit-personal_number-{{ $employee->id }}" name="personal_number" value="{{ old('personal_number', $employee->personal_number) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-position_id_source-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Position ID Source</label>
                            <input id="edit-position_id_source-{{ $employee->id }}" name="position_id_source" value="{{ old('position_id_source', $employee->position_id_source) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-department-id-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Department</label>
                            <select id="edit-department-id-{{ $employee->id }}" name="department_id" x-model="departmentId" @change="unitId = ''; positionId = ''" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                                <option value="">{{ $employee->department_id ? 'Pilih Department' : ($employee->department ?: 'Pilih Department') }}</option>
                                @foreach ($organizationDepartments as $departmentOption)
                                    <option value="{{ $departmentOption->id }}">{{ $departmentOption->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="edit-unit-id-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Unit</label>
                            <select id="edit-unit-id-{{ $employee->id }}" name="unit_id" x-model="unitId" @change="positionId = ''" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                                <option value="">{{ $employee->unit_id ? 'Pilih Unit' : ($employee->organizational_unit ?: 'Pilih Unit') }}</option>
                                <template x-for="unit in organizationUnits.filter(item => !departmentId || String(item.department_id) === String(departmentId))" :key="unit.id">
                                    <option :value="String(unit.id)" x-text="unit.name"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label for="edit-position-id-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Position</label>
                            <select id="edit-position-id-{{ $employee->id }}" name="position_id" x-model="positionId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                                <option value="">{{ $employee->position_id ? 'Pilih Position' : ($employee->position ?: 'Pilih Position') }}</option>
                                <template x-for="position in organizationPositions.filter(item => (!departmentId || String(item.department_id) === String(departmentId)) && (!unitId || String(item.unit_id) === String(unitId))" :key="position.id">
                                    <option :value="String(position.id)" x-text="position.name"></option>
                                </template>
                            </select>
                        </div>
                        <input type="hidden" name="department" value="{{ $employee->department }}">
                        <div>
                            <label for="edit-txt_dept-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">TXT_DEPT</label>
                            <input id="edit-txt_dept-{{ $employee->id }}" name="txt_dept" value="{{ old('txt_dept', $employee->txt_dept) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-organizational_unit-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Organizational Unit</label>
                            <input id="edit-organizational_unit-{{ $employee->id }}" name="organizational_unit" value="{{ old('organizational_unit', $employee->organizational_unit) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-position-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Position</label>
                            <input id="edit-position-{{ $employee->id }}" name="position" value="{{ old('position', $employee->position) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-employee_subgroup-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Employee Subgroup</label>
                            <input id="edit-employee_subgroup-{{ $employee->id }}" name="employee_subgroup" value="{{ old('employee_subgroup', $employee->employee_subgroup) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-cost_ctr-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Cost Ctr</label>
                            <input id="edit-cost_ctr-{{ $employee->id }}" name="cost_ctr" value="{{ old('cost_ctr', $employee->cost_ctr) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-cost_center-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Cost Center</label>
                            <input id="edit-cost_center-{{ $employee->id }}" name="cost_center" value="{{ old('cost_center', $employee->cost_center) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-masa_kontrak-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Masa Kontrak</label>
                            <input id="edit-masa_kontrak-{{ $employee->id }}" name="masa_kontrak" type="date" value="{{ old('masa_kontrak', $employee->masa_kontrak?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-txt_dir-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">TXT_DIR</label>
                            <input id="edit-txt_dir-{{ $employee->id }}" name="txt_dir" value="{{ old('txt_dir', $employee->txt_dir) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-txt_biro-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">TXT_BIRO</label>
                            <input id="edit-txt_biro-{{ $employee->id }}" name="txt_biro" value="{{ old('txt_biro', $employee->txt_biro) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-txt_sect-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">TXT_SECT</label>
                            <input id="edit-txt_sect-{{ $employee->id }}" name="txt_sect" value="{{ old('txt_sect', $employee->txt_sect) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-personnel_area-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Personnel Area</label>
                            <input id="edit-personnel_area-{{ $employee->id }}" name="personnel_area" value="{{ old('personnel_area', $employee->personnel_area) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-birth_date-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Birth Date</label>
                            <input id="edit-birth_date-{{ $employee->id }}" name="birth_date" type="date" value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-tempat_lahir-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Tempat Lahir</label>
                            <input id="edit-tempat_lahir-{{ $employee->id }}" name="tempat_lahir" value="{{ old('tempat_lahir', $employee->tempat_lahir) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-gender_key-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Gender Key</label>
                            <input id="edit-gender_key-{{ $employee->id }}" name="gender_key" value="{{ old('gender_key', $employee->gender_key) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-religious-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Religious</label>
                            <input id="edit-religious-{{ $employee->id }}" name="religious" value="{{ old('religious', $employee->religious) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-pendidikan-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Pendidikan</label>
                            <input id="edit-pendidikan-{{ $employee->id }}" name="pendidikan" value="{{ old('pendidikan', $employee->pendidikan) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-usia-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Usia</label>
                            <input id="edit-usia-{{ $employee->id }}" name="usia" type="number" min="0" value="{{ old('usia', $employee->usia) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-hiring-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Hiring</label>
                            <input id="edit-hiring-{{ $employee->id }}" name="hiring" type="date" value="{{ old('hiring', $employee->hiring?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-organilk-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Organilk</label>
                            <input id="edit-organilk-{{ $employee->id }}" name="organilk" type="date" value="{{ old('organilk', $employee->organilk?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div>
                            <label for="edit-email-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Email</label>
                            <input id="edit-email-{{ $employee->id }}" name="email" type="email" value="{{ old('email', $employee->email) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                        </div>
                        <div class="sm:col-span-2 xl:col-span-4">
                            <label for="edit-alamat-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Alamat</label>
                            <textarea id="edit-alamat-{{ $employee->id }}" name="alamat" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">{{ old('alamat', $employee->alamat) }}</textarea>
                        </div>
                        <div class="flex justify-end sm:col-span-2 xl:col-span-4">
                            <button type="submit" class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">Simpan Perubahan</button>
                        </div>
                    </form>
                </section>
            </dialog>
            @endforeach
        @endif
    </div>
    @endsection
