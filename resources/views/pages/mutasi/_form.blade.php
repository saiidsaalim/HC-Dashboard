@php
    $isCurrentForm = old('_form') === $formKey;
    $isCreate = $method === 'POST';
    $initialEmployeeId = $isCurrentForm ? old('employee_id') : $movement?->employee?->id;
    $initialEmployee = collect($employeeOptions)->firstWhere('id', (int) $initialEmployeeId);
    $fieldValue = fn (string $field, mixed $fallback = '') => $isCurrentForm
        ? old($field, $fallback)
        : ($movement?->{$field} ?? $fallback);
    $targetValue = fn (string $field) => $isCurrentForm ? old($field) : $movement?->{$field};
    $tmtValue = $isCurrentForm ? old('tmt') : ($movement?->tmt?->format('Y-m-d') ?? ($defaultTmt ?? null));
    $hasValidationError = $isCurrentForm && $errors->any();
    $currentOrganization = [
        'directorate' => $movement?->direktorat_lama ?: data_get($initialEmployee, 'directorate', ''),
        'department' => $movement?->departemen_lama ?: data_get($initialEmployee, 'department', ''),
        'unit' => $movement?->unit_lama ?: data_get($initialEmployee, 'unit', ''),
        'section' => $movement?->section_lama ?: data_get($initialEmployee, 'section', ''),
        'position' => $movement?->jabatan_lama ?: data_get($initialEmployee, 'position', ''),
    ];
    $fallbackEmployee = $movement ? [
        'id' => $initialEmployeeId,
        'sap' => $movement->sap,
        'name' => $movement->nama,
        'position' => $movement->jabatan_lama,
    ] : null;
@endphp

