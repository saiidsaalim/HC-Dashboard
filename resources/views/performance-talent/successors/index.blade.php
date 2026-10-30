@php
    $departmentStyles = [
        ['icon' => 'users', 'class' => 'bg-blue-50 text-blue-600 ring-blue-100'],
        ['icon' => 'formasi', 'class' => 'bg-violet-50 text-violet-600 ring-violet-100'],
        ['icon' => 'chart', 'class' => 'bg-emerald-50 text-emerald-600 ring-emerald-100'],
        ['icon' => 'arrows', 'class' => 'bg-orange-50 text-orange-600 ring-orange-100'],
        ['icon' => 'document', 'class' => 'bg-rose-50 text-rose-600 ring-rose-100'],
        ['icon' => 'clipboard', 'class' => 'bg-cyan-50 text-cyan-600 ring-cyan-100'],
    ];
    $departmentNames = array_column($departments, 'name');
    $structuredDepartmentNames = array_keys($successorStructures);
    $hasCreateErrors = $errors->getBag('createSuccessor')->any();
    $hasUpdateErrors = $errors->getBag('updateSuccessor')->any();
    $activeSuccessorErrorBag = $hasUpdateErrors ? 'updateSuccessor' : 'createSuccessor';
    $initialPositionId = (string) old('target_position_id', $selectedPositionKey);
    $initialPositionData = $structurePositions[$initialPositionId] ?? null;
    $initialPosition = $initialPositionData === null ? null : [
        'key' => $initialPositionData['key'],
        'name' => $initialPositionData['name'],
        'level' => $initialPositionData['level'],
    ];
    $initialDepartmentName = $initialPositionData['department'] ?? $selectedDepartmentName ?? ($departments[0]['name'] ?? '');
    $initialDepartment = collect($departments)->firstWhere('name', $initialDepartmentName) ?? ($departments[0] ?? null);
    $oldSuccessorId = old('successor_id');
    $initialEditSuccessor = $hasUpdateErrors && is_numeric($oldSuccessorId)
        ? $successors->firstWhere('id', (int) $oldSuccessorId)
        : null;
    $initialFormAction = $initialEditSuccessor === null
        ? route('performance-talent.successors.store')
        : route('performance-talent.successors.update', $initialEditSuccessor);
    $initialForm = [
        'successor_id' => old('successor_id', ''),
        'target_position_id' => old('target_position_id', $initialPositionId),
        'employee_id' => old('employee_id', ''),
        'talent_mapping' => old('talent_mapping', ''),
        'succession_readiness' => old('succession_readiness', ''),
        'idp' => old('idp', ''),
    ];
@endphp

