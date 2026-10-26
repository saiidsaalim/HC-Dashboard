@php
    $departmentStyles = [
        ['icon' => 'users', 'class' => 'bg-blue-50 text-blue-600 ring-blue-100'],
        ['icon' => 'formasi', 'class' => 'bg-violet-50 text-violet-600 ring-violet-100'],
        ['icon' => 'chart', 'class' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
        ['icon' => 'arrows', 'class' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ['icon' => 'formasi', 'class' => 'bg-rose-50 text-rose-600 ring-rose-100'],
        ['icon' => 'chart', 'class' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
        ['icon' => 'users', 'class' => 'bg-amber-50 text-amber-600 ring-amber-100'],
        ['icon' => 'document', 'class' => 'bg-fuchsia-50 text-fuchsia-600 ring-fuchsia-100'],
        ['icon' => 'users', 'class' => 'bg-indigo-50 text-indigo-600 ring-indigo-100'],
        ['icon' => 'clipboard', 'class' => 'bg-sky-50 text-sky-600 ring-sky-100'],
        ['icon' => 'formasi', 'class' => 'bg-green-50 text-green-600 ring-green-100'],
        ['icon' => 'document', 'class' => 'bg-blue-50 text-blue-600 ring-blue-100'],
    ];

    $departmentNames = array_column($departments, 'name');
    $structuredDepartmentNames = array_keys($successorStructures);
    $hasCreateErrors = $errors->getBag('createSuccessor')->any();
    $hasUpdateErrors = $errors->getBag('updateSuccessor')->any();
    $hasCreateStructureErrors = $errors->getBag('createStructurePosition')->any();
    $hasUpdateStructureErrors = $errors->getBag('updateStructurePosition')->any();
    $hasStructureErrors = $hasCreateStructureErrors || $hasUpdateStructureErrors || $errors->getBag('structurePosition')->any();
    $activeSuccessorErrorBag = $hasUpdateErrors ? 'updateSuccessor' : 'createSuccessor';
    $activeStructureErrorBag = $hasUpdateStructureErrors ? 'updateStructurePosition' : 'createStructurePosition';
    $initialPositionKey = old('structure_position_key', $selectedPositionKey);
    $initialPositionData = $structurePositions[$initialPositionKey] ?? null;
    $initialPosition = $initialPositionData === null ? null : [
        'key' => $initialPositionData['key'],
        'name' => $initialPositionData['name'],
        'level' => $initialPositionData['level'],
    ];
    $initialDepartmentName = $initialPositionData['department'] ?? $selectedDepartmentName ?? $departments[0]['name'];
    $initialDepartment = collect($departments)->firstWhere('name', $initialDepartmentName) ?? $departments[0];
    $oldSuccessorId = old('successor_id');
    $initialEditSuccessor = $hasUpdateErrors && is_numeric($oldSuccessorId)
        ? $successors->firstWhere('id', (int) $oldSuccessorId)
        : null;
    $initialFormMode = $hasUpdateErrors ? 'edit' : 'create';
    $initialFormAction = $initialEditSuccessor === null
        ? route('performance-talent.successors.store')
        : route('performance-talent.successors.update', $initialEditSuccessor);
    $initialForm = [
        'successor_id' => old('successor_id', ''),
        'structure_position_key' => old('structure_position_key', $initialPositionKey ?? ''),
        'name' => old('name', ''),
        'position' => old('position', ''),
        'talent_mapping' => old('talent_mapping', ''),
        'succession_readiness' => old('succession_readiness', ''),
        'idp' => old('idp', ''),
        'education' => old('education', ''),
        'tenure' => old('tenure', ''),
        'directorate' => old('directorate', ''),
    ];
    $oldStructurePositionId = old('structure_position_id');
    $initialEditStructurePosition = $hasUpdateStructureErrors && is_numeric($oldStructurePositionId)
        ? $structurePositionRecords->firstWhere('id', (int) $oldStructurePositionId)
        : null;
    $initialStructureFormMode = $hasUpdateStructureErrors ? 'edit' : 'create';
    $initialStructureFormAction = $initialEditStructurePosition === null
        ? route('performance-talent.successor-structure.positions.store')
        : $initialEditStructurePosition['update_url'];
    $initialStructureForm = [
        'structure_position_id' => old('structure_position_id', ''),
        'department_id' => old('department_id', $initialDepartment['id']),
        'parent_id' => old('parent_id', ''),
        'level' => old('level', ''),
        'name' => old('name', ''),
        'formation_count' => old('formation_count', 1),
        'sort_order' => old('sort_order', 1),
        'align_with_grandchildren' => filter_var(old('align_with_grandchildren', false), FILTER_VALIDATE_BOOLEAN),
    ];
@endphp

<div
    x-data="{
        search: '',
        selectedDepartment: @js($initialDepartment['name']),
        selectedFormationCount: @js($initialDepartment['formation_count']),
        departmentNames: @js($departmentNames),
        structuredDepartmentNames: @js($structuredDepartmentNames),
        successorCounts: @js($successorCounts),
        departments: @js($departments),
        structurePositions: @js($structurePositionRecords),
        successorDialogOpen: @js($initialPosition !== null),
        selectedPosition: @js($initialPosition),
        showSuccessorForm: @js($hasCreateErrors || $hasUpdateErrors),
        formMode: @js($initialFormMode),
        formAction: @js($initialFormAction),
        form: @js($initialForm),
        structureDialogOpen: @js($structureEditorOpen || $hasStructureErrors),
        showStructureForm: @js($hasCreateStructureErrors || $hasUpdateStructureErrors),
        structureFormMode: @js($initialStructureFormMode),
        structureFormAction: @js($initialStructureFormAction),
        structureForm: @js($initialStructureForm),
        matches(name) {
            return name.toLocaleLowerCase('id-ID').includes(this.search.trim().toLocaleLowerCase('id-ID'));
        },
        selectDepartment(name, formationCount) {
            this.selectedDepartment = name;
            this.selectedFormationCount = formationCount;
        },
        get hasMatches() {
            return this.departmentNames.some((name) => this.matches(name));
        },
        get selectedSuccessorCount() {
            return this.selectedPosition ? (this.successorCounts[this.selectedPosition.key] ?? 0) : 0;
        },
        get selectedDepartmentRecord() {
            return this.departments.find((department) => department.name === this.selectedDepartment);
        },
        get selectedStructurePositions() {
            return this.structurePositions.filter((position) => position.department_name === this.selectedDepartment);
        },
        get structureParentOptions() {
            return this.selectedStructurePositions.filter((position) => String(position.id) !== String(this.structureForm.structure_position_id));
        },
        emptySuccessorForm(positionKey) {
            return {
                successor_id: '',
                structure_position_key: positionKey,
                name: '',
                position: '',
                talent_mapping: '',
                succession_readiness: '',
                idp: '',
                education: '',
                tenure: '',
                directorate: '',
            };
        },
        openSuccessorDialog(position) {
            this.selectedPosition = position;
            this.successorDialogOpen = true;
            this.showSuccessorForm = false;
        },
        closeSuccessorDialog() {
            this.successorDialogOpen = false;
            this.showSuccessorForm = false;
        },
        openCreateSuccessor() {
            this.formMode = 'create';
            this.formAction = @js(route('performance-talent.successors.store'));
            this.form = this.emptySuccessorForm(this.selectedPosition.key);
            this.$refs.successorPhoto.value = '';
            this.showSuccessorForm = true;
        },
        openEditSuccessor(successor) {
            this.formMode = 'edit';
            this.formAction = successor.updateUrl;
            this.form = successor;
            this.$refs.successorPhoto.value = '';
            this.showSuccessorForm = true;
        },
        emptyStructureForm(parentId = '') {
            return {
                structure_position_id: '',
                department_id: this.selectedDepartmentRecord.id,
                parent_id: parentId === '' ? '' : String(parentId),
                level: '',
                name: '',
                formation_count: 1,
                sort_order: this.selectedStructurePositions.length + 1,
                align_with_grandchildren: false,
            };
        },
        openStructureDialog() {
            this.structureDialogOpen = true;
            this.showStructureForm = false;
        },
        closeStructureDialog() {
            this.structureDialogOpen = false;
            this.showStructureForm = false;
        },
        openCreateStructurePosition(parentId = '') {
            this.structureFormMode = 'create';
            this.structureFormAction = @js(route('performance-talent.successor-structure.positions.store'));
            this.structureForm = this.emptyStructureForm(parentId);
            this.showStructureForm = true;
            this.$nextTick(() => this.$refs.structureFormElement?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
        openEditStructurePosition(position) {
            this.structureFormMode = 'edit';
            this.structureFormAction = position.update_url;
            this.structureForm = {
                ...position,
                structure_position_id: String(position.id),
                parent_id: position.parent_id === null ? '' : String(position.parent_id),
            };
            this.showStructureForm = true;
            this.$nextTick(() => this.$refs.structureFormElement?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
    }"
    x-effect="document.body.classList.toggle('overflow-hidden', successorDialogOpen || structureDialogOpen)"
    @keydown.escape.window="successorDialogOpen ? closeSuccessorDialog() : closeStructureDialog()"
    class="space-y-5"
>
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->getBag('default')->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->getBag('default')->first() }}
        </div>
    @endif
    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-display text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Pilih Department</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
            </div>

            <div class="relative w-full lg:max-w-xs">
                <label for="successor-department-search" class="sr-only">Cari department</label>
                <x-dashboard-icon name="users"
                    class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input id="successor-department-search" x-model.debounce.150ms="search" @keydown.escape="search = ''"
                    type="search" placeholder="Cari department..." autocomplete="off"
                    class="w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-11 pr-4 text-sm text-slate-800 placeholder:text-slate-400 focus:border-sky-500 focus:bg-white focus:ring-sky-500" />
            </div>
        </div>

        <div role="list" aria-label="Daftar department"
            class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($departments as $department)
                @php($style = $departmentStyles[$loop->index])
                <button type="button" role="listitem"
                    x-show="matches(@js($department['name']))"
                    x-transition.opacity.duration.150ms
                    @click="selectDepartment(@js($department['name']), @js($department['formation_count']))"
                    :aria-pressed="selectedDepartment === @js($department['name'])"
                    :class="selectedDepartment === @js($department['name'])
                        ? 'border-blue-500 bg-blue-50/70 ring-1 ring-blue-500 shadow-sm'
                        : 'border-slate-200 bg-white hover:border-blue-300 hover:bg-slate-50'"
                    class="group flex min-h-24 items-center gap-3 rounded-xl border p-3.5 text-left transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:gap-4 sm:p-4">
                    <span
                        class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full ring-1 {{ $style['class'] }}">
                        <x-dashboard-icon :name="$style['icon']" class="h-7 w-7" />
                    </span>

                    <span class="min-w-0">
                        <span class="block text-sm font-bold leading-none text-slate-950">{{ $department['number'] }}</span>
                        <span class="mt-1.5 block text-sm font-semibold leading-5 text-slate-900">
                            {{ $department['name'] }}
                        </span>
                        <span class="mt-1 block text-xs font-medium text-slate-500">
                            {{ $department['formation_count'] }} Formasi
                        </span>
                    </span>
                </button>
            @endforeach
        </div>

        <div x-cloak x-show="!hasMatches"
            class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
            <p class="font-semibold text-slate-700">Department tidak ditemukan</p>
            <p class="mt-1 text-sm text-slate-500">Coba gunakan kata kunci pencarian yang berbeda.</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm">
                    <x-dashboard-icon name="users" class="h-6 w-6" />
                </span>
                <div class="min-w-0">
                    <h2 class="font-display text-lg font-bold text-slate-950">
                        Struktur Organisasi - <span x-text="selectedDepartment"></span>
                    </h2>
                    <p class="mt-0.5 text-sm text-slate-500">Menampilkan struktur jabatan dan formasi pada department terpilih.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('updateStructure', \App\Models\Successor::class)
                    <button type="button" @click="openStructureDialog()" class="ui-button ui-button-secondary">
                        Edit Struktur
                    </button>
                @endcan
                <span class="w-fit rounded-lg bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700">
                    Total Formasi: <span x-text="selectedFormationCount"></span>
                </span>
            </div>
        </div>

        @foreach ($successorStructures as $departmentName => $structure)
            <div x-cloak x-show="selectedDepartment === @js($departmentName)" class="p-4 sm:p-5">
                <p class="mb-3 text-xs font-medium text-slate-400 lg:hidden">Geser ke samping untuk melihat seluruh struktur.</p>
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-50/70" tabindex="0"
                    aria-label="Bagan struktur organisasi {{ $departmentName }}">
                    <div class="successor-org-chart">
                        <ul>
                            @include('performance-talent.successors._tree-node', ['position' => $structure])
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach

        <div x-cloak x-show="!structuredDepartmentNames.includes(selectedDepartment)" class="p-4 sm:p-5">
            <div class="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
                    <x-dashboard-icon name="formasi" class="h-7 w-7" />
                </span>
                <h3 class="mt-4 font-display text-lg font-bold text-slate-800">Struktur organisasi belum tersedia</h3>
                <p class="mt-1 max-w-lg text-sm leading-6 text-slate-500">
                    Data jabatan dan formasi untuk <span class="font-semibold text-slate-700" x-text="selectedDepartment"></span>
                    belum ditambahkan.
                </p>
            </div>
        </div>
    </section>

    @can('updateStructure', \App\Models\Successor::class)
        <div x-cloak x-show="structureDialogOpen" class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6"
            role="presentation">
            <button type="button" @click="closeStructureDialog()" aria-label="Tutup editor struktur"
                class="absolute inset-0 h-full w-full bg-slate-950/65 backdrop-blur-sm"></button>

            <section x-show="structureDialogOpen"
                x-transition:enter="transition duration-200 ease-out"
                x-transition:enter-start="translate-y-3 scale-[0.98] opacity-0"
                x-transition:enter-end="translate-y-0 scale-100 opacity-100"
                x-transition:leave="transition duration-150 ease-in"
                x-transition:leave-start="translate-y-0 scale-100 opacity-100"
                x-transition:leave-end="translate-y-3 scale-[0.98] opacity-0"
                class="relative z-[81] flex max-h-[calc(100dvh-1.5rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:max-h-[calc(100dvh-3rem)]"
                role="dialog" aria-modal="true" aria-labelledby="structure-dialog-title">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-6 sm:py-5">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700">Editor Struktur</p>
                        <h3 id="structure-dialog-title" class="mt-1 font-display text-xl font-bold text-slate-950">
                            <span x-text="selectedDepartment"></span>
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Atur jabatan, formasi, urutan, dan hubungan atasannya.</p>
                    </div>
                    <button type="button" @click="closeStructureDialog()" aria-label="Tutup popup editor struktur"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
                    @if (session('status'))
                        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->getBag('structurePosition')->any())
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ $errors->getBag('structurePosition')->first() }}
                        </div>
                    @endif

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h4 class="font-display text-lg font-bold text-slate-950">Daftar Posisi</h4>
                            <p class="mt-0.5 text-sm text-slate-500">Kunci internal tetap dipertahankan saat posisi diedit.</p>
                        </div>
                        <button type="button" @click="openCreateStructurePosition()" class="ui-button ui-button-primary">
                            Tambah Posisi
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <template x-for="position in selectedStructurePositions" :key="position.id">
                            <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700"
                                                x-text="position.level"></span>
                                            <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">
                                                <span x-text="position.formation_count"></span> Formasi
                                            </span>
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">
                                                Urutan <span x-text="position.sort_order"></span>
                                            </span>
                                            <span x-show="position.children_count > 0"
                                                class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">
                                                <span x-text="position.children_count"></span> Bawahan
                                            </span>
                                            <span x-show="position.successor_count > 0"
                                                class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">
                                                <span x-text="position.successor_count"></span> Successor
                                            </span>
                                        </div>
                                        <h5 class="mt-2 font-display text-base font-bold text-slate-950" x-text="position.name"></h5>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Atasan: <span class="font-semibold text-slate-700" x-text="position.parent_name ?? 'Posisi paling atas'"></span>
                                        </p>
                                    </div>

                                    <div class="flex shrink-0 flex-wrap items-center gap-3">
                                        <button type="button" @click="openCreateStructurePosition(position.id)"
                                            class="text-sm font-bold text-emerald-700 hover:text-emerald-900">Tambah Bawahan</button>
                                        <button type="button" @click="openEditStructurePosition(position)"
                                            class="text-sm font-bold text-sky-700 hover:text-sky-900">Edit</button>
                                        <form method="POST" :action="position.destroy_url" data-confirm-submit
                                            data-confirm-title="Hapus posisi struktur?"
                                            :data-confirm-text="`Posisi ${position.name} akan dihapus permanen.`"
                                            data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                :disabled="position.children_count > 0 || position.successor_count > 0"
                                                :title="position.children_count > 0
                                                    ? 'Pindahkan atau hapus posisi bawahan terlebih dahulu.'
                                                    : (position.successor_count > 0 ? 'Pindahkan atau hapus successor terlebih dahulu.' : 'Hapus posisi')"
                                                class="text-sm font-bold text-red-600 hover:text-red-800 disabled:cursor-not-allowed disabled:text-slate-300">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div x-show="selectedStructurePositions.length === 0 && !showStructureForm"
                        class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                        <h4 class="font-display text-lg font-bold text-slate-800">Struktur belum memiliki posisi</h4>
                        <p class="mt-1 text-sm text-slate-500">Tambahkan posisi paling atas untuk mulai membentuk struktur.</p>
                    </div>

                    <div x-cloak x-show="showStructureForm">
                        @include('performance-talent.successors._structure-form')
                    </div>
                </div>

                <div class="shrink-0 border-t border-slate-200 bg-slate-50 px-4 py-3 text-right sm:px-6">
                    <button type="button" @click="closeStructureDialog()" class="ui-button ui-button-secondary">Tutup</button>
                </div>
            </section>
        </div>
    @endcan

    <div x-cloak x-show="successorDialogOpen" class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6"
        role="presentation">
        <button type="button" @click="closeSuccessorDialog()" aria-label="Tutup daftar successor"
            class="absolute inset-0 h-full w-full bg-slate-950/65 backdrop-blur-sm"></button>

        <section x-show="successorDialogOpen"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-3 scale-[0.98] opacity-0"
            x-transition:enter-end="translate-y-0 scale-100 opacity-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="translate-y-0 scale-100 opacity-100"
            x-transition:leave-end="translate-y-3 scale-[0.98] opacity-0"
            class="relative z-[81] flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:max-h-[calc(100dvh-3rem)]"
            role="dialog" aria-modal="true" aria-labelledby="successor-dialog-title">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-6 sm:py-5">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700" x-text="selectedPosition?.level"></p>
                    <h3 id="successor-dialog-title" class="mt-1 font-display text-xl font-bold text-slate-950">
                        Successor - <span x-text="selectedPosition?.name"></span>
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        <span x-text="selectedSuccessorCount"></span> kandidat successor tersimpan untuk posisi ini.
                    </p>
                </div>
                <button type="button" @click="closeSuccessorDialog()" aria-label="Tutup popup successor"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-800">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M5 5l10 10M15 5 5 15" stroke-linecap="round" />
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h4 class="font-display text-lg font-bold text-slate-950">Daftar Kandidat</h4>
                        <p class="mt-0.5 text-sm text-slate-500">Klik profil singkat untuk melihat detail kandidat.</p>
                    </div>
                    @can('create', \App\Models\Successor::class)
                        <button type="button" @click="openCreateSuccessor()" class="ui-button ui-button-primary">
                            Tambah Successor
                        </button>
                    @endcan
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($successors as $successor)
                        @php($successorInitial = mb_strtoupper(mb_substr(trim($successor->name), 0, 1)))
                        <article x-show="selectedPosition?.key === @js($successor->structure_position_key)"
                            class="flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                            <div class="flex items-center gap-3">
                                @if ($successor->photo_path)
                                    <img src="{{ Storage::disk('public')->url($successor->photo_path) }}"
                                        alt="Foto {{ $successor->name }}"
                                        class="h-16 w-16 shrink-0 rounded-2xl object-cover ring-1 ring-slate-200">
                                @else
                                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-100 to-indigo-100 text-xl font-bold text-blue-700 ring-1 ring-blue-200"
                                        aria-hidden="true">{{ $successorInitial }}</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <h5 class="font-display text-lg font-bold text-slate-950">{{ $successor->name }}</h5>
                                    <p class="mt-0.5 text-sm font-medium text-slate-600">{{ $successor->position }}</p>
                                </div>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">
                                    {{ $successor->talent_mapping }}
                                </span>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">
                                    {{ $successor->succession_readiness }}
                                </span>
                            </div>

                            <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2.5">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">IDP</p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $successor->idp }}</p>
                            </div>

                            <details class="mt-3 rounded-lg border border-slate-200 bg-white">
                                <summary class="cursor-pointer select-none px-3 py-2.5 text-sm font-bold text-slate-700">
                                    Profil Singkat
                                </summary>
                                <dl class="grid grid-cols-1 gap-3 border-t border-slate-200 px-3 py-3 text-sm">
                                    <div>
                                        <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Pendidikan</dt>
                                        <dd class="mt-1 font-semibold text-slate-700">{{ $successor->education }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Masa Kerja</dt>
                                        <dd class="mt-1 font-semibold text-slate-700">{{ $successor->tenure }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Direktorat</dt>
                                        <dd class="mt-1 font-semibold text-slate-700">{{ $successor->directorate }}</dd>
                                    </div>
                                </dl>
                            </details>

                            <div class="mt-auto flex items-center justify-end gap-3 pt-4">
                                @can('update', $successor)
                                    <button type="button"
                                        @click="openEditSuccessor(@js([
                                            'successor_id' => (string) $successor->id,
                                            'structure_position_key' => $successor->structure_position_key,
                                            'name' => $successor->name,
                                            'position' => $successor->position,
                                            'talent_mapping' => $successor->talent_mapping,
                                            'succession_readiness' => $successor->succession_readiness,
                                            'idp' => $successor->idp,
                                            'education' => $successor->education,
                                            'tenure' => $successor->tenure,
                                            'directorate' => $successor->directorate,
                                            'updateUrl' => route('performance-talent.successors.update', $successor),
                                        ]))"
                                        class="text-sm font-bold text-sky-700 hover:text-sky-900">Edit</button>
                                @endcan
                                @can('delete', $successor)
                                    <form method="POST" action="{{ route('performance-talent.successors.destroy', $successor) }}"
                                        data-confirm-submit data-confirm-title="Hapus Successor ini?"
                                        data-confirm-text="Data kandidat {{ $successor->name }} akan dihapus permanen."
                                        data-confirm-button-text="Ya, hapus" data-confirm-button-color="#dc2626">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-bold text-red-600 hover:text-red-800">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </div>

                <div x-show="selectedSuccessorCount === 0 && !showSuccessorForm"
                    class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
                        <x-dashboard-icon name="users" class="h-6 w-6" />
                    </span>
                    <h4 class="mt-3 font-display text-lg font-bold text-slate-800">Belum ada successor</h4>
                    <p class="mt-1 text-sm text-slate-500">Tambahkan kandidat successor untuk posisi struktur ini.</p>
                </div>

                @if (auth()->user()->hasPermission('successors.create') || auth()->user()->hasPermission('successors.update'))
                    <div x-cloak x-show="showSuccessorForm">
                        @include('performance-talent.successors._form')
                    </div>
                @endif
            </div>

            <div class="shrink-0 border-t border-slate-200 bg-slate-50 px-4 py-3 text-right sm:px-6">
                <button type="button" @click="closeSuccessorDialog()" class="ui-button ui-button-secondary">Tutup</button>
            </div>
        </section>
    </div>
</div>