<form method="POST" action="{{ $action }}"
    x-data="{
        employees: @js($employeeOptions),
        directorates: @js($movementDirectorates),
        departments: @js($movementDepartments),
        units: @js($movementUnits),
        sections: @js($movementSections),
        positions: @js($movementPositions),
        fallbackEmployee: @js($fallbackEmployee),
        lockedOrganization: @js($movement ? $currentOrganization : null),
        search: '',
        employeeId: @js($initialEmployeeId ? (int) $initialEmployeeId : ''),
        levelLama: @js($movement?->level_lama ?? data_get($initialEmployee, 'level', '')),
        newDirectorateId: @js($targetValue('new_directorate_id')),
        newDepartmentId: @js($targetValue('new_department_id')),
        newUnitId: @js($targetValue('new_unit_id')),
        newSectionId: @js($targetValue('new_section_id')),
        newPositionId: @js($targetValue('new_position_id')),
        results() {
            const query = this.search.trim().toLocaleLowerCase();
            const matches = query === '' ? this.employees : this.employees.filter((employee) => [
                employee.sap, employee.personal_number, employee.name, employee.directorate, employee.department,
                employee.unit, employee.section, employee.position,
            ].some((value) => String(value ?? '').toLocaleLowerCase().includes(query)));
            const selectedEmployee = this.selected();
            if (selectedEmployee && !matches.some((employee) => Number(employee.id) === Number(selectedEmployee.id))) {
                return [selectedEmployee, ...matches];
            }
            return matches;
        },
        selected() {
            return this.employees.find((employee) => Number(employee.id) === Number(this.employeeId))
                ?? (Number(this.fallbackEmployee?.id) === Number(this.employeeId) ? this.fallbackEmployee : null);
        },
        currentOrganization() {
            if (this.lockedOrganization) return this.lockedOrganization;
            const employee = this.selected();
            return {
                directorate: employee?.directorate ?? '', department: employee?.department ?? '',
                unit: employee?.unit ?? '', section: employee?.section ?? '', position: employee?.position ?? '',
            };
        },
        initials() {
            const name = this.selected()?.name?.trim() ?? '';
            if (name === '') return '?';
            return name.split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toLocaleUpperCase();
        },
        selectEmployee() {
            this.newDirectorateId = ''; this.newDepartmentId = ''; this.newUnitId = '';
            this.newSectionId = ''; this.newPositionId = '';
            this.levelLama = this.selected()?.level ?? '';
        },
        clearEmployee() {
            this.employeeId = ''; this.search = ''; this.selectEmployee();
            this.$nextTick(() => (this.$refs.employeeSearch ?? this.$refs.mutationModalSearch)?.focus());
        },
        availableDepartments() { return this.departments.filter((item) => Number(item.directorate_id) === Number(this.newDirectorateId)); },
        availableUnits() { return this.units.filter((item) => Number(item.department_id) === Number(this.newDepartmentId)); },
        availableSections() { return this.sections.filter((item) => Number(item.unit_id) === Number(this.newUnitId)); },
        availablePositions() {
            return this.positions.filter((item) => Number(item.unit_id) === Number(this.newUnitId)
                && (item.section_id === null || Number(item.section_id) === Number(this.newSectionId)));
        },
    }"
    class="{{ $isCreate ? 'flex min-h-0 flex-1 flex-col' : 'mt-5' }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="_form" value="{{ $formKey }}">

    <div class="{{ $isCreate ? 'min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6 lg:px-7' : '' }} space-y-5">
        <section aria-labelledby="{{ $formKey }}-employee-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)] lg:items-end">
                <div class="min-w-0">
                    <div class="flex items-center justify-between gap-3">
                        <label id="{{ $formKey }}-employee-heading" for="{{ $formKey }}-search" class="text-sm font-bold text-slate-800">Cari Employee</label>
                        <span class="text-xs text-slate-400" x-text="`${results().length} hasil`"></span>
                    </div>
                    <div class="relative mt-2">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <x-dashboard-icon name="users" class="h-5 w-5" />
                        </span>
                        <input id="{{ $formKey }}-search" x-ref="{{ $isCreate ? 'mutationModalSearch' : 'employeeSearch' }}" type="search" x-model="search"
                            placeholder="Cari SAP, Personal Number, nama, dan organisasi..."
                            class="block w-full rounded-xl border-slate-300 py-3 pl-11 pr-4 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </div>

                    @if ($isCreate)
                        <label for="{{ $formKey }}-employee" class="sr-only">Pilih Employee</label>
                        <select id="{{ $formKey }}-employee" name="employee_id" x-model="employeeId" @change="selectEmployee()" required
                            class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            <option value="">Pilih Employee dari hasil pencarian</option>
                            <template x-for="employee in results()" :key="employee.id">
                                <option :value="employee.id" x-text="employee.label"></option>
                            </template>
                        </select>
                    @else
                        <input type="hidden" name="employee_id" :value="employeeId">
                        <p class="mt-2 text-xs text-slate-500">Employee pada record Mutasi tidak dapat diganti saat mengedit.</p>
                    @endif

                    <p x-cloak x-show="search.trim() !== '' && results().length === 0" class="mt-2 text-xs font-medium text-amber-700">
                        Employee tidak ditemukan. Coba kata kunci lain.
                    </p>
                    @if ($isCurrentForm)
                        @error('employee_id')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
                </div>

                <div class="min-w-0">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Employee terpilih</p>
                    <div x-cloak x-show="selected()" class="flex min-h-[4.5rem] items-center gap-3 rounded-xl border border-sky-200 bg-sky-50/70 p-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-sm font-bold text-white" x-text="initials()"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-900">
                                <span x-text="selected()?.sap || '—'"></span><span class="text-slate-400"> — </span><span x-text="selected()?.personal_number || selected()?.name || 'Nama belum tersedia'"></span>
                            </p>
                            <p class="mt-0.5 truncate text-xs text-slate-600" x-text="currentOrganization().position || 'Position belum tersedia'"></p>
                        </div>
                        @if ($isCreate)
                            <button type="button" @click="clearEmployee()" aria-label="Batalkan pilihan Employee"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500">
                                <span aria-hidden="true" class="text-xl leading-none">&times;</span>
                            </button>
                        @endif
                    </div>
                    <div x-show="!selected()" class="flex min-h-[4.5rem] items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 text-sm text-slate-500">
                        Pilih Employee untuk menampilkan ringkasan data.
                    </div>
                </div>
            </div>
        </section>

        @if ($movement && ! $movement->employee)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Data Employee asal tidak lagi tersedia. Organisasi saat ini tetap menampilkan snapshot Mutasi yang tersimpan.
            </div>
        @endif

        <div class="grid items-stretch gap-3 lg:grid-cols-[minmax(0,1fr)_3.5rem_minmax(0,1fr)]">
            <section aria-labelledby="{{ $formKey }}-current-organization-heading" class="min-w-0 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-slate-700 shadow-sm ring-1 ring-slate-200">
                        <x-dashboard-icon name="formasi" class="h-5 w-5" />
                    </span>
                    <div>
                        <h4 id="{{ $formKey }}-current-organization-heading" class="font-display text-base font-bold text-slate-950">Organisasi Saat Ini</h4>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Data organisasi dan jabatan saat ini dari Employee.</p>
                    </div>
                </div>
                <dl class="mt-4 space-y-2">
                    @foreach (['directorate' => 'Directorate', 'department' => 'Department', 'unit' => 'Unit', 'section' => 'Section', 'position' => 'Position'] as $field => $label)
                        <div class="grid min-w-0 grid-cols-[7rem_minmax(0,1fr)] overflow-hidden rounded-xl border border-slate-200 bg-white sm:grid-cols-[8rem_minmax(0,1fr)]">
                            <dt class="bg-slate-100 px-3 py-2.5 text-xs font-bold text-slate-600 sm:px-4">{{ $label }}</dt>
                            <dd class="min-w-0 break-words px-3 py-2.5 text-sm font-medium text-slate-700 sm:px-4" x-text="currentOrganization().{{ $field }} || '—'"></dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <div class="flex items-center justify-center" aria-hidden="true">
                <span class="flex h-11 w-11 rotate-90 items-center justify-center rounded-full bg-slate-100 text-slate-600 ring-1 ring-slate-200 lg:rotate-0">
                    <x-dashboard-icon name="arrows" class="h-5 w-5" />
                </span>
            </div>

            <section aria-labelledby="{{ $formKey }}-target-organization-heading" class="min-w-0 rounded-2xl border border-sky-200 bg-sky-50/50 p-4 sm:p-5">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-white shadow-sm">
                        <x-dashboard-icon name="formasi" class="h-5 w-5" />
                    </span>
                    <div>
                        <h4 id="{{ $formKey }}-target-organization-heading" class="font-display text-base font-bold text-slate-950">Organisasi Tujuan</h4>
                        <p class="mt-1 text-xs leading-5 text-slate-500">Pilih organisasi dan jabatan tujuan mutasi.</p>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    @php
                        $organizationFields = [
                            ['field' => 'directorate', 'label' => 'Directorate', 'model' => 'newDirectorateId'],
                            ['field' => 'department', 'label' => 'Department', 'model' => 'newDepartmentId'],
                            ['field' => 'unit', 'label' => 'Unit', 'model' => 'newUnitId'],
                            ['field' => 'section', 'label' => 'Section', 'model' => 'newSectionId'],
                            ['field' => 'position', 'label' => 'Position', 'model' => 'newPositionId'],
                        ];
                    @endphp
                    @foreach ($organizationFields as $organizationField)
                        @php
                            $field = $organizationField['field'];
                        @endphp
                        <div class="grid gap-1.5 sm:grid-cols-[7rem_minmax(0,1fr)] sm:items-center">
                            <label for="{{ $formKey }}-new-{{ $field }}" class="text-xs font-bold text-slate-700">
                                {{ $organizationField['label'] }}
                                @if ($field === 'section') <span class="font-normal text-slate-400">(opsional)</span> @endif
                            </label>
                            <div>
                                @if ($field === 'directorate')
                                    <select id="{{ $formKey }}-new-directorate" name="new_directorate_id" x-model="newDirectorateId"
                                        @change="newDepartmentId = ''; newUnitId = ''; newSectionId = ''; newPositionId = ''" required
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                        <option value="">Pilih Directorate</option>
                                        <template x-for="item in directorates" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                @elseif ($field === 'department')
                                    <select id="{{ $formKey }}-new-department" name="new_department_id" x-model="newDepartmentId"
                                        @change="newUnitId = ''; newSectionId = ''; newPositionId = ''" :disabled="!newDirectorateId" required
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                                        <option value="" x-text="!newDirectorateId ? 'Pilih Directorate terlebih dahulu' : (availableDepartments().length ? 'Pilih Department' : 'Department tidak tersedia')"></option>
                                        <template x-for="item in availableDepartments()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                @elseif ($field === 'unit')
                                    <select id="{{ $formKey }}-new-unit" name="new_unit_id" x-model="newUnitId"
                                        @change="newSectionId = ''; newPositionId = ''" :disabled="!newDepartmentId" required
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                                        <option value="" x-text="!newDepartmentId ? 'Pilih Department terlebih dahulu' : (availableUnits().length ? 'Pilih Unit' : 'Unit tidak tersedia')"></option>
                                        <template x-for="item in availableUnits()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                @elseif ($field === 'section')
                                    <select id="{{ $formKey }}-new-section" name="new_section_id" x-model="newSectionId" @change="newPositionId = ''" :disabled="!newUnitId"
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                                        <option value="" x-text="!newUnitId ? 'Pilih Unit terlebih dahulu' : 'Tanpa Section'"></option>
                                        <template x-for="item in availableSections()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                @else
                                    <select id="{{ $formKey }}-new-position" name="new_position_id" x-model="newPositionId" :disabled="!newUnitId" required
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100">
                                        <option value="" x-text="!newUnitId ? 'Pilih Unit terlebih dahulu' : (availablePositions().length ? 'Pilih Position' : 'Position tidak tersedia')"></option>
                                        <template x-for="item in availablePositions()" :key="item.id"><option :value="item.id" x-text="item.name"></option></template>
                                    </select>
                                @endif
                                @if ($isCurrentForm)
                                    @error('new_'.$field.'_id')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section aria-labelledby="{{ $formKey }}-change-detail-heading" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 ring-1 ring-sky-100">
                    <x-dashboard-icon name="document" class="h-5 w-5" />
                </span>
                <div>
                    <h4 id="{{ $formKey }}-change-detail-heading" class="font-display text-base font-bold text-slate-950">Detail Perubahan</h4>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Lengkapi informasi mutasi dan bandingkan perubahan data.</p>
                </div>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label for="{{ $formKey }}-tmt" class="text-xs font-bold text-slate-700">TMT</label>
                    <input id="{{ $formKey }}-tmt" name="tmt" type="date" value="{{ $tmtValue }}" required
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    @if ($isCurrentForm) @error('tmt')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror @endif
                </div>
                <div>
                    <label for="{{ $formKey }}-pg" class="text-xs font-bold text-slate-700">PG</label>
                    <input id="{{ $formKey }}-pg" name="pg" value="{{ $fieldValue('pg') }}" required
                        class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    @if ($isCurrentForm) @error('pg')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror @endif
                </div>
            </div>

            <div class="my-4 border-t border-slate-100"></div>

            <div class="grid gap-5 lg:grid-cols-2 lg:divide-x lg:divide-slate-200">
                @foreach ([['prefix' => 'level', 'label' => 'Level'], ['prefix' => 'jg', 'label' => 'JG']] as $change)
                    <div class="grid items-end gap-3 sm:grid-cols-[minmax(0,1fr)_2rem_minmax(0,1fr)] {{ $loop->last ? 'lg:pl-5' : '' }}">
                        <div>
                            <label for="{{ $formKey }}-{{ $change['prefix'] }}_lama" class="text-xs font-bold text-slate-700">{{ $change['label'] }} Lama</label>
                            <input id="{{ $formKey }}-{{ $change['prefix'] }}_lama" name="{{ $change['prefix'] }}_lama"
                                value="{{ $fieldValue($change['prefix'].'_lama') }}" required @readonly($movement !== null || $change['prefix'] === 'level')
                                @if ($change['prefix'] === 'level') x-model="levelLama" @endif
                                class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 readonly:border-slate-200 readonly:bg-slate-100 readonly:text-slate-600">
                            @if ($isCurrentForm) @error($change['prefix'].'_lama')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror @endif
                        </div>
                        <span class="flex h-11 items-center justify-center rotate-90 text-sky-600 sm:rotate-0" aria-hidden="true">
                            <x-dashboard-icon name="arrows" class="h-5 w-5" />
                        </span>
                        <div>
                            <label for="{{ $formKey }}-{{ $change['prefix'] }}_baru" class="text-xs font-bold text-slate-700">{{ $change['label'] }} Baru</label>
                            @if ($change['prefix'] === 'level')
                                <select id="{{ $formKey }}-level_baru" name="level_baru" required
                                    class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                                    <option value="">Pilih Level Baru</option>
                                    @foreach ($movementLevels as $level)
                                        <option value="{{ $level }}" @selected((string) $fieldValue('level_baru') === $level)>{{ $level }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input id="{{ $formKey }}-{{ $change['prefix'] }}_baru" name="{{ $change['prefix'] }}_baru"
                                    value="{{ $fieldValue($change['prefix'].'_baru') }}" required
                                    class="mt-1.5 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                            @endif
                            @if ($isCurrentForm) @error($change['prefix'].'_baru')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $hasValidationError ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-800' }}"
            role="{{ $hasValidationError ? 'alert' : 'note' }}">
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-current text-xs font-bold" aria-hidden="true">!</span>
            <p>Minimal satu organisasi atau Position harus berubah.</p>
        </div>
    </div>

    <footer class="{{ $isCreate ? 'flex-none border-t border-slate-200 bg-white/95 px-4 py-4 backdrop-blur sm:px-6 lg:px-7' : 'mt-5 border-t border-slate-200 pt-4' }}">
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            @if ($isCreate)
                <button type="button" @click="closeModal()" class="ui-button ui-button-secondary w-full sm:w-auto">Batal</button>
            @else
                <button type="button" onclick="this.closest('details')?.removeAttribute('open')" class="ui-button ui-button-secondary w-full sm:w-auto">Batal</button>
            @endif
            <button type="submit" class="ui-button ui-button-primary w-full sm:w-auto sm:min-w-48">
                <span>{{ $submitLabel }}</span>
            </button>
        </div>
    </footer>
</form>