<div x-data="{
    search: '',
    selectedDepartment: @js($initialDepartment['name'] ?? ''),
    selectedFormationCount: @js($initialDepartment['formation_count'] ?? 0),
    departmentNames: @js($departmentNames),
    structuredDepartmentNames: @js($structuredDepartmentNames),
    successorCounts: @js($successorCounts),
    successorDialogOpen: @js($initialPosition !== null),
    selectedPosition: @js($initialPosition),
    showSuccessorForm: @js($hasCreateErrors || $hasUpdateErrors),
    formMode: @js($hasUpdateErrors ? 'edit' : 'create'),
    formAction: @js($initialFormAction),
    form: @js($initialForm),
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
    emptySuccessorForm(positionId) {
        return { successor_id: '', target_position_id: positionId, employee_id: '', talent_mapping: '', succession_readiness: '', idp: '' };
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
}" x-effect="document.body.classList.toggle('overflow-hidden', successorDialogOpen)"
    @keydown.escape.window="closeSuccessorDialog()" class="space-y-5">
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($errors->getBag('default')->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->getBag('default')->first() }}</div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-display text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">Pilih Department</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
            </div>
            <div class="relative w-full lg:max-w-xs">
                <label for="successor-department-search" class="sr-only">Cari department</label>
                <x-dashboard-icon name="users" class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input id="successor-department-search" x-model.debounce.150ms="search" type="search"
                    placeholder="Cari department..." autocomplete="off"
                    class="w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-11 pr-4 text-sm focus:border-sky-500 focus:bg-white focus:ring-sky-500">
            </div>
        </div>

        <div role="list" aria-label="Daftar department" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($departments as $department)
                @php($style = $departmentStyles[$loop->index % count($departmentStyles)])
                <button type="button" role="listitem" x-show="matches(@js($department['name']))"
                    @click="selectDepartment(@js($department['name']), @js($department['formation_count']))"
                    :aria-pressed="selectedDepartment === @js($department['name'])"
                    :class="selectedDepartment === @js($department['name']) ? 'border-blue-500 bg-blue-50/70 ring-1 ring-blue-500' : 'border-slate-200 bg-white hover:border-blue-300'"
                    class="flex min-h-24 items-center gap-3 rounded-xl border p-3.5 text-left transition sm:gap-4 sm:p-4">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full ring-1 {{ $style['class'] }}">
                        <x-dashboard-icon :name="$style['icon']" class="h-7 w-7" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-bold text-slate-950">{{ $department['number'] }}</span>
                        <span class="mt-1 block text-sm font-semibold text-slate-900">{{ $department['name'] }}</span>
                        <span class="mt-1 block text-xs font-medium text-slate-500">{{ $department['formation_count'] }} Formasi</span>
                    </span>
                </button>
            @endforeach
        </div>

        <div x-cloak x-show="!hasMatches" class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
            <p class="font-semibold text-slate-700">Department tidak ditemukan</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div>
                <h2 class="font-display text-lg font-bold text-slate-950">Struktur Organisasi - <span x-text="selectedDepartment"></span></h2>
                <p class="mt-0.5 text-sm text-slate-500">Data langsung dari Master Organization.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('organization.update')
                    <a href="{{ route('organization.positions.index') }}" class="ui-button ui-button-secondary">Kelola Master Organization</a>
                @endcan
                <span class="rounded-lg bg-blue-50 px-3 py-2 text-xs font-bold text-blue-700">Total Formasi: <span x-text="selectedFormationCount"></span></span>
            </div>
        </div>

        @foreach ($successorStructures as $departmentName => $structureRoots)
            <div x-cloak x-show="selectedDepartment === @js($departmentName)" class="p-4 sm:p-5">
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-slate-50/70" tabindex="0" aria-label="Bagan struktur organisasi {{ $departmentName }}">
                    <div class="successor-org-chart">
                        <ul>
                            @foreach ($structureRoots as $structureRoot)
                                @include('performance-talent.successors._tree-node', ['position' => $structureRoot])
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach

        <div x-cloak x-show="!structuredDepartmentNames.includes(selectedDepartment)" class="p-4 sm:p-5">
            <x-ui.empty-state title="Struktur organisasi belum tersedia" description="Tambahkan Unit, Section, dan Position aktif pada Master Organization." />
        </div>
    </section>

    <div x-cloak x-show="successorDialogOpen" class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6" role="presentation">
        <button type="button" @click="closeSuccessorDialog()" aria-label="Tutup daftar successor" class="absolute inset-0 h-full w-full bg-slate-950/65 backdrop-blur-sm"></button>
        <section x-show="successorDialogOpen" class="relative z-[81] flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
            role="dialog" aria-modal="true" aria-labelledby="successor-dialog-title">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-blue-700" x-text="selectedPosition?.level"></p>
                    <h3 id="successor-dialog-title" class="mt-1 font-display text-xl font-bold text-slate-950">Successor - <span x-text="selectedPosition?.name"></span></h3>
                    <p class="mt-1 text-sm text-slate-500"><span x-text="selectedSuccessorCount"></span> kandidat tersimpan.</p>
                </div>
                <button type="button" @click="closeSuccessorDialog()" class="ui-button ui-button-secondary">Tutup</button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
                <div class="flex items-center justify-between gap-3">
                    <h4 class="font-display text-lg font-bold text-slate-950">Daftar Kandidat</h4>
                    @can('create', \App\Models\Successor::class)
                        <button type="button" @click="openCreateSuccessor()" class="ui-button ui-button-primary">Tambah Successor</button>
                    @endcan
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($successors as $successor)
                        @php($successorInitial = mb_strtoupper(mb_substr(trim($successor->name), 0, 1)))
                        <article x-show="selectedPosition?.key === @js((string) $successor->target_position_id)" class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center gap-3">
                                @if ($successor->photo_path)
                                    <img src="{{ Storage::disk('public')->url($successor->photo_path) }}" alt="Foto {{ $successor->name }}" class="h-16 w-16 rounded-2xl object-cover ring-1 ring-slate-200">
                                @else
                                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-100 text-xl font-bold text-blue-700">{{ $successorInitial }}</span>
                                @endif
                                <div class="min-w-0"><h5 class="font-display text-lg font-bold text-slate-950">{{ $successor->name }}</h5><p class="text-sm text-slate-600">{{ $successor->position }}</p></div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">{{ $successor->talent_mapping }}</span>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $successor->succession_readiness }}</span>
                            </div>
                            <p class="mt-3 whitespace-pre-line rounded-lg bg-slate-50 px-3 py-2.5 text-sm text-slate-700">{{ $successor->idp }}</p>
                            <dl class="mt-3 space-y-2 text-sm text-slate-600">
                                <div><dt class="font-bold">Pendidikan</dt><dd>{{ $successor->education }}</dd></div>
                                <div><dt class="font-bold">Masa Kerja</dt><dd>{{ $successor->tenure }}</dd></div>
                                <div><dt class="font-bold">Direktorat</dt><dd>{{ $successor->directorate }}</dd></div>
                            </dl>
                            <div class="mt-auto flex justify-end gap-3 pt-4">
                                @can('update', $successor)
                                    <button type="button" @click="openEditSuccessor(@js([
                                        'successor_id' => (string) $successor->id,
                                        'target_position_id' => (string) $successor->target_position_id,
                                        'employee_id' => (string) $successor->employee_id,
                                        'talent_mapping' => $successor->talent_mapping,
                                        'succession_readiness' => $successor->succession_readiness,
                                        'idp' => $successor->idp,
                                        'updateUrl' => route('performance-talent.successors.update', $successor),
                                    ]))" class="text-sm font-bold text-sky-700">Edit</button>
                                @endcan
                                @can('delete', $successor)
                                    <form method="POST" action="{{ route('performance-talent.successors.destroy', $successor) }}" data-confirm-submit data-confirm-title="Hapus Successor ini?" data-confirm-text="Data kandidat {{ $successor->name }} akan dihapus permanen.">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-sm font-bold text-red-600">Hapus</button>
                                    </form>
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </div>

                <div x-show="selectedSuccessorCount === 0 && !showSuccessorForm" class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-10 text-center">
                    <h4 class="font-display text-lg font-bold text-slate-800">Belum ada successor</h4>
                    <p class="mt-1 text-sm text-slate-500">Tambahkan kandidat dari Data Pegawai.</p>
                </div>

                @if (auth()->user()->hasPermission('successors.create') || auth()->user()->hasPermission('successors.update'))
                    <div x-cloak x-show="showSuccessorForm">@include('performance-talent.successors._form')</div>
                @endif
            </div>
        </section>
    </div>
</div>
