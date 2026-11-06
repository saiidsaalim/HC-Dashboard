@extends('layouts.app')

@section('page-title', 'Critical Position')

@section('content')
    @php
        $hasCreateErrors = $errors->getBag('createCriticalPosition')->any();
        $hasUpdateErrors = $errors->getBag('updateCriticalPosition')->any();
        $oldCriticalPositionId = old('critical_position_id');
        $hasCriticalPositionOldInput = old('position_id') !== null;
        $isOldUpdateInput = is_numeric($oldCriticalPositionId);
        $createAction = route('performance-talent.critical-positions.store');
        $initialPanel = $hasCreateErrors || $hasUpdateErrors || $hasCriticalPositionOldInput ? 'form' : null;
        $initialFormMode = $hasUpdateErrors || $isOldUpdateInput ? 'edit' : 'create';
        $initialFormAction = $initialFormMode === 'edit' && $isOldUpdateInput
            ? route('performance-talent.critical-positions.update', (int) $oldCriticalPositionId)
            : $createAction;
        $emptyCreateForm = [
            'id' => '',
            'position_id' => '',
            'criticality' => '',
            'assessment_reason' => '',
            'assessed_at' => '',
            'review_due_at' => '',
            'notes' => '',
        ];
        $initialFormData = [
            'id' => (string) $oldCriticalPositionId,
            'position_id' => (string) old('position_id', ''),
            'criticality' => old('criticality', ''),
            'assessment_reason' => old('assessment_reason', ''),
            'assessed_at' => old('assessed_at', $emptyCreateForm['assessed_at']),
            'review_due_at' => old('review_due_at', ''),
            'notes' => old('notes', ''),
        ];
        $activeErrorBag = $hasUpdateErrors ? 'updateCriticalPosition' : 'createCriticalPosition';
        $canViewSuccessors = auth()->user()->can('viewAny', \App\Models\Successor::class);
        $canCreateSuccessors = auth()->user()->can('create', \App\Models\Successor::class);
        $employeeDisplayName = static function (\App\Models\Employee $employee): string {
            $personalNumber = trim((string) $employee->personal_number);

            if ($personalNumber !== '') {
                return $personalNumber;
            }

            return 'Nama belum tersedia';
        };
        $employeeInitials = static function (string $name): string {
            return collect(preg_split('/\s+/', trim($name)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('') ?: 'NA';
        };
    @endphp

    <div data-initial-panel="{{ $initialPanel }}" data-initial-form-mode="{{ $initialFormMode }}" data-initial-form-action="{{ $initialFormAction }}" x-data="{
        panel: @js($initialPanel),
        formMode: @js($initialFormMode),
        formAction: @js($initialFormAction),
        formData: @js($initialFormData),
        createAction: @js($createAction),
        emptyCreateForm: @js($emptyCreateForm),
        formSequence: 0,
        detail: null,
        actionMenu: null,
        actionMenuTrigger: null,
        lastFormTrigger: null,
        init() {
            if (this.panel === 'form') this.focusPositionSearch();
        },
        openCreate(trigger) {
            this.lastFormTrigger = trigger;
            this.formMode = 'create';
            this.formAction = this.createAction;
            this.formData = { ...this.emptyCreateForm };
            this.formSequence++;
            this.panel = 'form';
            this.focusPositionSearch();
        },
        openEdit(criticalPosition, trigger) {
            this.closeActionMenu(false);
            this.lastFormTrigger = trigger;
            this.formMode = 'edit';
            this.formAction = criticalPosition.updateUrl;
            this.formData = { ...criticalPosition };
            this.formSequence++;
            this.panel = 'form';
            this.focusPositionSearch();
        },
        openDetail(criticalPosition) {
            this.closeActionMenu(false);
            this.detail = criticalPosition;
            this.panel = 'detail';
        },
        openActionMenu(criticalPosition, trigger) {
            const rectangle = trigger.getBoundingClientRect();
            const menuWidth = 192;
            const menuHeight = 160;
            const left = Math.min(window.innerWidth - menuWidth - 12, Math.max(12, rectangle.right - menuWidth));
            const openAbove = rectangle.bottom + menuHeight > window.innerHeight - 12;

            this.actionMenuTrigger = trigger;
            this.actionMenu = {
                ...criticalPosition,
                menuLeft: left,
                menuTop: openAbove ? Math.max(12, rectangle.top - menuHeight - 8) : rectangle.bottom + 8,
            };
            this.$nextTick(() => document.querySelector('[data-critical-position-action-menu] [role=menuitem]')?.focus());
        },
        closeActionMenu(returnFocus = true) {
            this.actionMenu = null;
            if (returnFocus) this.$nextTick(() => this.actionMenuTrigger?.focus());
        },
        moveActionMenuFocus(event, direction) {
            const items = [...event.currentTarget.querySelectorAll('[role=menuitem]')];
            const currentIndex = items.indexOf(document.activeElement);
            const nextIndex = (currentIndex + direction + items.length) % items.length;
            items[nextIndex]?.focus();
        },
        closePanel() { this.panel = null; },
        closeForm() {
            this.panel = null;
            this.$nextTick(() => this.lastFormTrigger?.focus());
        },
        focusPositionSearch() {
            this.$nextTick(() => setTimeout(() => this.$refs.formDialog?.querySelector('[data-critical-position-search]')?.focus(), 50));
        },
        formFocusables() {
            if (!this.$refs.formDialog) return [];
            return [...this.$refs.formDialog.querySelectorAll('a, button, input:not([type=hidden]), textarea, select, [tabindex]:not([tabindex=\'-1\'])')]
                .filter((element) => !element.disabled && element.offsetParent !== null);
        },
        trapFormFocus(event) {
            const focusables = this.formFocusables();
            if (focusables.length === 0) return;
            const first = focusables[0];
            const last = focusables.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }" x-effect="document.body.classList.toggle('overflow-hidden', panel !== null)"
        @scroll.window="closeActionMenu(false)"
        @resize.window="closeActionMenu(false)"
        @keydown.escape.window="actionMenu ? closeActionMenu() : (panel === 'form' ? closeForm() : closePanel())">
        <div data-critical-position-dashboard class="space-y-5">
            <section data-critical-position-summary class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan Critical Position">
                @foreach ([
                    ['label' => 'Total Critical Position', 'value' => $summary['total'], 'description' => 'Seluruh posisi terpantau', 'classes' => 'border-sky-200 bg-sky-50/80 text-sky-700', 'icon' => 'briefcase'],
                    ['label' => 'High Criticality', 'value' => $summary['high'], 'description' => 'Posisi dengan criticality tinggi', 'classes' => 'border-rose-200 bg-rose-50/80 text-rose-700', 'icon' => 'warning'],
                    ['label' => 'Vacant', 'value' => $summary['vacant'], 'description' => 'Posisi belum memiliki incumbent', 'classes' => 'border-amber-200 bg-amber-50/80 text-amber-700', 'icon' => 'chair'],
                    ['label' => 'Belum Memiliki Successor', 'value' => $summary['without_successor'], 'description' => 'Posisi tanpa kandidat successor', 'classes' => 'border-violet-200 bg-violet-50/80 text-violet-700', 'icon' => 'users'],
                ] as $card)
                    <article data-critical-position-kpi class="flex min-h-28 items-center gap-4 rounded-2xl border p-4 shadow-sm {{ $card['classes'] }}">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/70">
                            @if ($card['icon'] === 'briefcase')
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2" /></svg>
                            @elseif ($card['icon'] === 'warning')
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10.3 3.7 2.6 18a2 2 0 0 0 1.8 3h15.2a2 2 0 0 0 1.8-3L13.7 3.7a2 2 0 0 0-3.4 0Z" /><path d="M12 9v5m0 3h.01" stroke-linecap="round" /></svg>
                            @elseif ($card['icon'] === 'chair')
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 13V5a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3v8M4 13h16v4H4zM7 17v5m10-5v5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            @else
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                            @endif
                        </div>
                        <div class="min-w-0 border-l border-current/20 pl-4">
                            <p class="text-sm font-bold">{{ $card['label'] }}</p>
                            <p class="mt-1 text-3xl font-extrabold">{{ number_format($card['value']) }}</p>
                            <p class="mt-0.5 text-xs text-slate-600">{{ $card['description'] }}</p>
                        </div>
                    </article>
                @endforeach
            </section>

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif
            @if ($errors->getBag('default')->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->getBag('default')->first() }}</div>
            @endif

            <section data-critical-position-analytics class="grid gap-4 xl:grid-cols-3" aria-label="Analitik Critical Position">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-7 w-7 shrink-0 text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v9h9A9 9 0 1 1 12 3Z" /><path d="M15 2.5A7 7 0 0 1 21.5 9H15V2.5Z" /></svg>
                        <div><h2 class="font-display text-lg font-bold text-[#0B1F3A]">Distribusi Criticality</h2><p class="text-sm text-slate-500">Sebaran posisi berdasarkan tingkat criticality.</p></div>
                    </div>
                    <div data-criticality-chart data-chart='@json($chartData['criticality'])' class="mt-2 min-h-[245px]"></div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-7 w-7 shrink-0 text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 20V10m7 10V4m7 16v-7" stroke-linecap="round" /></svg>
                        <div><h2 class="font-display text-lg font-bold text-[#0B1F3A]">Coverage Formasi</h2><p class="text-sm text-slate-500">Ketersediaan incumbent terhadap kebutuhan formasi.</p></div>
                    </div>
                    <div data-coverage-chart data-chart='@json($chartData['coverage'])' class="mt-2 min-h-[245px]"></div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 h-7 w-7 shrink-0 text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87" /></svg>
                        <div><h2 class="font-display text-lg font-bold text-[#0B1F3A]">Kesiapan Successor</h2><p class="text-sm text-slate-500">Status kesiapan successor untuk seluruh critical position.</p></div>
                    </div>
                    <div data-successor-chart data-chart='@json($chartData['successor'])' class="mt-2 min-h-[245px]"></div>
                    <div class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-bold text-rose-700">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m10 2 8 15H2L10 2Z" /><path d="M10 7v4m0 3h.01" stroke-linecap="round" /></svg>
                        {{ number_format($chartData['successor']['missing']) }} posisi membutuhkan kandidat
                    </div>
                </article>
            </section>

            <section data-critical-position-filter class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form method="GET" action="{{ route('performance-talent.critical-positions.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-12">
                    <input type="hidden" name="per_page" value="{{ $perPage }}">
                    <label class="relative block xl:col-span-4"><span class="sr-only">Pencarian</span>
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="8.5" cy="8.5" r="5.25" /><path d="m12.5 12.5 4 4" stroke-linecap="round" /></svg>
                        <input type="search" name="search" value="{{ $search }}" placeholder="Cari nama atau SAP Position ID"
                            class="block w-full rounded-xl border-slate-300 py-2.5 pl-11 pr-3 text-sm focus:border-[#087FC3] focus:ring-[#087FC3]">
                    </label>
                    <label class="block xl:col-span-2"><span class="sr-only">Direktorat</span>
                    <select name="directorate_id" aria-label="Direktorat" class="block w-full rounded-xl border-slate-300 py-2.5 text-sm font-semibold text-slate-700 focus:border-[#087FC3] focus:ring-[#087FC3]">
                        <option value="">Semua Direktorat</option>
                        @foreach ($directorates as $directorate)
                            <option value="{{ $directorate->id }}" @selected($directorateId == $directorate->id)>{{ $directorate->name }}</option>
                        @endforeach
                    </select>
                    </label>
                    <label class="block xl:col-span-2"><span class="sr-only">Department</span>
                    <select name="department_id" aria-label="Department" class="block w-full rounded-xl border-slate-300 py-2.5 text-sm font-semibold text-slate-700 focus:border-[#087FC3] focus:ring-[#087FC3]">
                        <option value="">Semua Department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected($departmentId == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    </label>
                    <label class="block xl:col-span-2"><span class="sr-only">Criticality</span>
                    <select name="criticality" aria-label="Criticality" class="block w-full rounded-xl border-slate-300 py-2.5 text-sm font-semibold text-slate-700 focus:border-[#087FC3] focus:ring-[#087FC3]">
                        <option value="">Semua Criticality</option>
                        @foreach (\App\Enums\CriticalPositionCriticality::cases() as $option)
                            <option value="{{ $option->value }}" @selected($criticality === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    </label>
                    <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#0B1F3A] px-5 text-sm font-bold text-white shadow-sm transition hover:bg-[#102B50] xl:col-span-2">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 4h14l-5.5 6.2V16l-3 1v-6.8L3 4Z" stroke-linejoin="round" /></svg>
                        Terapkan
                    </button>
                    <label class="block xl:col-span-2"><span class="sr-only">Level Jabatan</span>
                        <select name="job_level" aria-label="Level Jabatan" class="block w-full rounded-xl border-slate-300 py-2.5 text-sm text-slate-700 focus:border-[#087FC3] focus:ring-[#087FC3]">
                            <option value="">Semua Level</option>
                            @foreach ($jobLevels as $level)
                                <option value="{{ $level }}" @selected($jobLevel === $level)>{{ $level }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block xl:col-span-2"><span class="sr-only">Coverage</span>
                    <select name="coverage_status" aria-label="Coverage" class="block w-full rounded-xl border-slate-300 py-2.5 text-sm text-slate-700 focus:border-[#087FC3] focus:ring-[#087FC3]">
                        <option value="">Semua Coverage</option>
                        @foreach (\App\Enums\CriticalPositionCoverageStatus::cases() as $option)
                            <option value="{{ $option->value }}" @selected($coverageStatus === $option)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    </label>
                    <label class="block xl:col-span-3"><span class="sr-only">Status Successor</span>
                    <select name="successor_status" aria-label="Status Successor" class="block w-full rounded-xl border-slate-300 py-2.5 text-sm text-slate-700 focus:border-[#087FC3] focus:ring-[#087FC3]">
                        <option value="">Semua Status Successor</option>
                        <option value="available" @selected($successorStatus === 'available')>Tersedia</option>
                        <option value="missing" @selected($successorStatus === 'missing')>Belum tersedia</option>
                    </select>
                    </label>
                    <div class="flex items-center md:col-span-2 xl:col-span-5 xl:justify-end">
                        <a href="{{ route('performance-talent.critical-positions.index') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-bold text-sky-700 transition hover:bg-sky-50 hover:text-sky-900">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 10a7 7 0 1 0 2-4.9M3 3v5h5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            Reset Filter
                        </a>
                    </div>
                </form>
            </section>

            <section data-critical-position-list class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <svg class="mt-0.5 h-7 w-7 shrink-0 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12M3 6h.01M3 12h.01M3 18h.01" stroke-linecap="round" /></svg>
                    <div><h2 class="font-display text-lg font-bold text-[#0B1F3A]">Daftar Critical Position</h2><p class="mt-0.5 text-sm text-slate-500">Data selalu mengikuti Master Organization, incumbent, dan Successor View.</p></div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    @can('create', \App\Models\CriticalPosition::class)
                        <button type="button" @click="openCreate($event.currentTarget)" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#0B1F3A] px-5 text-sm font-bold text-white shadow-sm transition hover:bg-[#102B50] sm:w-auto">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 4v12M4 10h12" stroke-linecap="round" /></svg>
                            Tambah Critical Position
                        </button>
                    @endcan
                    <form method="GET" action="{{ route('performance-talent.critical-positions.index') }}">
                        @foreach (request()->except(['per_page', 'page']) as $queryName => $queryValue)
                            @if (is_scalar($queryValue))
                                <input type="hidden" name="{{ $queryName }}" value="{{ $queryValue }}">
                            @endif
                        @endforeach
                        <label class="sr-only" for="critical-position-per-page">Jumlah data per halaman</label>
                        <select id="critical-position-per-page" name="per_page" @change="$el.form.submit()" class="w-full rounded-xl border-slate-300 py-2.5 text-sm font-semibold text-slate-700 focus:border-[#087FC3] focus:ring-[#087FC3] sm:w-40">
                            @foreach ([20, 40, 60] as $option)
                                <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }} per halaman</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1320px] text-left text-xs">
                    <thead class="bg-[#0B1F3A] text-[11px] uppercase tracking-wide text-white">
                        <tr>
                            @foreach (['No', 'Position & SAP ID', 'Organisasi', 'Criticality', 'Incumbent', 'Formasi & Coverage', 'Successor', 'Review Berikutnya', 'Aksi'] as $column)
                                <th class="px-4 py-3 {{ in_array($column, ['No', 'Aksi'], true) ? 'text-center' : '' }}">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($criticalPositions as $criticalPosition)
                            @php
                                $position = $criticalPosition->position;
                                $department = $position->unit->department;
                                $coverage = $position->coverageStatus();
                                $incumbents = $position->employees;
                                $successors = $position->successors;
                                $reviewSchedule = $criticalPosition->reviewSchedule();
                                $successorUrl = route('performance-talent.successors.index', ['position' => $position->id]);
                                $rowData = [
                                    'id' => (string) $criticalPosition->id,
                                    'position_id' => (string) $position->id,
                                    'position_name' => $position->name,
                                    'external_position_id' => $position->external_position_id ?: '—',
                                    'active_label' => $position->active ? 'Aktif' : 'Nonaktif',
                                    'job_level' => $position->job_level,
                                    'section' => $position->section?->name ?: '—',
                                    'unit' => $position->unit->name,
                                    'department' => $department->name,
                                    'directorate' => $department->directorate?->name ?: '—',
                                    'criticality' => $criticalPosition->criticality->value,
                                    'criticality_label' => $criticalPosition->criticality->label(),
                                    'assessment_reason' => $criticalPosition->assessment_reason,
                                    'assessed_at' => $criticalPosition->assessed_at->format('Y-m-d'),
                                    'assessed_at_label' => $criticalPosition->assessed_at->format('d M Y'),
                                    'review_due_at' => $criticalPosition->review_due_at?->format('Y-m-d') ?? '',
                                    'review_due_at_label' => $criticalPosition->review_due_at?->format('d M Y') ?? 'Belum dijadwalkan',
                                    'assessor' => $criticalPosition->assessor?->name ?: 'Tidak tersedia',
                                    'notes' => $criticalPosition->notes ?? '',
                                    'formation_count' => $position->formation_count,
                                    'coverage' => $coverage->label(),
                                    'incumbents' => $incumbents->map(fn ($employee) => [
                                        'name' => $employeeDisplayName($employee),
                                        'sap' => $employee->sap ?: '—',
                                        'position' => $position->name,
                                    ])->values(),
                                    'successor_count' => $position->successorCount(),
                                    'ready_now_count' => $position->readyNowSuccessorCount(),
                                    'best_readiness' => $position->bestSuccessorReadiness() ? ucfirst($position->bestSuccessorReadiness()) : 'Belum tersedia',
                                    'successors' => $canViewSuccessors ? $successors->map(fn ($successor) => [
                                        'name' => $successor->name,
                                        'sap' => $successor->sap ?: '—',
                                        'current_position' => $successor->current_position ?: '—',
                                        'readiness' => ucfirst((string) $successor->succession_readiness),
                                    ])->values() : [],
                                    'successorUrl' => $canViewSuccessors ? $successorUrl : null,
                                    'updateUrl' => route('performance-talent.critical-positions.update', $criticalPosition),
                                    'deleteUrl' => route('performance-talent.critical-positions.destroy', $criticalPosition),
                                    'can_update' => auth()->user()->can('update', $criticalPosition),
                                    'can_delete' => auth()->user()->can('delete', $criticalPosition),
                                ];
                            @endphp
                            <tr data-critical-position-row class="align-top hover:bg-slate-50/60">
                                <td class="px-4 py-4 text-center font-semibold text-slate-500">{{ $criticalPositions->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-4">
                                    <p class="font-bold text-slate-900">{{ $position->name }}</p>
                                    <p class="mt-1 text-slate-500">SAP Position ID: {{ $position->external_position_id ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800">{{ $department->name }}</p>
                                    <p class="mt-1 text-slate-500">{{ $department->directorate?->name ?: '—' }} · {{ $position->job_level }}</p>
                                </td>
                                <td class="px-4 py-4"><x-ui.status-badge :tone="$criticalPosition->criticality->tone()">{{ $criticalPosition->criticality->label() }}</x-ui.status-badge></td>
                                <td class="px-4 py-4">
                                    @if ($incumbents->isEmpty())
                                        <span class="text-slate-500">Belum ada incumbent</span>
                                    @else
                                        <div class="space-y-2">
                                            @foreach ($incumbents->take(2) as $incumbent)
                                                @php($incumbentName = $employeeDisplayName($incumbent))
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sky-100 text-[11px] font-extrabold text-sky-700">{{ $employeeInitials($incumbentName) }}</span>
                                                    <div class="min-w-0"><p class="truncate font-semibold text-slate-800">{{ $incumbentName }}</p><p class="text-slate-500">SAP Pegawai: {{ $incumbent->sap ?: '—' }}</p></div>
                                                </div>
                                            @endforeach
                                        </div>
                                        @if ($incumbents->count() > 2)<button type="button" @click="openDetail(@js($rowData))" class="mt-2 font-semibold text-sky-700">+{{ $incumbents->count() - 2 }} lainnya</button>@endif
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-bold text-slate-800">{{ $incumbents->count() }} / {{ $position->formation_count }}</p>
                                    <div class="mt-2"><x-ui.status-badge :tone="$coverage->tone()">{{ $coverage->label() }}</x-ui.status-badge></div>
                                </td>
                                <td class="px-4 py-4">
                                    @if ($successors->isEmpty())
                                        <x-ui.status-badge tone="warning">Belum tersedia</x-ui.status-badge>
                                    @else
                                        <x-ui.status-badge tone="success">{{ $successors->count() }} kandidat</x-ui.status-badge>
                                    @endif
                                    <p class="mt-1 text-slate-500">Ready Now: {{ $position->readyNowSuccessorCount() }}</p>
                                    @if ($canViewSuccessors)
                                        <a href="{{ $successors->isEmpty() && $canCreateSuccessors ? route('performance-talent.successors.index', ['position' => $position->id, 'create' => 1]) : $successorUrl }}" class="mt-2 inline-block font-semibold text-sky-700 hover:text-sky-900">Kelola Successor</a>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800">{{ $reviewSchedule['date'] }}</p>
                                    <div class="mt-2"><x-ui.status-badge :tone="$reviewSchedule['tone']">{{ $reviewSchedule['label'] }}</x-ui.status-badge></div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <button type="button" @click="openActionMenu(@js($rowData), $event.currentTarget)" aria-haspopup="menu"
                                        data-update-url="{{ route('performance-talent.critical-positions.update', $criticalPosition) }}"
                                        :aria-expanded="String(actionMenu?.id === @js((string) $criticalPosition->id))" aria-label="Buka aksi {{ $position->name }}"
                                        class="inline-flex h-9 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-[#087FC3]">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="4" cy="10" r="1.5" /><circle cx="10" cy="10" r="1.5" /><circle cx="16" cy="10" r="1.5" /></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr><x-ui.empty-state colspan="9" title="Belum ada Critical Position" description="Tambahkan penilaian Position atau sesuaikan filter pencarian." /></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">Menampilkan {{ $criticalPositions->count() }} dari {{ $criticalPositions->total() }} posisi</p>
                <div>{{ $criticalPositions->links() }}</div>
            </div>
            </section>
        </div>

        <template x-teleport="body">
            <div x-cloak x-show="actionMenu" x-bind:style="`top: ${actionMenu?.menuTop}px; left: ${actionMenu?.menuLeft}px`"
                @click.outside="closeActionMenu()" @keydown.arrow-down.prevent="moveActionMenuFocus($event, 1)" @keydown.arrow-up.prevent="moveActionMenuFocus($event, -1)"
                class="fixed z-[110] w-48 rounded-xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-950/15"
                role="menu" aria-label="Aksi Critical Position" data-critical-position-action-menu>
                <button type="button" role="menuitem" @click="openDetail(actionMenu)" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none">Detail</button>
                @if (auth()->user()->hasPermission('critical-positions.update'))
                    <button x-show="actionMenu?.can_update" type="button" role="menuitem" @click="openEdit(actionMenu, actionMenuTrigger)" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-amber-700 transition hover:bg-amber-50 focus:bg-amber-50 focus:outline-none">Edit</button>
                @endif
                @if (auth()->user()->hasPermission('critical-positions.delete'))
                    <form x-show="actionMenu?.can_delete" method="POST" x-bind:action="actionMenu?.deleteUrl" data-confirm-delete data-confirm-submit data-confirm-title="Hapus Critical Position ini?" data-confirm-text="Hanya data penilaian yang dihapus; Master Position, Employee, dan Successor tetap tersimpan.">
                        @csrf @method('DELETE')
                        <button type="submit" role="menuitem" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50 focus:bg-red-50 focus:outline-none">Hapus</button>
                    </form>
                @endif
            </div>
        </template>

        <div x-cloak x-show="panel === 'detail'" class="fixed inset-0 z-[70]" role="presentation" data-critical-position-detail>
            <button type="button" @click="closePanel()" aria-label="Tutup detail" class="absolute inset-0 h-full w-full bg-slate-950/60 backdrop-blur-sm"></button>
            <section class="fixed inset-y-0 right-0 z-[71] flex w-full max-w-2xl flex-col overflow-hidden border-l border-slate-200 bg-white shadow-2xl" role="dialog" aria-modal="true" data-critical-position-detail-panel>
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-sky-700">Detail Critical Position</p><h3 class="mt-1 font-display text-xl font-bold text-slate-950" x-text="detail?.position_name"></h3></div>
                    <button type="button" @click="closePanel()" class="ui-button ui-button-secondary">Tutup</button>
                </div>
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto bg-slate-50 p-5">
                    <section class="rounded-xl border border-slate-200 bg-white p-4"><h4 class="font-bold text-slate-900">Identitas & Struktur</h4><dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">SAP Position ID</dt><dd class="font-semibold" x-text="detail?.external_position_id"></dd></div><div><dt class="text-slate-500">Level</dt><dd x-text="detail?.job_level"></dd></div><div><dt class="text-slate-500">Section</dt><dd x-text="detail?.section"></dd></div><div><dt class="text-slate-500">Unit</dt><dd x-text="detail?.unit"></dd></div><div><dt class="text-slate-500">Department</dt><dd x-text="detail?.department"></dd></div><div><dt class="text-slate-500">Direktorat</dt><dd x-text="detail?.directorate"></dd></div><div><dt class="text-slate-500">Status Position</dt><dd x-text="detail?.active_label"></dd></div></dl></section>
                    <section class="rounded-xl border border-slate-200 bg-white p-4"><h4 class="font-bold text-slate-900">Penilaian</h4><dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2"><div><dt class="text-slate-500">Criticality</dt><dd class="font-semibold" x-text="detail?.criticality_label"></dd></div><div><dt class="text-slate-500">Penilai</dt><dd x-text="detail?.assessor"></dd></div><div><dt class="text-slate-500">Tanggal penilaian</dt><dd x-text="detail?.assessed_at_label"></dd></div><div><dt class="text-slate-500">Review berikutnya</dt><dd x-text="detail?.review_due_at_label"></dd></div></dl><div class="mt-3"><p class="text-xs font-semibold text-slate-500">Alasan</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700" x-text="detail?.assessment_reason"></p></div><div class="mt-3"><p class="text-xs font-semibold text-slate-500">Catatan</p><p class="mt-1 whitespace-pre-line text-sm text-slate-700" x-text="detail?.notes || '—'"></p></div></section>
                    <section class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center justify-between"><h4 class="font-bold text-slate-900">Incumbent</h4><span class="text-sm font-semibold text-slate-600"><span x-text="(detail?.incumbents || []).length"></span>/<span x-text="detail?.formation_count"></span> · <span x-text="detail?.coverage"></span></span></div><template x-for="incumbent in (detail?.incumbents || [])" :key="`${incumbent.sap}-${incumbent.name}`"><article class="mt-3 rounded-lg border border-slate-200 p-3 text-sm"><p class="font-semibold text-slate-900" x-text="incumbent.name"></p><p class="text-slate-500">SAP Pegawai: <span x-text="incumbent.sap"></span></p><p class="text-slate-500" x-text="incumbent.position"></p></article></template><p x-show="(detail?.incumbents || []).length === 0" class="mt-3 text-sm text-slate-500">Posisi sedang Vacant.</p></section>
                    <section class="rounded-xl border border-slate-200 bg-white p-4"><div class="flex items-center justify-between"><h4 class="font-bold text-slate-900">Successor</h4><span class="text-sm text-slate-600"><span x-text="detail?.successor_count"></span> kandidat · <span x-text="detail?.ready_now_count"></span> Ready Now</span></div><p class="mt-1 text-sm text-slate-500">Readiness terbaik: <span x-text="detail?.best_readiness"></span></p><template x-for="successor in (detail?.successors || [])" :key="`${successor.sap}-${successor.name}`"><article class="mt-3 rounded-lg border border-slate-200 p-3 text-sm"><p class="font-semibold text-slate-900" x-text="successor.name"></p><p class="text-slate-500">SAP: <span x-text="successor.sap"></span> · <span x-text="successor.current_position"></span></p><p class="font-medium text-sky-700" x-text="successor.readiness"></p></article></template><p x-show="detail?.successor_count === 0" class="mt-3 text-sm font-medium text-rose-700">Posisi belum memiliki successor.</p>@if ($canViewSuccessors)<a x-show="detail?.successorUrl" x-bind:href="detail?.successorUrl" class="mt-3 inline-block font-semibold text-sky-700">Lihat Successor</a>@endif</section>
                </div>
            </section>
        </div>

        @if (auth()->user()->can('create', \App\Models\CriticalPosition::class) || auth()->user()->hasPermission('critical-positions.update'))
            <template x-teleport="body">
                <div x-cloak x-show="panel === 'form'" x-ref="formDialog"
                    @click.self="closeForm()" @keydown.escape.stop="closeForm()" @keydown.tab="trapFormFocus($event)"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-[#0B1F3A]/75 p-3 backdrop-blur-[2px] sm:p-5"
                    role="presentation" data-critical-position-form-modal>
                    <section x-show="panel === 'form'"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="translate-y-3 opacity-0 sm:scale-[0.98]"
                        x-transition:enter-end="translate-y-0 opacity-100 sm:scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="translate-y-0 opacity-100 sm:scale-100"
                        x-transition:leave-end="translate-y-3 opacity-0 sm:scale-[0.98]"
                        class="relative flex max-h-[95vh] w-full max-w-4xl flex-col overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-2xl shadow-slate-950/25 sm:max-h-[90vh]"
                        data-critical-position-form-panel
                        role="dialog" aria-modal="true" aria-labelledby="critical-position-form-title"
                        aria-describedby="critical-position-form-description">
                        <header class="flex shrink-0 items-start justify-between gap-4 bg-white px-5 pb-4 pt-6 sm:px-10 sm:pb-5 sm:pt-7">
                            <div class="min-w-0">
                                <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-[#087FC3] sm:text-sm">Performance &amp; Talent Management</p>
                                <h2 id="critical-position-form-title" class="mt-1 font-display text-2xl font-bold text-[#0B1F3A] sm:text-3xl"
                                    x-text="formMode === 'edit' ? 'Ubah Critical Position' : 'Tambah Critical Position'">Tambah Critical Position</h2>
                                <p id="critical-position-form-description" class="mt-1 text-sm text-slate-500"
                                    x-text="formMode === 'edit' ? 'Perbarui data penilaian critical position.' : 'Pilih Master Position dan isi data penilaiannya.'">Pilih Master Position dan isi data penilaiannya.</p>
                            </div>
                            <button type="button" @click="closeForm()" aria-label="Tutup modal"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-[#0B1F3A] shadow-sm transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#087FC3] focus-visible:ring-offset-2">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" /></svg>
                            </button>
                        </header>

                        @include('performance-talent.critical-positions._form', ['errorBag' => $activeErrorBag])
                    </section>
                </div>
            </template>
        @endif
    </div>
@endsection
