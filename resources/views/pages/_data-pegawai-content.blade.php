@php($canCreateEmployees = auth()->user()->hasPermission('employees.create'))
    @php($canUpdateEmployees = auth()->user()->hasPermission('employees.update'))
    @php($canDeleteEmployees = auth()->user()->hasPermission('employees.delete'))
    @php($canImportEmployees = auth()->user()->hasPermission('employees.import'))
    @php($canSyncEmployeeOrganization = auth()->user()->hasPermission('employees.sync-organization'))
    @php($canExportEmployees = auth()->user()->hasPermission('employees.export'))
    @php($canPrintEmployees = auth()->user()->hasPermission('employees.print'))
    @php($incompletePercentage = $employeeStatistics['total'] > 0 ? round(($employeeStatistics['incomplete'] / $employeeStatistics['total']) * 100, 1) : 0)
    @php($retiringPercentage = $employeeStatistics['total'] > 0 ? round(($employeeStatistics['retiring_this_year'] / $employeeStatistics['total']) * 100, 1) : 0)
    @php($employeeAvatarClasses = ['bg-red-100 text-red-700', 'bg-sky-100 text-sky-700', 'bg-violet-100 text-violet-700', 'bg-orange-100 text-orange-700', 'bg-emerald-100 text-emerald-700'])
    @php($employeeLevelBadgePalette = ['border-rose-300 bg-rose-100 text-rose-800', 'border-orange-300 bg-orange-100 text-orange-800', 'border-amber-300 bg-amber-100 text-amber-800', 'border-lime-300 bg-lime-100 text-lime-800', 'border-green-300 bg-green-100 text-green-800', 'border-emerald-300 bg-emerald-100 text-emerald-800', 'border-teal-300 bg-teal-100 text-teal-800', 'border-cyan-300 bg-cyan-100 text-cyan-800', 'border-sky-300 bg-sky-100 text-sky-800', 'border-blue-300 bg-blue-100 text-blue-800', 'border-indigo-300 bg-indigo-100 text-indigo-800', 'border-violet-300 bg-violet-100 text-violet-800', 'border-purple-300 bg-purple-100 text-purple-800', 'border-fuchsia-300 bg-fuchsia-100 text-fuchsia-800', 'border-pink-300 bg-pink-100 text-pink-800'])
    @php($employeeLevelClasses = $employeeLevels->values()->mapWithKeys(fn (string $level, int $index): array => [$level => $employeeLevelBadgePalette[$index % count($employeeLevelBadgePalette)]]))
    <div x-data="{
        open: @js($errors->any() && old('_form') === 'create'),
        importOpen: @js($errors->has('file') && blank(old('_form'))),
        selectedEmployeeIds: [],
        pageEmployeeIds: @js($employees->pluck('id')->map(fn ($id) => (string) $id)->values()),
        organizationDirectorates: @js($organizationDirectorates->map(fn ($directorate) => ['id' => $directorate->id, 'name' => $directorate->name])->values()),
        organizationDepartments: @js($organizationDepartments->map(fn ($department) => ['id' => $department->id, 'directorate_id' => $department->directorate_id, 'name' => $department->name])->values()),
        organizationUnits: @js($organizationUnits->map(fn ($unit) => ['id' => $unit->id, 'department_id' => $unit->department_id, 'name' => $unit->name])->values()),
        organizationSections: @js($organizationSections->map(fn ($section) => ['id' => $section->id, 'unit_id' => $section->unit_id, 'name' => $section->name])->values()),
        organizationPositions: @js($organizationPositions->map(fn ($position) => ['id' => $position->id, 'section_id' => $position->section_id, 'name' => $position->name])->values()),
        employeeForm(initialValues = {}) {
            return {
                directorateId: String(initialValues.directorateId ?? ''),
                departmentId: String(initialValues.departmentId ?? ''),
                unitId: String(initialValues.unitId ?? ''),
                sectionId: String(initialValues.sectionId ?? ''),
                positionId: String(initialValues.positionId ?? ''),
                txtDir: initialValues.txtDir ?? '',
                txtDept: initialValues.txtDept ?? '',
                txtBiro: initialValues.txtBiro ?? '',
                txtSect: initialValues.txtSect ?? '',
                positionName: initialValues.positionName ?? '',
                birthDate: initialValues.birthDate ?? '',
                systemDate: @js(today()->toDateString()),
                calculateAge() {
                    if (!this.birthDate) {
                        return '';
                    }

                    const [birthYear, birthMonth, birthDay] = this.birthDate.split('-').map(Number);
                    const [currentYear, currentMonth, currentDay] = this.systemDate.split('-').map(Number);

                    if (![birthYear, birthMonth, birthDay].every(Number.isFinite)) {
                        return '';
                    }

                    let age = currentYear - birthYear;
                    if (currentMonth < birthMonth || (currentMonth === birthMonth && currentDay < birthDay)) {
                        age--;
                    }

                    return age >= 0 ? age : '';
                },
                selectedName(items, id) {
                    return items.find(item => String(item.id) === String(id))?.name ?? '';
                },
            };
        },
        get selectedEmployeeCount() {
            return this.selectedEmployeeIds.length;
        },
        get allPageEmployeesSelected() {
            return this.pageEmployeeIds.length > 0 && this.pageEmployeeIds.every(id => this.selectedEmployeeIds.includes(id));
        },
        togglePageEmployees(checked) {
            this.selectedEmployeeIds = checked ? [...this.pageEmployeeIds] : [];
        },
        async chooseExport(pdfUrl, excelUrl, canPrint, canExport) {
            if (canPrint && !canExport) {
                window.location.href = pdfUrl;
                return;
            }

            if (canExport && !canPrint) {
                window.location.href = excelUrl;
                return;
            }

            const result = await Swal.fire({
                title: 'Pilih format laporan',
                text: 'Seluruh data detail pegawai akan disertakan.',
                showDenyButton: canExport,
                showConfirmButton: canPrint,
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
    }" x-effect="document.body.classList.toggle('overflow-hidden', open || importOpen)" data-employee-dashboard>
        <section aria-label="Ringkasan data pegawai" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article data-employee-stat-card class="flex min-h-28 items-center gap-4 overflow-hidden rounded-2xl border border-red-100 bg-gradient-to-r from-red-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700 ring-1 ring-red-200"><x-dashboard-icon name="users" class="h-7 w-7" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-slate-600">Total Pegawai</p>
                    <p data-employee-counter="{{ $employeeStatistics['total'] }}" class="mt-1 font-display text-2xl font-bold tabular-nums text-slate-950">{{ number_format($employeeStatistics['total'], 0, ',', '.') }}</p>
                    <p class="truncate text-[11px] text-slate-500">Employee master</p>
                </div>
                <span class="flex h-12 items-end gap-1 text-red-300" aria-hidden="true"><i class="h-4 w-2 rounded-t bg-current"></i><i class="h-7 w-2 rounded-t bg-current"></i><i class="h-10 w-2 rounded-t bg-current"></i></span>
            </article>
            <article data-employee-stat-card class="flex min-h-28 items-center gap-4 overflow-hidden rounded-2xl border border-sky-100 bg-gradient-to-r from-sky-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-600 ring-1 ring-sky-200"><x-dashboard-icon name="document" class="h-7 w-7" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-slate-600">Data Pegawai Belum Lengkap</p>
                    <p class="mt-1 flex items-baseline gap-2"><span data-employee-counter="{{ $employeeStatistics['incomplete'] }}" class="font-display text-2xl font-bold tabular-nums text-slate-950">{{ number_format($employeeStatistics['incomplete'], 0, ',', '.') }}</span><span class="text-xs font-semibold text-slate-600">{{ number_format($incompletePercentage, 1, ',', '.') }}%</span></p>
                    <p class="truncate text-[11px] text-slate-500">Field utama perlu dilengkapi</p>
                </div>
                <span class="h-12 w-12 shrink-0 rounded-full" aria-hidden="true" style="background: conic-gradient(#0284c7 {{ $incompletePercentage }}%, #e2e8f0 0); mask: radial-gradient(farthest-side, transparent 58%, #000 59%); -webkit-mask: radial-gradient(farthest-side, transparent 58%, #000 59%);"></span>
            </article>
            <article data-employee-stat-card class="flex min-h-28 items-center gap-4 overflow-hidden rounded-2xl border border-amber-100 bg-gradient-to-r from-amber-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 ring-1 ring-amber-200"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 13h3v3H8z" /></svg></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-slate-600">Pensiun Tahun Ini</p>
                    <p class="mt-1 flex items-baseline gap-2"><span data-employee-counter="{{ $employeeStatistics['retiring_this_year'] }}" class="font-display text-2xl font-bold tabular-nums text-slate-950">{{ number_format($employeeStatistics['retiring_this_year'], 0, ',', '.') }}</span><span class="text-xs font-semibold text-slate-600">{{ number_format($retiringPercentage, 1, ',', '.') }}%</span></p>
                    <p class="truncate text-[11px] text-slate-500">Usia pensiun {{ $employeeStatistics['retirement_age'] }} tahun</p>
                </div>
                <span class="h-12 w-12 shrink-0 rounded-full" aria-hidden="true" style="background: conic-gradient(#f59e0b {{ $retiringPercentage }}%, #e2e8f0 0); mask: radial-gradient(farthest-side, transparent 58%, #000 59%); -webkit-mask: radial-gradient(farthest-side, transparent 58%, #000 59%);"></span>
            </article>
            <article data-employee-stat-card class="flex min-h-28 items-center gap-4 overflow-hidden rounded-2xl border border-violet-100 bg-gradient-to-r from-violet-50 to-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700 ring-1 ring-violet-200"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M7 3v3m10-3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 14h2v2H9z" /></svg></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-slate-600">Rata-rata Masa Kerja</p>
                    @if ($employeeStatistics['average_tenure_years'] !== null)
                        <p data-employee-counter="{{ $employeeStatistics['average_tenure_years'] }}" data-counter-decimals="1" data-counter-suffix=" tahun" class="mt-1 whitespace-nowrap font-display text-2xl font-bold tabular-nums text-slate-950">{{ number_format($employeeStatistics['average_tenure_years'], 1, ',', '.') }} tahun</p>
                        <p class="truncate text-[11px] text-slate-500">{{ number_format($employeeStatistics['tenure_sample_size'], 0, ',', '.') }} tanggal hiring valid</p>
                    @else
                        <p class="mt-1 text-sm font-bold text-slate-700">Belum tersedia</p>
                        <p class="text-[11px] text-slate-500">Tanggal hiring belum tersedia</p>
                    @endif
                </div>
                <span class="flex h-12 items-end gap-1 text-violet-300" aria-hidden="true"><i class="h-4 w-2 rounded-t bg-current"></i><i class="h-7 w-2 rounded-t bg-current"></i><i class="h-10 w-2 rounded-t bg-current"></i></span>
            </article>
        </section>

        <section data-employee-charts aria-label="Grafik data pegawai" class="mt-5 overflow-x-auto pb-1">
            <div class="grid min-w-[960px] grid-cols-2 gap-4">
                <article data-employee-chart-card class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-3">
                        <h2 class="font-display text-base font-bold text-slate-950">Jumlah Hiring per Tahun</h2>
                        <p class="text-xs text-slate-500">Distribusi pegawai berdasarkan tahun hiring.</p>
                    </div>
                    <div data-employee-hiring-chart data-chart='@json($employeeCharts['hiring'])' class="min-h-[320px]"></div>
                </article>
                <article data-employee-chart-card class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-3">
                        <h2 class="font-display text-base font-bold text-slate-950">Tingkat Pendidikan</h2>
                        <p class="text-xs text-slate-500">Jumlah pegawai pada setiap tingkat pendidikan.</p>
                    </div>
                    <div data-employee-education-chart data-chart='@json($employeeCharts['education'])' class="min-h-[320px]"></div>
                </article>
            </div>
        </section>

        <section data-employee-toolbar class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 p-3 sm:p-4 xl:flex-row xl:items-end">
                <form method="GET" action="{{ route($employeeIndexRoute) }}" class="grid min-w-0 flex-1 gap-2 md:grid-cols-2 xl:grid-cols-[minmax(280px,2fr)_repeat(4,minmax(125px,1fr))]">
                    <input type="hidden" name="position_id" value="{{ $positionId }}">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="gender" value="{{ $gender }}">
                    <input type="hidden" name="sort_by" value="{{ $sortBy }}">
                    <label class="relative block self-end">
                        <span class="sr-only">Cari pegawai</span>
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="9" cy="9" r="5.5"/><path d="m13 13 4 4" stroke-linecap="round" /></svg>
                        <input data-employee-search name="search" value="{{ $search }}" type="search" placeholder="Cari nama, NIP, email, jabatan, departemen..." class="block h-11 w-full rounded-xl border-slate-300 py-2 pl-11 pr-16 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                        <span class="pointer-events-none absolute right-2 top-1/2 hidden -translate-y-1/2 rounded-md bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-500 sm:inline">Ctrl + K</span>
                    </label>
                    <label class="block text-[11px] font-semibold text-slate-600">Directorate
                        <select name="directorate_id" onchange="this.form.elements.namedItem('department_id').value = ''; this.form.elements.namedItem('unit_id').value = ''; this.form.elements.namedItem('position_id').value = ''; this.form.submit()" class="mt-1 block h-11 w-full rounded-xl border-slate-300 py-2 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                            <option value="">Semua</option>
                            @foreach ($organizationDirectorates as $directorateOption)
                                <option value="{{ $directorateOption->id }}" @selected((string) $directorateOption->id === $directorateId)>{{ $directorateOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-[11px] font-semibold text-slate-600">Departemen
                        <select name="department_id" onchange="this.form.elements.namedItem('unit_id').value = ''; this.form.elements.namedItem('position_id').value = ''; this.form.submit()" class="mt-1 block h-11 w-full rounded-xl border-slate-300 py-2 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                            <option value="">Semua</option>
                            @foreach ($filterDepartments as $departmentOption)
                                <option value="{{ $departmentOption->id }}" @selected((string) $departmentOption->id === $departmentId)>{{ $departmentOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-[11px] font-semibold text-slate-600">Unit
                        <select name="unit_id" onchange="this.form.elements.namedItem('position_id').value = ''; this.form.submit()" @disabled($departmentId === '') class="mt-1 block h-11 w-full rounded-xl border-slate-300 py-2 text-sm shadow-sm focus:border-red-400 focus:ring-red-400 disabled:bg-slate-100">
                            <option value="">Semua</option>
                            @foreach ($filterUnits as $unitOption)
                                <option value="{{ $unitOption->id }}" @selected((string) $unitOption->id === $unitId)>{{ $unitOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-[11px] font-semibold text-slate-600">Level
                        <select name="level" onchange="this.form.submit()" class="mt-1 block h-11 w-full rounded-xl border-slate-300 py-2 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                            <option value="">Semua</option>
                            @foreach ($employeeLevels as $levelOption)
                                <option value="{{ $levelOption }}" @selected($levelOption === $level)>{{ $levelOption }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
                <x-ui.filter-panel id="employee-filters" title="Filter Lanjutan" button-label="Filter Lanjutan"
                    :action="route($employeeIndexRoute)" :reset-href="route($employeeIndexRoute)"
                    :active="$search !== '' || $directorateId !== '' || $departmentId !== '' || $unitId !== '' || $positionId !== '' || $status !== '' || $level !== '' || $gender !== '' || $sortBy !== 'latest'">
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="directorate_id" value="{{ $directorateId }}">
                    <input type="hidden" name="department_id" value="{{ $departmentId }}">
                    <input type="hidden" name="unit_id" value="{{ $unitId }}">
                    <input type="hidden" name="level" value="{{ $level }}">
                    <label for="employee-position-filter" class="block text-sm font-semibold text-slate-700">Position
                        <select id="employee-position-filter" name="position_id" @disabled($unitId === '') class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400 disabled:bg-slate-100">
                            <option value="">Semua position</option>
                            @foreach ($filterPositions as $positionOption)
                                <option value="{{ $positionOption->id }}" @selected((string) $positionOption->id === $positionId)>{{ $positionOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label for="employee-status-filter" class="block text-sm font-semibold text-slate-700">Status
                        <select id="employee-status-filter" name="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                            <option value="">Semua status</option>
                            @foreach ($statusOptions as $statusOption)
                                <option value="{{ $statusOption }}" @selected($statusOption === $status)>{{ $statusOption }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label for="employee-gender-filter" class="block text-sm font-semibold text-slate-700">Gender
                        <select id="employee-gender-filter" name="gender" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                            <option value="">Semua gender</option>
                            @foreach ($genderOptions as $genderOption)
                                <option value="{{ $genderOption }}" @selected($genderOption === $gender)>{{ $genderOption }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label for="employee-sort" class="block text-sm font-semibold text-slate-700">Urutkan berdasarkan
                        <select id="employee-sort" name="sort_by" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                            <option value="latest" @selected($sortBy === 'latest')>Terbaru ditambahkan</option>
                            <option value="organic_newest" @selected($sortBy === 'organic_newest')>Organik terbaru</option>
                            <option value="organic_oldest" @selected($sortBy === 'organic_oldest')>Organik terlama</option>
                        </select>
                    </label>
                </x-ui.filter-panel>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-200 p-3 sm:p-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                    @if ($canCreateEmployees)
                        <button type="button" data-employee-modal-trigger
                            @click="open = true; $nextTick(() => { document.getElementById('employee-form-body').scrollTop = 0; document.getElementById('sap')?.focus() })"
                            class="ui-button border border-red-700 bg-red-700 text-white shadow-lg shadow-red-700/20 hover:border-red-800 hover:bg-red-800">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke-linecap="round" /></svg>
                            Tambah Pegawai
                        </button>
                    @endif
                    @if ($canImportEmployees)
                        <button type="button" data-employee-import-trigger @click="importOpen = true" class="ui-button ui-button-secondary">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 13V3m0 0L6 7m4-4 4 4M4 12v4h12v-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            Import SAP
                        </button>
                    @endif
                    @if ($canPrintEmployees || $canExportEmployees)
                        <button type="button" data-pdf-url="{{ route('data-pegawai.print', ['autoprint' => 1]) }}" data-excel-url="{{ route('data-pegawai.export') }}" @click="chooseExport($el.dataset.pdfUrl, $el.dataset.excelUrl, @js($canPrintEmployees), @js($canExportEmployees))" class="ui-button ui-button-secondary">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 2h6l4 4v12H4V2h2Z" stroke-linejoin="round"/><path d="M12 2v5h4M7 12h6M7 15h4" stroke-linecap="round" /></svg>
                            Ekspor Semua
                        </button>
                    @endif
                    @if ($canSyncEmployeeOrganization)
                        <a href="{{ route('data-pegawai.organization-sync.preview') }}" class="ui-button ui-button-secondary">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 7a6 6 0 0 0-10-2L4 7m0 0h4M4 7V3m0 10a6 6 0 0 0 10 2l2-2m0 0h-4m4 0v4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            Sinkronkan Organisasi
                        </a>
                    @endif
                    @if ($canImportEmployees)
                        <a href="{{ route('data-pegawai.import.review.index') }}" class="ui-button ui-button-secondary">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 3h10v14H5zM8 7h4M8 10h4M8 13h3" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            Riwayat Import
                        </a>
                    @endif
                </div>
                <div class="flex items-center gap-2 self-end lg:self-auto">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger"><button type="button" aria-label="Aksi lainnya" class="ui-button ui-button-secondary px-3"><svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="4" r="1.4"/><circle cx="10" cy="10" r="1.4"/><circle cx="10" cy="16" r="1.4"/></svg></button></x-slot>
                        <x-slot name="content"><a href="{{ route($employeeIndexRoute) }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Reset semua filter</a></x-slot>
                    </x-dropdown>
                    @if ($canDeleteEmployees)
                        <button type="submit" form="delete-all-employees" class="ui-button border border-red-300 bg-white text-red-700 hover:border-red-400 hover:bg-red-50">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h12M8 3h4l1 3H7l1-3Zm-2 3 1 11h6l1-11M8 9v5m4-5v5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            Hapus Semua Data
                        </button>
                    @endif
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
                        @if (session('import_batch_id') && $canImportEmployees)
                            <a href="{{ route('data-pegawai.import.review.show', session('import_batch_id')) }}"
                                class="ml-2 font-semibold underline underline-offset-2">Buka review batch</a>
                        @endif
            </div>
        @endif

        @if ($errors->any() && blank(old('_form')))
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold">Data pegawai tidak dapat diproses.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($canImportEmployees)
            <div x-cloak x-show="importOpen" x-transition.opacity @keydown.escape.window="importOpen = false"
                @click.self="importOpen = false" class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
                <section x-ref="importPanel" data-employee-import-panel role="dialog" aria-modal="true" aria-labelledby="employee-import-title"
                    class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-5 sm:px-6">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-red-600">Employee master</p>
                            <h3 id="employee-import-title" class="mt-1 font-display text-xl font-bold text-slate-950">Import Data SAP</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Unggah file sesuai format import yang sudah digunakan sistem.</p>
                        </div>
                        <button type="button" @click="importOpen = false" aria-label="Tutup import SAP" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" /></svg>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('data-pegawai.import') }}" enctype="multipart/form-data" class="p-5 sm:p-6">
                        @csrf
                        <label for="employee-import-file" class="block text-sm font-semibold text-slate-700">File SAP</label>
                        <label for="employee-import-file" class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center transition hover:border-red-300 hover:bg-red-50/40">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-red-700 shadow-sm ring-1 ring-slate-200">
                                <svg class="h-6 w-6" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 13V3m0 0L6 7m4-4 4 4M4 12v4h12v-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </span>
                            <span class="mt-3 text-sm font-semibold text-slate-800">Pilih file untuk diunggah</span>
                            <span class="mt-1 text-xs text-slate-500">Format yang diterima: XLSX, CSV, atau TXT</span>
                        </label>
                        <input id="employee-import-file" type="file" name="file" accept=".xlsx,.csv,.txt" required
                            class="mt-3 block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-500 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-xs file:font-semibold file:text-slate-700">
                        @error('file')
                            <p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
                        @enderror
                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button type="button" @click="importOpen = false" class="ui-button ui-button-secondary">Batal</button>
                            <button type="submit" class="ui-button border border-red-700 bg-red-700 text-white hover:border-red-800 hover:bg-red-800">Unggah dan Review</button>
                        </div>
                    </form>
                </section>
            </div>
        @endif

        @if ($canCreateEmployees)
            <div x-cloak x-show="open" x-transition.opacity
                @keydown.escape.window="open = false"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-2 backdrop-blur-sm sm:p-6"
                @click.self="open = false">
                <section data-employee-create-panel class="flex h-[calc(100dvh-1rem)] w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-[calc(100dvh-3rem)] sm:max-w-6xl sm:rounded-2xl sm:border sm:border-slate-200" role="dialog"
                    aria-modal="true" aria-labelledby="employee-modal-title">
                    <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-white px-4 py-4 sm:px-6 sm:py-5">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Employee master</p>
                            <h3 id="employee-modal-title" class="mt-1 font-display text-xl font-bold text-slate-950 sm:text-2xl">Tambah Data Pegawai</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-500">Isi identitas utama terlebih dahulu, lalu lengkapi data organisasi dan personal yang tersedia.</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="Tutup formulir tambah pegawai"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-400">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>

                    <nav aria-label="Bagian formulir pegawai"
                        class="shrink-0 border-b border-slate-200 bg-white px-4 py-2.5 sm:px-6">
                        <div class="flex gap-2 overflow-x-auto pb-0.5">
                            <button type="button" @click="document.getElementById('employee-section-identity').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                                class="whitespace-nowrap rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white">01 Identitas</button>
                            <button type="button" @click="document.getElementById('employee-section-organization').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                                class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:text-sky-700">02 Organisasi</button>
                            <button type="button" @click="document.getElementById('employee-section-sap').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                                class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:text-sky-700">03 Data SAP</button>
                            <button type="button" @click="document.getElementById('employee-section-personal').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                                class="whitespace-nowrap rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-sky-300 hover:text-sky-700">04 Personal</button>
                        </div>
                    </nav>

                <form method="POST" action="{{ route('data-pegawai.store') }}" x-data="employeeForm({
                    directorateId: @js((string) old('directorate_id', '')),
                    departmentId: @js((string) old('department_id', '')),
                    unitId: @js((string) old('unit_id', '')),
                    sectionId: @js((string) old('section_id', '')),
                    positionId: @js((string) old('position_id', '')),
                    txtDir: @js(old('txt_dir', '')),
                    txtDept: @js(old('txt_dept', '')),
                    txtBiro: @js(old('txt_biro', '')),
                    txtSect: @js(old('txt_sect', '')),
                    positionName: @js(old('position', '')),
                    birthDate: @js(old('birth_date', '')),
                })" class="flex min-h-0 flex-1 flex-col">
                    @csrf
                    <input type="hidden" name="_form" value="create">
                    <div id="employee-form-body" class="min-h-0 flex-1 overflow-y-auto bg-slate-100/70 px-4 py-5 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-5xl space-y-5">
                            @if ($errors->any() && old('_form') === 'create')
                                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                                    <p class="font-bold">Periksa kembali data berikut:</p>
                                    <ul class="mt-2 list-disc space-y-1 pl-5">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <fieldset id="employee-section-identity" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                                <legend class="sr-only">Identitas kepegawaian</legend>
                                <div class="flex items-start gap-3 border-b border-slate-200 pb-4">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-xs font-bold text-white">01</span>
                                    <div>
                                        <h4 class="font-display font-bold text-slate-950">Identitas kepegawaian</h4>
                                        <p class="mt-0.5 text-xs leading-5 text-slate-500">SAP wajib diisi. Nomor lainnya dapat dilengkapi sesuai data sumber.</p>
                                    </div>
                                </div>
                                <div class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="sap" class="text-sm font-semibold text-slate-700">SAP <span class="text-red-600">*</span></label>
                        <input id="sap" name="sap" value="{{ old('sap') }}" required
                            @error('sap') aria-invalid="true" aria-describedby="sap-error" @enderror
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('sap') border-red-400 @enderror">
                        @error('sap')<p id="sap-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
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
                                </div>
                            </fieldset>

                            <fieldset id="employee-section-organization" class="scroll-mt-4 rounded-2xl border border-sky-200 bg-white p-4 shadow-sm sm:p-6">
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
                        <label for="create-directorate-id" class="text-sm font-semibold text-slate-700">Directorate</label>
                        <select id="create-directorate-id" name="directorate_id" x-model="directorateId"
                            @change="departmentId = ''; unitId = ''; sectionId = ''; positionId = ''; txtDir = selectedName(organizationDirectorates, directorateId); txtDept = ''; txtBiro = ''; txtSect = ''; positionName = ''"
                            @error('directorate_id') aria-invalid="true" aria-describedby="directorate-id-error" @enderror
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('directorate_id') border-red-400 @enderror">
                            <option value="">Pilih Directorate</option>
                            <template x-for="directorate in organizationDirectorates" :key="directorate.id">
                                <option :value="String(directorate.id)" x-text="directorate.name"></option>
                            </template>
                        </select>
                        @error('directorate_id')<p id="directorate-id-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="create-department-id" class="text-sm font-semibold text-slate-700">Department</label>
                        <select id="create-department-id" name="department_id" x-model="departmentId"
                            @change="unitId = ''; sectionId = ''; positionId = ''; txtDept = selectedName(organizationDepartments, departmentId); txtBiro = ''; txtSect = ''; positionName = ''"
                            :disabled="!directorateId"
                            @error('department_id') aria-invalid="true" aria-describedby="department-id-error" @enderror
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 @error('department_id') border-red-400 @enderror">
                            <option value="" x-text="directorateId ? 'Pilih Department' : 'Pilih Directorate terlebih dahulu'"></option>
                            <template x-for="department in organizationDepartments.filter(item => String(item.directorate_id) === String(directorateId))" :key="department.id">
                                <option :value="String(department.id)" x-text="department.name"></option>
                            </template>
                        </select>
                        @error('department_id')<p id="department-id-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="create-unit-id" class="text-sm font-semibold text-slate-700">Unit</label>
                        <select id="create-unit-id" name="unit_id" x-model="unitId"
                            @change="sectionId = ''; positionId = ''; txtBiro = selectedName(organizationUnits, unitId); txtSect = ''; positionName = ''"
                            :disabled="!departmentId"
                            @error('unit_id') aria-invalid="true" aria-describedby="unit-id-error" @enderror
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 @error('unit_id') border-red-400 @enderror">
                            <option value="" x-text="departmentId ? 'Pilih Unit' : 'Pilih Department terlebih dahulu'"></option>
                            <template x-for="unit in organizationUnits.filter(item => String(item.department_id) === String(departmentId))" :key="unit.id">
                                <option :value="String(unit.id)" x-text="unit.name"></option>
                            </template>
                        </select>
                        @error('unit_id')<p id="unit-id-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="create-section-id" class="text-sm font-semibold text-slate-700">Section</label>
                        <select id="create-section-id" name="section_id" x-model="sectionId"
                            @change="positionId = ''; txtSect = selectedName(organizationSections, sectionId); positionName = ''"
                            :disabled="!unitId"
                            @error('section_id') aria-invalid="true" aria-describedby="section-id-error" @enderror
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 @error('section_id') border-red-400 @enderror">
                            <option value="" x-text="unitId ? 'Pilih Section' : 'Pilih Unit terlebih dahulu'"></option>
                            <template x-for="section in organizationSections.filter(item => String(item.unit_id) === String(unitId))" :key="section.id">
                                <option :value="String(section.id)" x-text="section.name"></option>
                            </template>
                        </select>
                        @error('section_id')<p id="section-id-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="create-position-id" class="text-sm font-semibold text-slate-700">Position</label>
                        <select id="create-position-id" name="position_id" x-model="positionId"
                            @change="positionName = selectedName(organizationPositions, positionId)"
                            :disabled="!sectionId"
                            @error('position_id') aria-invalid="true" aria-describedby="position-id-error" @enderror
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 @error('position_id') border-red-400 @enderror">
                            <option value="" x-text="sectionId ? 'Pilih Position' : 'Pilih Section terlebih dahulu'"></option>
                            <template x-for="position in organizationPositions.filter(item => String(item.section_id) === String(sectionId))" :key="position.id">
                                <option :value="String(position.id)" x-text="position.name"></option>
                            </template>
                        </select>
                        @error('position_id')<p id="position-id-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                                </div>
                            </fieldset>

                            <fieldset id="employee-section-sap" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                                <legend class="sr-only">Data tambahan SAP</legend>
                                <div class="flex items-start gap-3 border-b border-slate-200 pb-4">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-600 text-xs font-bold text-white">03</span>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-display font-bold text-slate-950">Data tambahan SAP</h4>
                                        <p class="mt-0.5 text-xs leading-5 text-slate-500">Gunakan bagian ini untuk menyimpan nilai organisasi dan ketenagakerjaan dari sumber lama.</p>
                                    </div>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">Opsional</span>
                                </div>
                                <div class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="organizational_unit" class="text-sm font-semibold text-slate-700">Organizational Unit</label>
                        <input id="organizational_unit" name="organizational_unit" value="{{ old('organizational_unit') }}"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">
                    </div>
                    <div>
                        <label for="position" class="text-sm font-semibold text-slate-700">Position</label>
                        <input id="position" name="position" x-model="positionName" :readonly="!!positionId"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
                    </div>
                    <div>
                        <label for="txt_dir" class="text-sm font-semibold text-slate-700">TXT_DIR</label>
                        <input id="txt_dir" name="txt_dir" x-model="txtDir" :readonly="!!directorateId"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
                    </div>
                    <div>
                        <label for="txt_dept" class="text-sm font-semibold text-slate-700">TXT_DEPT</label>
                        <input id="txt_dept" name="txt_dept" x-model="txtDept" :readonly="!!departmentId"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
                    </div>
                    <div>
                        <label for="txt_biro" class="text-sm font-semibold text-slate-700">TXT_BIRO</label>
                        <input id="txt_biro" name="txt_biro" x-model="txtBiro" :readonly="!!unitId"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
                    </div>
                    <div>
                        <label for="txt_sect" class="text-sm font-semibold text-slate-700">TXT_SECT</label>
                        <input id="txt_sect" name="txt_sect" x-model="txtSect" :readonly="!!sectionId"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 read-only:bg-slate-100">
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
                                </div>
                            </fieldset>

                            <fieldset id="employee-section-personal" class="scroll-mt-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                                <legend class="sr-only">Data personal</legend>
                                <div class="flex items-start gap-3 border-b border-slate-200 pb-4">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-600 text-xs font-bold text-white">04</span>
                                    <div>
                                        <h4 class="font-display font-bold text-slate-950">Data personal</h4>
                                        <p class="mt-0.5 text-xs leading-5 text-slate-500">Lengkapi informasi personal dan kontak yang tersedia.</p>
                                    </div>
                                </div>
                                <div class="mt-5 grid gap-x-5 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <label for="birth_date" class="text-sm font-semibold text-slate-700">Tanggal Lahir</label>
                        <input id="birth_date" name="birth_date" type="date" x-model="birthDate" max="{{ today()->toDateString() }}"
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
                        <label for="religious" class="text-sm font-semibold text-slate-700">Agama</label>
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
                        <input id="usia" type="number" :value="calculateAge()" readonly aria-describedby="usia-help"
                            class="mt-1 block w-full rounded-xl border-slate-300 bg-slate-100 text-sm text-slate-600 shadow-sm">
                        <p id="usia-help" class="mt-1 text-xs text-slate-500">Dihitung otomatis dari tanggal lahir hingga tanggal sistem.</p>
                    </div>
                    <div>
                        <label for="hiring" class="text-sm font-semibold text-slate-700">Tanggal Hiring</label>
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
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500 @error('email') border-red-400 @enderror">
                        @error('email')<p id="email-error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="alamat" class="text-sm font-semibold text-slate-700">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400">{{ old('alamat') }}</textarea>
                    </div>
                                </div>
                            </fieldset>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col-reverse gap-3 border-t border-slate-200 bg-white px-4 py-4 sm:flex-row sm:justify-end sm:px-6">
                        <button type="button" @click="open = false"
                            class="ui-button ui-button-secondary w-full sm:w-auto">Batal</button>
                        <button type="submit" class="ui-button ui-button-primary w-full sm:w-auto">
                            Simpan Data Pegawai
                        </button>
                    </div>
                </form>
                </section>
            </div>
        @endif

    <div class="mt-8 space-y-6">
        <div data-employee-table class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_16px_45px_-28px_rgba(15,23,42,0.35)]">
            <div class="flex w-full flex-col items-stretch justify-between gap-4 border-b border-slate-200 p-4 sm:p-5 xl:flex-row xl:items-center">
                <div>
                    <h3 class="font-display text-xl font-bold text-slate-950">Daftar pegawai</h3>
                    <p class="mt-1 text-sm text-slate-500">Menampilkan {{ number_format($employees->total(), 0, ',', '.') }} data sesuai filter</p>
                </div>
                @if ($canDeleteEmployees)
                    <div class="flex min-w-0 flex-col gap-3 xl:items-end">
                        <div x-cloak x-show="selectedEmployeeCount > 0" x-transition.opacity
                            class="flex flex-wrap items-center justify-end gap-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2">
                            <span class="text-sm font-semibold text-red-800"><span x-text="selectedEmployeeCount"></span> pegawai dipilih</span>
                            <button type="submit" form="bulk-delete-employees"
                                class="ui-button border border-red-700 bg-red-700 text-white hover:border-red-800 hover:bg-red-800">Hapus Terpilih</button>
                        </div>
                        <form id="bulk-delete-employees" method="POST" action="{{ route('data-pegawai.destroy-many') }}"
                            data-confirm-submit data-confirm-title="Hapus pegawai terpilih?" data-confirm-text="Data yang dihapus tidak dapat dikembalikan."
                            data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626" data-confirm-require-checkbox=".employee-checkbox"
                            data-confirm-empty-text="Centang minimal satu pegawai.">
                        @csrf
                        @method('DELETE')
                        </form>
                        <form id="delete-all-employees" method="POST" action="{{ route('data-pegawai.destroy-all') }}"
                            data-confirm-submit data-confirm-title="Hapus semua data pegawai?" data-confirm-text="Seluruh data pegawai akan dihapus permanen dan tidak dapat dipulihkan."
                            data-confirm-button-text="Ya, hapus semua" data-confirm-button-color="#dc2626">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                @endif
            </div>
            <div class="w-full overflow-hidden">
                <table class="w-full table-fixed text-left text-sm">
                    <colgroup>
                        @if ($canDeleteEmployees)<col style="width: 4%">@endif
                        <col style="width: 4%">
                        <col style="width: {{ $canDeleteEmployees ? '19%' : '23%' }}">
                        <col data-employee-sap-column style="width: 6%">
                        <col data-employee-position-column style="width: 15%">
                        <col style="width: 16%">
                        <col style="width: 13%">
                        <col style="width: 10%">
                        <col data-employee-actions-column style="width: 13%">
                    </colgroup>
                    <thead class="border-b border-slate-200 bg-slate-50/90 text-xs font-bold uppercase tracking-[0.08em] text-slate-500">
                        <tr>
                            @if ($canDeleteEmployees)
                                <th class="px-2 py-3.5 text-center sm:px-3">
                                    <input type="checkbox" :checked="allPageEmployeesSelected" @change="togglePageEmployees($event.target.checked)"
                                        aria-label="Pilih semua pegawai pada halaman ini"
                                        class="rounded border-slate-300 text-red-700 focus:ring-red-400">
                                </th>
                            @endif
                            <th class="px-2 py-3.5 sm:px-3">No</th>
                            <th class="px-2 py-3.5 sm:px-3">Identitas Pegawai</th>
                            <th class="whitespace-nowrap px-2 py-3.5 sm:px-3">SAP</th>
                            <th class="px-2 py-3.5 sm:px-3">Position</th>
                            <th class="px-2 py-3.5 sm:px-3">Department</th>
                            <th class="px-2 py-3.5 sm:px-3">Unit</th>
                            <th class="px-2 py-3.5 sm:px-3">Level</th>
                            <th class="whitespace-nowrap px-2 py-3.5 text-center sm:px-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($employees as $employee)
                            @php($employeeDisplayName = $employee->name ?: $employee->personal_number ?: $employee->sap)
                            @php($employeeInitials = collect(preg_split('/\s+/', trim((string) $employeeDisplayName)))->filter()->take(2)->map(fn (string $namePart): string => mb_strtoupper(mb_substr($namePart, 0, 1)))->implode(''))
                            @php($hasDistinctPersonalNumber = filled($employee->name) && filled($employee->personal_number) && mb_strtolower(trim((string) $employee->name)) !== mb_strtolower(trim((string) $employee->personal_number)))
                            @php($employeeLevel = trim((string) $employee->employee_subgroup))
                            @php($employeeLevelClass = $employeeLevelClasses->get($employeeLevel, 'border-slate-200 bg-slate-100 text-slate-700'))
                            <tr data-employee-row class="transition-colors duration-200 hover:bg-red-50/40">
                                @if ($canDeleteEmployees)
                                    <td class="px-2 py-4 text-center align-top sm:px-3">
                                        <input type="checkbox" form="bulk-delete-employees" name="employee_ids[]" value="{{ $employee->id }}"
                                            x-model="selectedEmployeeIds" aria-label="Pilih pegawai {{ $employee->personal_number ?: $employee->sap }}"
                                            class="employee-checkbox rounded border-slate-300 text-red-700 focus:ring-red-400">
                                    </td>
                                @endif
                                <td class="px-2 py-4 align-top font-semibold tabular-nums text-slate-500 sm:px-3">{{ ($employees->firstItem() ?? 1) + $loop->index }}</td>
                                <td class="px-2 py-4 align-top sm:px-3">
                                    <div class="flex flex-col items-start gap-2 lg:flex-row lg:items-center lg:gap-3">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-4 ring-white {{ $employeeAvatarClasses[$employee->id % count($employeeAvatarClasses)] }}" aria-hidden="true">{{ $employeeInitials ?: 'PG' }}</span>
                                        <div class="min-w-0">
                                            <p class="break-words font-bold text-slate-950">{{ $employeeDisplayName }}</p>
                                            @if ($hasDistinctPersonalNumber)
                                                <p class="mt-1 break-words text-xs font-medium text-slate-500">Personal Number {{ $employee->personal_number }}</p>
                                            @elseif (filled($employee->personal_number))
                                                <p class="mt-1 text-xs font-medium text-slate-500">Personal Number</p>
                                            @else
                                                <p class="mt-1 text-xs font-medium text-slate-500">Personal Number belum tersedia</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-2 py-4 align-top font-semibold tabular-nums text-slate-700 sm:px-3">
                                    {{ $employee->sap ?: '-' }}
                                </td>
                                <td class="px-2 py-4 align-top sm:px-3">
                                    <p class="break-words font-semibold text-slate-800">{{ $employee->position ?: '-' }}</p>
                                    @if ($employee->organizationPosition && $employee->organizationPosition->name !== $employee->position)
                                        <p class="mt-1 break-words text-xs text-slate-500">Master: {{ $employee->organizationPosition->name }}</p>
                                    @endif
                                </td>
                                <td class="px-2 py-4 align-top sm:px-3">
                                    <p class="break-words font-semibold text-slate-800">{{ $employee->organizationDepartment?->name ?: $employee->txt_dept ?: '-' }}</p>
                                </td>
                                <td class="px-2 py-4 align-top sm:px-3">
                                    <p class="break-words font-semibold text-slate-800">{{ $employee->unit?->name ?: $employee->txt_biro ?: '-' }}</p>
                                </td>
                                <td class="px-2 py-4 align-top sm:px-3">
                                    <span class="inline-flex max-w-full whitespace-normal break-words rounded-full border px-2.5 py-1 text-xs font-bold {{ $employeeLevelClass }}">{{ $employeeLevel !== '' ? $employeeLevel : 'Belum tersedia' }}</span>
                                </td>
                                <td class="whitespace-nowrap px-2 py-4 text-center align-top sm:px-3">
                                    <div data-employee-action-buttons class="flex flex-nowrap items-center justify-center gap-2">
                                        <a href="{{ route('employees.show', $employee->id) }}"
                                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-sky-200 bg-sky-50 text-sky-700 transition hover:border-sky-300 hover:bg-sky-100"
                                            aria-label="Lihat detail {{ $employee->personal_number ?: $employee->sap }}" title="Detail">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>
                                        @if ($canUpdateEmployees)
                                            <button type="button" onclick="document.getElementById('edit-employee-{{ $employee->id }}').showModal()"
                                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700 transition hover:border-amber-300 hover:bg-amber-100"
                                                aria-label="Edit {{ $employee->personal_number ?: $employee->sap }}" title="Edit">
                                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12.8 3.2 4 4L7 17H3v-4L12.8 3.2Z" stroke-linecap="round" stroke-linejoin="round"/><path d="m10.8 5.2 4 4" /></svg>
                                            </button>
                                        @endif
                                        @if ($canDeleteEmployees)
                                            <form method="POST" action="{{ route('data-pegawai.destroy', $employee) }}" class="inline-block shrink-0"
                                                data-confirm-submit data-confirm-title="Hapus pegawai ini?" data-confirm-text="Data yang dihapus tidak dapat dikembalikan."
                                                data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-700 transition hover:border-red-300 hover:bg-red-100"
                                                    aria-label="Hapus {{ $employee->personal_number ?: $employee->sap }}" title="Hapus">
                                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h12M8 3h4l1 3H7l1-3Zm-2 3 1 11h6l1-11M8 9v5m4-5v5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canDeleteEmployees ? 9 : 8 }}" class="px-4 py-14 text-center">
                                    <p class="font-semibold text-slate-700">Belum ada data pegawai yang sesuai.</p>
                                    <p class="mt-1 text-sm text-slate-500">Ubah kata kunci atau reset filter untuk melihat data lainnya.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                <p class="text-sm text-slate-500">
                    Menampilkan <span class="font-semibold text-slate-700">{{ $employees->firstItem() ?? 0 }}–{{ $employees->lastItem() ?? 0 }}</span>
                    dari <span class="font-semibold text-slate-700">{{ number_format($employees->total(), 0, ',', '.') }}</span> data
                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">20 per halaman</span>
                </p>
                @if ($employees->hasPages())
                    @php($currentPage = $employees->currentPage())
                    @php($lastPage = $employees->lastPage())
                    @php($pageStart = $currentPage <= 3 ? 1 : ($currentPage >= $lastPage - 2 ? max(1, $lastPage - 4) : $currentPage - 2))
                    @php($pageEnd = $currentPage <= 3 ? min($lastPage, 5) : ($currentPage >= $lastPage - 2 ? $lastPage : min($lastPage, $currentPage + 2)))
                    <nav aria-label="Pagination Data Pegawai" class="flex items-center gap-1.5">
                        @if ($employees->onFirstPage())
                            <span aria-disabled="true" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-300"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                        @else
                            <a href="{{ $employees->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-red-200 hover:text-red-700"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke-linecap="round" stroke-linejoin="round" /></svg></a>
                        @endif
                        @if ($pageStart > 1)
                            <a href="{{ $employees->url(1) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-red-200 hover:text-red-700">1</a>
                            @if ($pageStart > 2)<span class="px-1 text-sm text-slate-400">…</span>@endif
                        @endif
                        @foreach (range($pageStart, $pageEnd) as $page)
                            @if ($page === $currentPage)
                                <span aria-current="page" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-red-700 bg-red-700 px-2 text-sm font-bold text-white shadow-sm">{{ $page }}</span>
                            @else
                                <a href="{{ $employees->url($page) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-red-200 hover:text-red-700">{{ $page }}</a>
                            @endif
                        @endforeach
                        @if ($pageEnd < $lastPage)
                            @if ($pageEnd < $lastPage - 1)<span class="px-1 text-sm text-slate-400">…</span>@endif
                            <a href="{{ $employees->url($lastPage) }}" class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-slate-200 bg-white px-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-red-200 hover:text-red-700">{{ $lastPage }}</a>
                        @endif
                        @if ($employees->hasMorePages())
                            <a href="{{ $employees->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-red-200 hover:text-red-700"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg></a>
                        @else
                            <span aria-disabled="true" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-300"><svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke-linecap="round" stroke-linejoin="round" /></svg></span>
                        @endif
                    </nav>
                @endif
            </div>
        </div>

        @if ($canUpdateEmployees)
            @foreach ($employees as $employee)
            @php($isFailedEdit = old('_form') === 'edit' && (int) old('_employee_id') === $employee->id)
            @php($editValue = fn (string $field, mixed $fallback = null): mixed => $isFailedEdit ? old($field, $fallback) : $fallback)
            @php($editDirectorateId = $editValue('directorate_id', $employee->directorate_id ?? $employee->organizationDepartment?->directorate_id))
            @php($editDepartmentId = $editValue('department_id', $employee->department_id))
            @php($editUnitId = $editValue('unit_id', $employee->unit_id))
            @php($editSectionId = $editValue('section_id', $employee->section_id ?? $employee->organizationPosition?->section_id))
            @php($editPositionId = $editValue('position_id', $employee->position_id))
            <dialog id="edit-employee-{{ $employee->id }}"
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
            @endforeach
        @endif
    </div>
    </div>
