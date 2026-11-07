@php($isFailedEdit = old('_form') === 'edit' && (int) old('_employee_id') === $employee->id)
@php($editValue = fn (string $field, mixed $fallback = null): mixed => $isFailedEdit ? old($field, $fallback) : $fallback)
@php($editDirectorateId = $editValue('directorate_id', $employee->directorate_id ?? $employee->organizationDepartment?->directorate_id))
@php($editDepartmentId = $editValue('department_id', $employee->department_id))
@php($editUnitId = $editValue('unit_id', $employee->unit_id))
@php($editSectionId = $editValue('section_id', $employee->section_id ?? $employee->organizationPosition?->section_id))
@php($editPositionId = $editValue('position_id', $employee->position_id))
<dialog id="edit-employee-{{ $employee->id }}"
    aria-modal="true" role="dialog" aria-labelledby="edit-employee-title-{{ $employee->id }}"
    @if ($isFailedEdit) x-init="$nextTick(() => $el.showModal())" @endif
    class="h-[calc(100dvh-1rem)] w-[calc(100%-1rem)] max-w-6xl overflow-hidden rounded-2xl p-0 shadow-2xl backdrop:bg-slate-950/60 sm:h-[calc(100dvh-3rem)] sm:w-[calc(100%-3rem)]">
    <section class="flex h-full flex-col overflow-hidden bg-white" aria-labelledby="edit-employee-title-{{ $employee->id }}">
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:px-6 sm:py-5">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Employee master</p>
                <h3 id="edit-employee-title-{{ $employee->id }}" class="mt-1 font-display text-xl font-bold text-slate-950 sm:text-2xl">Edit Data Pegawai</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500">SAP {{ $employee->sap }} · Perbarui identitas, organisasi, dan data personal pegawai.</p>
            </div>
            <button type="button" onclick="this.closest('dialog').close()" aria-label="Tutup formulir edit pegawai"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <nav aria-label="Bagian formulir edit pegawai" class="shrink-0 border-b border-slate-200 bg-white px-4 py-2.5 sm:px-6">
            <div class="flex gap-2 overflow-x-auto pb-0.5">
                <button type="button" onclick="document.getElementById('edit-employee-identity-{{ $employee->id }}').scrollIntoView({ behavior: 'smooth', block: 'start' })" class="whitespace-nowrap rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white">01 Identitas</button>
                <button type="button" onclick="document.getElementById('edit-employee-organization-{{ $employee->id }}').scrollIntoView({ behavior: 'smooth', block: 'start' })" class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:text-sky-700">02 Organisasi</button>
                <button type="button" onclick="document.getElementById('edit-employee-sap-{{ $employee->id }}').scrollIntoView({ behavior: 'smooth', block: 'start' })" class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:text-sky-700">03 Data SAP</button>
                <button type="button" onclick="document.getElementById('edit-employee-personal-{{ $employee->id }}').scrollIntoView({ behavior: 'smooth', block: 'start' })" class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:text-sky-700">04 Personal</button>
            </div>
        </nav>

        <form method="POST" action="{{ route('data-pegawai.update', $employee) }}" x-data="employeeForm({
            directorateId: @js((string) $editDirectorateId),
            departmentId: @js((string) $editDepartmentId),
            unitId: @js((string) $editUnitId),
            sectionId: @js((string) $editSectionId),
            positionId: @js((string) $editPositionId),
            txtDir: @js($editValue('txt_dir', $employee->txt_dir)),
            txtDept: @js($editValue('txt_dept', $employee->txt_dept)),
            txtBiro: @js($editValue('txt_biro', $employee->txt_biro)),
            txtSect: @js($editValue('txt_sect', $employee->txt_sect)),
            positionName: @js($editValue('position', $employee->getAttribute('position'))),
            birthDate: @js($editValue('birth_date', $employee->birth_date?->format('Y-m-d'))),
        })" class="flex min-h-0 flex-1 flex-col">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" name="_employee_id" value="{{ $employee->id }}">
            <div id="edit-employee-form-body-{{ $employee->id }}" class="min-h-0 flex-1 overflow-y-auto bg-slate-100/70 px-4 py-5 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-5xl space-y-5">
                    @if ($isFailedEdit)
                        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                            <p class="font-bold">Periksa kembali data berikut:</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <fieldset id="edit-employee-identity-{{ $employee->id }}" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                        <legend class="sr-only">Identitas kepegawaian</legend>
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-xs font-bold text-white">01</span>
                            <div>
                                <h4 class="font-display font-bold text-slate-950">Identitas kepegawaian</h4>
                                <p class="mt-0.5 text-xs leading-5 text-slate-500">SAP wajib diisi. Nomor lainnya dapat diperbarui sesuai data sumber.</p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="edit-sap-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">SAP</label>
                <input id="edit-sap-{{ $employee->id }}" name="sap" value="{{ $editValue('sap', $employee->sap) }}" required class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-id_number-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">ID Number</label>
                <input id="edit-id_number-{{ $employee->id }}" name="id_number" value="{{ $editValue('id_number', $employee->id_number) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-agkn-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">AGKN</label>
                <input id="edit-agkn-{{ $employee->id }}" name="agkn" value="{{ $editValue('agkn', $employee->agkn) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-personal_number-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Personal Number</label>
                <input id="edit-personal_number-{{ $employee->id }}" name="personal_number" value="{{ $editValue('personal_number', $employee->personal_number) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-position_id_source-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Position ID Source</label>
                <input id="edit-position_id_source-{{ $employee->id }}" name="position_id_source" value="{{ $editValue('position_id_source', $employee->position_id_source) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
                        </div>
                    </fieldset>

                    <fieldset id="edit-employee-organization-{{ $employee->id }}" class="scroll-mt-4 rounded-2xl border border-sky-200 bg-white p-4 shadow-sm sm:p-6">
                        <legend class="sr-only">Penempatan organisasi</legend>
                        <div class="flex items-start gap-3 border-b border-sky-100 pb-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-700 text-xs font-bold text-white">02</span>
                            <div>
                                <h4 class="font-display font-bold text-slate-950">Penempatan organisasi</h4>
                                <p class="mt-0.5 text-xs leading-5 text-slate-500">Pilih berurutan dari Directorate, Department, Unit, Section, lalu Position.</p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="edit-directorate-id-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Directorate</label>
                <select id="edit-directorate-id-{{ $employee->id }}" name="directorate_id" x-model="directorateId"
                    @change="departmentId = ''; unitId = ''; sectionId = ''; positionId = ''; txtDir = selectedName(organizationDirectorates, directorateId); txtDept = ''; txtBiro = ''; txtSect = ''; positionName = ''"
                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Pilih Directorate</option>
                    <template x-for="directorate in organizationDirectorates" :key="directorate.id">
                        <option :value="String(directorate.id)" x-text="directorate.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label for="edit-department-id-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Department</label>
                <select id="edit-department-id-{{ $employee->id }}" name="department_id" x-model="departmentId"
                    @change="unitId = ''; sectionId = ''; positionId = ''; txtDept = selectedName(organizationDepartments, departmentId); txtBiro = ''; txtSect = ''; positionName = ''"
                    :disabled="!directorateId"
                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                    <option value="" x-text="directorateId ? 'Pilih Department' : 'Pilih Directorate terlebih dahulu'"></option>
                    <template x-for="department in organizationDepartments.filter(item => String(item.directorate_id) === String(directorateId))" :key="department.id">
                        <option :value="String(department.id)" x-text="department.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label for="edit-unit-id-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Unit</label>
                <select id="edit-unit-id-{{ $employee->id }}" name="unit_id" x-model="unitId"
                    @change="sectionId = ''; positionId = ''; txtBiro = selectedName(organizationUnits, unitId); txtSect = ''; positionName = ''"
                    :disabled="!departmentId"
                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                    <option value="" x-text="departmentId ? 'Pilih Unit' : 'Pilih Department terlebih dahulu'"></option>
                    <template x-for="unit in organizationUnits.filter(item => String(item.department_id) === String(departmentId))" :key="unit.id">
                        <option :value="String(unit.id)" x-text="unit.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label for="edit-section-id-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Section</label>
                <select id="edit-section-id-{{ $employee->id }}" name="section_id" x-model="sectionId"
                    @change="positionId = ''; txtSect = selectedName(organizationSections, sectionId); positionName = ''"
                    :disabled="!unitId"
                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                    <option value="" x-text="unitId ? 'Pilih Section' : 'Pilih Unit terlebih dahulu'"></option>
                    <template x-for="section in organizationSections.filter(item => String(item.unit_id) === String(unitId))" :key="section.id">
                        <option :value="String(section.id)" x-text="section.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label for="edit-position-id-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Position</label>
                <select id="edit-position-id-{{ $employee->id }}" name="position_id" x-model="positionId"
                    @change="positionName = selectedName(organizationPositions, positionId)"
                    :disabled="!sectionId"
                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                    <option value="" x-text="sectionId ? 'Pilih Position' : 'Pilih Section terlebih dahulu'"></option>
                    <template x-for="position in organizationPositions.filter(item => String(item.section_id) === String(sectionId))" :key="position.id">
                        <option :value="String(position.id)" x-text="position.name"></option>
                    </template>
                </select>
            </div>
                        </div>
                    </fieldset>

                    <fieldset id="edit-employee-sap-{{ $employee->id }}" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                        <legend class="sr-only">Data tambahan SAP</legend>
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-600 text-xs font-bold text-white">03</span>
                            <div class="min-w-0 flex-1">
                                <h4 class="font-display font-bold text-slate-950">Data tambahan SAP</h4>
                                <p class="mt-0.5 text-xs leading-5 text-slate-500">Nilai organisasi terisi otomatis dari pilihan master organization.</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">Opsional</span>
                        </div>
                        <div class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="edit-organizational_unit-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Organizational Unit</label>
                <input id="edit-organizational_unit-{{ $employee->id }}" name="organizational_unit" value="{{ $editValue('organizational_unit', $employee->organizational_unit) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-position-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Position</label>
                <input id="edit-position-{{ $employee->id }}" name="position" x-model="positionName" :readonly="!!positionId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
            </div>
            <div>
                <label for="edit-txt_dir-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">TXT_DIR</label>
                <input id="edit-txt_dir-{{ $employee->id }}" name="txt_dir" x-model="txtDir" :readonly="!!directorateId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
            </div>
            <div>
                <label for="edit-txt_dept-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">TXT_DEPT</label>
                <input id="edit-txt_dept-{{ $employee->id }}" name="txt_dept" x-model="txtDept" :readonly="!!departmentId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
            </div>
            <div>
                <label for="edit-txt_biro-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">TXT_BIRO</label>
                <input id="edit-txt_biro-{{ $employee->id }}" name="txt_biro" x-model="txtBiro" :readonly="!!unitId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
            </div>
            <div>
                <label for="edit-txt_sect-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">TXT_SECT</label>
                <input id="edit-txt_sect-{{ $employee->id }}" name="txt_sect" x-model="txtSect" :readonly="!!sectionId" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
            </div>
            <div>
                <label for="edit-employee_subgroup-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Employee Subgroup</label>
                <input id="edit-employee_subgroup-{{ $employee->id }}" name="employee_subgroup" value="{{ $editValue('employee_subgroup', $employee->employee_subgroup) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-cost_ctr-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Cost Ctr</label>
                <input id="edit-cost_ctr-{{ $employee->id }}" name="cost_ctr" value="{{ $editValue('cost_ctr', $employee->cost_ctr) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-cost_center-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Cost Center</label>
                <input id="edit-cost_center-{{ $employee->id }}" name="cost_center" value="{{ $editValue('cost_center', $employee->cost_center) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-masa_kontrak-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Masa Kontrak</label>
                <input id="edit-masa_kontrak-{{ $employee->id }}" name="masa_kontrak" type="date" value="{{ $editValue('masa_kontrak', $employee->masa_kontrak?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-abrevation_position-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Abrevation Position</label>
                <input id="edit-abrevation_position-{{ $employee->id }}" name="abrevation_position" value="{{ $editValue('abrevation_position', $employee->abrevation_position) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-abrevation_organization-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Abrevation Organization</label>
                <input id="edit-abrevation_organization-{{ $employee->id }}" name="abrevation_organization" value="{{ $editValue('abrevation_organization', $employee->abrevation_organization) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-personnel_area-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Personnel Area</label>
                <input id="edit-personnel_area-{{ $employee->id }}" name="personnel_area" value="{{ $editValue('personnel_area', $employee->personnel_area) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
                        </div>
                    </fieldset>

                    <fieldset id="edit-employee-personal-{{ $employee->id }}" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                        <legend class="sr-only">Data personal</legend>
                        <div class="flex items-start gap-3 border-b border-slate-200 pb-4">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-600 text-xs font-bold text-white">04</span>
                            <div>
                                <h4 class="font-display font-bold text-slate-950">Data personal</h4>
                                <p class="mt-0.5 text-xs leading-5 text-slate-500">Perbarui informasi personal dan kontak yang tersedia.</p>
                            </div>
                        </div>
                        <div class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="edit-birth_date-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Birth Date</label>
                <input id="edit-birth_date-{{ $employee->id }}" name="birth_date" type="date" x-model="birthDate" max="{{ today()->toDateString() }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-tempat_lahir-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Tempat Lahir</label>
                <input id="edit-tempat_lahir-{{ $employee->id }}" name="tempat_lahir" value="{{ $editValue('tempat_lahir', $employee->tempat_lahir) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-gender_key-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Gender Key</label>
                <input id="edit-gender_key-{{ $employee->id }}" name="gender_key" value="{{ $editValue('gender_key', $employee->gender_key) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-religious-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Religious</label>
                <input id="edit-religious-{{ $employee->id }}" name="religious" value="{{ $editValue('religious', $employee->religious) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-pendidikan-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Pendidikan</label>
                <input id="edit-pendidikan-{{ $employee->id }}" name="pendidikan" value="{{ $editValue('pendidikan', $employee->pendidikan) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-usia-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Usia</label>
                <input id="edit-usia-{{ $employee->id }}" type="number" :value="calculateAge()" readonly aria-describedby="edit-usia-help-{{ $employee->id }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-100 text-sm text-slate-600 shadow-sm">
                <p id="edit-usia-help-{{ $employee->id }}" class="mt-1 text-xs text-slate-500">Dihitung otomatis dari tanggal lahir hingga tanggal sistem.</p>
            </div>
            <div>
                <label for="edit-hiring-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Hiring</label>
                <input id="edit-hiring-{{ $employee->id }}" name="hiring" type="date" value="{{ $editValue('hiring', $employee->hiring?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-organilk-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Organilk</label>
                <input id="edit-organilk-{{ $employee->id }}" name="organilk" type="date" value="{{ $editValue('organilk', $employee->organilk?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div>
                <label for="edit-email-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Email</label>
                <input id="edit-email-{{ $employee->id }}" name="email" type="email" value="{{ $editValue('email', $employee->email) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
            </div>
            <div class="sm:col-span-2 lg:col-span-3">
                <label for="edit-alamat-{{ $employee->id }}" class="text-sm font-semibold text-slate-700">Alamat</label>
                <textarea id="edit-alamat-{{ $employee->id }}" name="alamat" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">{{ $editValue('alamat', $employee->alamat) }}</textarea>
            </div>
                        </div>
                    </fieldset>
                </div>
            </div>
            <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                <button type="button" onclick="this.closest('dialog').close()" class="ui-button ui-button-secondary w-full sm:w-auto">Batal</button>
                <button type="submit" class="ui-button ui-button-primary w-full sm:w-auto">Simpan Perubahan</button>
            </div>
        </form>
    </section>
</dialog>
