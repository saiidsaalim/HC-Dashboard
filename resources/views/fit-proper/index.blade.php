@extends('layouts.app')
@section('page-title', 'Fit & Proper')

@php
    $currentUser = auth()->user();
    $canCreateActivities = $currentUser->can('create', \App\Models\FitProperActivity::class);
    $canUpdateActivities = $currentUser->hasPermission('fit-proper.update');
    $stageLabels = ['Persiapan', 'Penilaian', 'Rekap', 'Selesai'];
    $detailStageLabels = ['Administrasi lengkap', 'Uji kompetensi', 'Wawancara panel', 'Rekap nilai'];
    $examinerReadiness = $examinerCount > 0
        ? min(100, round(($submittedExaminerCount / $examinerCount) * 100, 1))
        : 0;

    $activitySummaries = $activities->getCollection()->mapWithKeys(function ($activity) use ($currentUser, $canUpdateActivities, $detailStageLabels) {
        $examinerTotal = max(0, (int) $activity->examiners_count);
        $submittedTotal = min($examinerTotal, max(0, (int) $activity->submitted_count));
        $progress = $examinerTotal > 0
            ? min(100, round(($submittedTotal / $examinerTotal) * 100, 1))
            : 0;
        $stagePosition = match ($activity->status) {
            'Selesai' => 4,
            'Berjalan' => $examinerTotal > 0 && $submittedTotal === $examinerTotal ? 2 : 1,
            default => 0,
        };
        $statusLabel = match ($activity->status) {
            'Draft' => 'Terjadwal',
            default => $activity->status ?: 'Belum tersedia',
        };
        $statusClasses = match ($activity->status) {
            'Berjalan' => 'border-sky-200 bg-sky-50 text-sky-700',
            'Selesai' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            default => 'border-amber-200 bg-amber-50 text-amber-700',
        };
        $detailStages = collect($detailStageLabels)->map(function ($label, $index) use ($stagePosition) {
            $state = $index < $stagePosition
                ? 'complete'
                : ($index === $stagePosition ? 'active' : 'pending');

            return [
                'label' => $label,
                'state' => $state,
                'stateLabel' => match ($state) {
                    'complete' => 'Selesai',
                    'active' => 'Berjalan',
                    default => 'Menunggu',
                },
            ];
        })->values()->all();
        $canManage = $canUpdateActivities
            || $activity->examiners->contains('user_id', $currentUser->id);

        return [
            $activity->id => [
                'id' => $activity->id,
                'name' => $activity->name ?: 'Kegiatan tanpa nama',
                'targetPosition' => $activity->target_position ?: 'Belum tersedia',
                'period' => $activity->scheduled_at?->translatedFormat('d M Y') ?? 'Belum tersedia',
                'status' => $statusLabel,
                'statusClasses' => $statusClasses,
                'candidateCount' => max(0, (int) $activity->participants_count),
                'examinerCount' => $examinerTotal,
                'submittedCount' => $submittedTotal,
                'progress' => $progress,
                'progressLabel' => number_format($progress, $progress == floor($progress) ? 0 : 1, ',', '.').'%',
                'stagePosition' => $stagePosition,
                'detailStages' => $detailStages,
                'detailUrl' => route('fit-proper.show', $activity),
                'canManage' => $canManage,
            ],
        ];
    });

    $firstActivity = $activities->first();
    $firstSummary = $firstActivity ? $activitySummaries->get($firstActivity->id) : null;
    $selectedActivityId = $firstActivity?->id;
@endphp

@section('content')
<div
    class="space-y-5"
    data-fp-root
    x-data="{
        selectedId: @js($selectedActivityId),
        createOpen: @js($errors->hasAny(['name', 'target_position', 'department', 'scheduled_at', 'description'])),
        settingsOpen: @js($errors->hasAny(['weight_kpi', 'weight_assessment', 'weight_fp', 'max_kpi', 'max_assessment', 'passing_grade'])),
        activities: @js($activitySummaries),
        get selected() {
            return this.activities[this.selectedId] ?? Object.values(this.activities)[0] ?? null;
        },
        selectActivity(id) {
            this.selectedId = id;
        },
        openCreateForm() {
            this.createOpen = true;
        },
    }"
    @keydown.escape.window="createOpen = false; settingsOpen = false"
>
    <div @class([
        'grid gap-4 md:grid-cols-3 xl:items-center',
        'xl:grid-cols-[auto_repeat(3,minmax(0,1fr))]' => $canCreateActivities,
        'xl:grid-cols-3' => ! $canCreateActivities,
    ])>
        @can('create', \App\Models\FitProperActivity::class)
            <button type="button" class="ui-button ui-button-primary w-full md:col-span-3 xl:col-span-1 xl:w-fit" @click="openCreateForm()">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M10 4v12M4 10h12" stroke-linecap="round" />
                </svg>
                Tambah Kegiatan
            </button>
        @endcan

        <article class="flex h-24 items-center gap-4 rounded-2xl border border-slate-200 bg-white px-5 shadow-sm">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="4" y="5.5" width="16" height="14" rx="2.5" />
                    <path d="M8 3.5v4M16 3.5v4M4 10h16" stroke-linecap="round" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-medium text-slate-500">Kegiatan Aktif</p>
                <p class="mt-0.5 font-display text-2xl font-bold text-slate-950">{{ $activeActivityCount }}</p>
            </div>
        </article>

        <article class="flex h-24 items-center gap-4 rounded-2xl border border-slate-200 bg-white px-5 shadow-sm">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 4 18.5V20" stroke-linecap="round" />
                    <circle cx="10" cy="7" r="3.5" />
                    <path d="M17 10.5a3 3 0 1 0 0-6M19 14.5a4 4 0 0 1 3 3.87V20" stroke-linecap="round" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-medium text-slate-500">Kandidat Dinilai</p>
                <p class="mt-0.5 font-display text-2xl font-bold text-slate-950">{{ $participantCount }}</p>
            </div>
        </article>

        <article class="flex h-24 items-center gap-4 rounded-2xl border border-slate-200 bg-white px-5 shadow-sm">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-500" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M12 3 5 6v5c0 4.6 2.9 8.1 7 10 4.1-1.9 7-5.4 7-10V6l-7-3Z" stroke-linejoin="round" />
                    <path d="m9 12 2 2 4-5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-medium text-slate-500">Kesiapan Penguji</p>
                <p class="mt-0.5 font-display text-2xl font-bold text-slate-950">
                    {{ $submittedExaminerCount }}<span class="text-base text-slate-400">/{{ $examinerCount }}</span>
                </p>
            </div>
        </article>
    </div>

    @include('fit-proper._messages')

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(20rem,1fr)] xl:items-stretch">
        <section class="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:h-[36rem]" aria-labelledby="activities-heading">
            <div class="grid gap-3 border-b border-slate-200 pb-3 lg:grid-cols-[minmax(13rem,1fr)_minmax(14rem,1.15fr)_auto] lg:items-center">
                <h2 id="activities-heading" class="font-display text-xl font-bold text-slate-950">Kegiatan Berlangsung</h2>

                    <form method="GET" action="{{ route('fit-proper') }}" class="flex min-w-0">
                        @if (request()->filled('status'))
                            <input type="hidden" name="status" value="{{ request('status') }}">
                        @endif
                        <label class="relative min-w-0 flex-1">
                            <span class="sr-only">Cari kegiatan</span>
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <circle cx="8.5" cy="8.5" r="5" />
                                <path d="m12 12 4 4" stroke-linecap="round" />
                            </svg>
                            <input name="q" value="{{ request('q') }}" placeholder="Cari kegiatan, posisi, atau kandidat…" class="block w-full rounded-xl border-slate-300 py-2 pl-9 pr-3 text-sm focus:border-sky-500 focus:ring-sky-500">
                        </label>
                        <button type="submit" class="sr-only">Cari</button>
                    </form>

                <nav class="scrollbar-hidden flex gap-1.5 overflow-x-auto" aria-label="Filter status kegiatan">
                    @foreach (['' => 'Semua', 'Berjalan' => 'Berjalan', 'Draft' => 'Terjadwal', 'Selesai' => 'Selesai'] as $statusValue => $statusLabel)
                        @php
                            $isActiveStatus = request('status', '') === $statusValue;
                        @endphp
                        <a
                            href="{{ route('fit-proper', array_filter(['q' => request('q'), 'status' => $statusValue], fn ($value) => $value !== null && $value !== '')) }}"
                            @class([
                                'inline-flex shrink-0 items-center rounded-full border px-3.5 py-2 text-xs font-bold transition focus-visible:outline-none',
                                'border-sky-600 bg-sky-600 text-white shadow-sm' => $isActiveStatus,
                                'border-slate-200 bg-white text-slate-600 hover:border-sky-300 hover:text-sky-700' => ! $isActiveStatus,
                            ])
                            @if ($isActiveStatus) aria-current="page" @endif
                        >{{ $statusLabel }}</a>
                    @endforeach
                </nav>
            </div>

            <div
                class="scrollbar-hidden mt-3 min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pr-1"
                tabindex="0"
                aria-label="Daftar kegiatan Fit & Proper"
            >
                @forelse ($activities as $activity)
                    @php
                        $summary = $activitySummaries->get($activity->id);
                        $stagePosition = $summary['stagePosition'];
                        $isInitiallySelected = $activity->id === $selectedActivityId;
                        $visibleParticipants = $activity->participants->take(3);
                        $additionalParticipantCount = max(0, $activity->participants_count - $visibleParticipants->count());
                    @endphp
                    <article
                        tabindex="0"
                        role="button"
                        aria-label="Pilih kegiatan {{ $activity->name }}"
                        aria-selected="{{ $isInitiallySelected ? 'true' : 'false' }}"
                        :aria-selected="(selectedId === {{ $activity->id }}).toString()"
                        @click="selectActivity({{ $activity->id }})"
                        @keydown.enter.self.prevent="selectActivity({{ $activity->id }})"
                        @keydown.space.self.prevent="selectActivity({{ $activity->id }})"
                        @class([
                            'group relative overflow-hidden rounded-2xl border p-4 transition duration-200 focus-visible:outline-none sm:p-5',
                            'border-sky-400 bg-sky-50/70 shadow-md' => $isInitiallySelected,
                            'border-slate-200 bg-white hover:border-sky-200 hover:shadow-sm' => ! $isInitiallySelected,
                        ])
                        :class="selectedId === {{ $activity->id }} ? 'border-sky-400 bg-sky-50/70 shadow-md' : 'border-slate-200 bg-white hover:border-sky-200 hover:shadow-sm'"
                    >
                        <span
                            aria-hidden="true"
                            class="absolute inset-y-3 left-0 w-1 rounded-r-full bg-amber-400"
                            @if (! $isInitiallySelected) style="display: none" @endif
                            x-show="selectedId === {{ $activity->id }}"
                        ></span>

                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-start">
                            <div class="flex min-w-0 gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-600" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <rect x="3.5" y="7" width="17" height="12.5" rx="2.5" />
                                        <path d="M8.5 7V5.5A1.5 1.5 0 0 1 10 4h4a1.5 1.5 0 0 1 1.5 1.5V7M3.5 12h17M10 12v2h4v-2" stroke-linecap="round" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <h3 class="truncate font-display text-base font-bold text-slate-950">{{ $activity->name ?: 'Kegiatan tanpa nama' }}</h3>
                                    <p class="mt-0.5 truncate text-sm text-slate-500">Target posisi: {{ $activity->target_position ?: 'Belum tersedia' }}</p>
                                    <p class="mt-1 flex items-center gap-2 text-xs font-semibold text-slate-600">
                                        <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                            <rect x="3" y="4.5" width="14" height="12.5" rx="2" />
                                            <path d="M6 2.5v4M14 2.5v4M3 8.5h14" stroke-linecap="round" />
                                        </svg>
                                        {{ $summary['period'] }}
                                    </p>
                                </div>
                            </div>

                            <x-ui.status-badge :tone="match ($activity->status) { 'Berjalan' => 'info', 'Selesai' => 'success', default => 'warning' }" class="w-fit">
                                {{ $summary['status'] }}
                            </x-ui.status-badge>

                            <div class="flex shrink-0 items-center gap-4 border-slate-200 sm:border-l sm:pl-4">
                                <div>
                                    <p class="text-xs font-medium text-slate-500">Peserta</p>
                                    <div class="mt-1 flex -space-x-1.5" aria-label="{{ $activity->participants_count }} peserta">
                                        @foreach ($visibleParticipants as $participant)
                                            @php
                                                $initials = collect(preg_split('/\s+/', trim($participant->name ?: '—')))
                                                    ->filter()
                                                    ->take(2)
                                                    ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                                    ->implode('');
                                            @endphp
                                            <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-sky-100 text-[11px] font-bold text-sky-700" title="{{ $participant->name }}">{{ $initials ?: '—' }}</span>
                                        @endforeach
                                        @if ($additionalParticipantCount > 0)
                                            <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-slate-100 text-[11px] font-bold text-slate-600">+{{ $additionalParticipantCount }}</span>
                                        @elseif ($visibleParticipants->isEmpty())
                                            <span class="inline-flex h-8 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-xs font-semibold text-slate-500">Belum ada</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-4" aria-label="Tahapan kegiatan">
                            @foreach ($stageLabels as $stageIndex => $stageLabel)
                                @php
                                    $isComplete = $stageIndex < $stagePosition;
                                    $isActive = $stageIndex === $stagePosition;
                                    $connectorComplete = $stageIndex < min($stagePosition, 4);
                                @endphp
                                <div class="relative flex min-w-0 flex-col items-center text-center">
                                    @if (! $loop->last)
                                        <span @class(['absolute left-1/2 top-3 h-0.5 w-full', 'bg-sky-500' => $connectorComplete, 'bg-slate-200' => ! $connectorComplete]) aria-hidden="true"></span>
                                    @endif
                                    <span @class([
                                        'relative z-10 flex h-6 w-6 items-center justify-center rounded-full border-2',
                                        'border-sky-500 bg-sky-500 text-white' => $isComplete,
                                        'border-sky-500 bg-white text-sky-500' => $isActive,
                                        'border-slate-300 bg-white text-transparent' => ! $isComplete && ! $isActive,
                                    ]) aria-hidden="true">
                                        @if ($isComplete)
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m3 8 3 3 7-7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                        @elseif ($isActive)
                                            <span class="h-2 w-2 rounded-full bg-sky-500"></span>
                                        @endif
                                    </span>
                                    <span @class(['mt-1.5 truncate text-[11px] font-semibold sm:text-xs', 'text-slate-800' => $isComplete || $isActive, 'text-slate-400' => ! $isComplete && ! $isActive])>{{ $stageLabel }}</span>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <x-ui.empty-state title="Belum ada kegiatan Fit & Proper" description="Tambahkan kegiatan baru atau sesuaikan filter yang digunakan." class="py-10">
                        @can('create', \App\Models\FitProperActivity::class)
                            <button type="button" class="ui-button ui-button-primary mt-4" @click="openCreateForm()">Tambah Kegiatan</button>
                        @endcan
                    </x-ui.empty-state>
                @endforelse
            </div>

            @if ($activities->hasPages())
                <div class="mt-5 border-t border-slate-200 pt-4">{{ $activities->links() }}</div>
            @endif
        </section>

        @if ($firstSummary)
            <aside class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm xl:sticky xl:top-6 xl:h-[36rem]" aria-labelledby="activity-summary-heading">
                <div class="border-b border-slate-200 pb-3">
                    <h2 id="activity-summary-heading" class="font-display text-xl font-bold text-slate-950">Ringkasan Kegiatan</h2>
                </div>

                <div class="mt-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-display text-lg font-bold text-slate-950" x-text="selected.name">{{ $firstSummary['name'] }}</h3>
                            <p class="mt-1 text-sm text-slate-500">Target posisi: <span x-text="selected.targetPosition">{{ $firstSummary['targetPosition'] }}</span></p>
                        </div>
                        <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-xs font-bold {{ $firstSummary['statusClasses'] }}" :class="selected.statusClasses" x-text="selected.status">{{ $firstSummary['status'] }}</span>
                    </div>

                    <div class="mt-4 grid items-center gap-4 sm:grid-cols-[8rem_minmax(0,1fr)] xl:grid-cols-1 2xl:grid-cols-[8rem_minmax(0,1fr)]">
                        <div class="relative mx-auto h-28 w-28" role="img" :aria-label="`Progres ${selected.progressLabel}`">
                            <svg class="h-full w-full -rotate-90" viewBox="0 0 42 42" aria-hidden="true">
                                <circle cx="21" cy="21" r="16" fill="none" stroke="currentColor" stroke-width="4" class="text-slate-100" />
                                <circle cx="21" cy="21" r="16" pathLength="100" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="100" stroke-dashoffset="{{ 100 - $firstSummary['progress'] }}" :stroke-dashoffset="100 - selected.progress" class="text-sky-500 transition-all duration-300" />
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <strong class="max-w-[4.75rem] whitespace-nowrap text-center font-display text-xl font-bold leading-none text-slate-950" x-text="selected.progressLabel">{{ $firstSummary['progressLabel'] }}</strong>
                                <span class="text-xs font-medium text-slate-500">Selesai</span>
                            </div>
                        </div>

                        <dl class="space-y-2.5 text-sm">
                            <div class="flex gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="4.5" width="14" height="12.5" rx="2" /><path d="M6 2.5v4M14 2.5v4M3 8.5h14" stroke-linecap="round" /></svg>
                                <div><dt class="text-xs text-slate-500">Periode</dt><dd class="font-bold text-slate-800" x-text="selected.period">{{ $firstSummary['period'] }}</dd></div>
                            </div>
                            <div class="flex gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="8" cy="6.5" r="3" /><path d="M2.5 17v-1a5.5 5.5 0 0 1 11 0v1M14 9a3 3 0 0 1 3.5 3v3" stroke-linecap="round" /></svg>
                                <div><dt class="text-xs text-slate-500">Kandidat</dt><dd class="font-bold text-slate-800"><span x-text="selected.candidateCount">{{ $firstSummary['candidateCount'] }}</span> kandidat</dd></div>
                            </div>
                            <div class="flex gap-3">
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M10 2.5 4 5v4.3c0 4 2.5 7 6 8.7 3.5-1.7 6-4.7 6-8.7V5l-6-2.5Z" /><path d="m7.5 10 1.7 1.7 3.5-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                <div><dt class="text-xs text-slate-500">Penyelesaian Penguji</dt><dd class="font-bold text-slate-800"><span x-text="selected.submittedCount">{{ $firstSummary['submittedCount'] }}</span> dari <span x-text="selected.examinerCount">{{ $firstSummary['examinerCount'] }}</span> penguji</dd></div>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="mt-4 border-t border-slate-200 pt-4">
                    <h3 class="text-sm font-bold text-slate-950">Progres Tahapan</h3>
                    <div class="mt-2 space-y-1.5" x-show="false">
                        @foreach ($firstSummary['detailStages'] as $stage)
                            <div class="flex items-center gap-3 border-b border-slate-100 py-1.5 last:border-0">
                                <span @class([
                                    'flex h-5 w-5 shrink-0 items-center justify-center rounded border',
                                    'border-emerald-500 bg-emerald-500 text-white' => $stage['state'] === 'complete',
                                    'border-sky-500 bg-sky-50 text-sky-600' => $stage['state'] === 'active',
                                    'border-slate-300 bg-white text-transparent' => $stage['state'] === 'pending',
                                ])>
                                    @if ($stage['state'] === 'complete')
                                        <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 8 3 3 7-7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    @elseif ($stage['state'] === 'active')
                                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1 text-sm font-medium text-slate-700">{{ $stage['label'] }}</span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $stage['stateLabel'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div x-cloak class="mt-2 space-y-1.5">
                        <template x-for="stage in selected.detailStages" :key="stage.label">
                            <div class="flex items-center gap-3 border-b border-slate-100 py-1.5 last:border-0">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded border" :class="{ 'border-emerald-500 bg-emerald-500 text-white': stage.state === 'complete', 'border-sky-500 bg-sky-50 text-sky-600': stage.state === 'active', 'border-slate-300 bg-white text-transparent': stage.state === 'pending' }">
                                    <svg x-show="stage.state === 'complete'" class="h-3 w-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 8 3 3 7-7" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    <span x-show="stage.state === 'active'" class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                </span>
                                <span class="min-w-0 flex-1 text-sm font-medium text-slate-700" x-text="stage.label"></span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="{ 'bg-emerald-50 text-emerald-700': stage.state === 'complete', 'bg-sky-50 text-sky-700': stage.state === 'active', 'bg-slate-100 text-slate-600': stage.state === 'pending' }" x-text="stage.stateLabel"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 border-t border-slate-200 pt-4 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    @can('settings', \App\Models\FitProperActivity::class)
                    <button type="button" class="ui-button ui-button-secondary w-full" @click="settingsOpen = true">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z" /><circle cx="10" cy="10" r="2" /></svg>
                        Lihat Standar Penilaian
                    </button>
                    @else
                    <button type="button" class="ui-button ui-button-secondary w-full" disabled title="Anda tidak memiliki izin mengubah standar penilaian">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2.5 10s2.7-5 7.5-5 7.5 5 7.5 5-2.7 5-7.5 5-7.5-5-7.5-5Z" /><circle cx="10" cy="10" r="2" /></svg>
                        Lihat Standar Penilaian
                    </button>
                    @endcan
                    @if ($firstSummary['canManage'])
                        <a href="{{ $firstSummary['detailUrl'] }}" class="ui-button ui-button-primary w-full" x-show="false">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="3" /><path d="M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2M15.3 4.7l-1.4 1.4M6.1 13.9l-1.4 1.4M15.3 15.3l-1.4-1.4M6.1 6.1 4.7 4.7" stroke-linecap="round" /></svg>
                            Kelola Penilaian
                        </a>
                    @endif
                    <a x-cloak :href="selected.detailUrl" x-show="selected.canManage" class="ui-button ui-button-primary w-full">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="3" /><path d="M10 2.5v2M10 15.5v2M17.5 10h-2M4.5 10h-2M15.3 4.7l-1.4 1.4M6.1 13.9l-1.4 1.4M15.3 15.3l-1.4-1.4M6.1 6.1 4.7 4.7" stroke-linecap="round" /></svg>
                        Kelola Penilaian
                    </a>
                </div>
            </aside>
        @endif
    </div>

    @can('create', \App\Models\FitProperActivity::class)
        <template x-teleport="body">
            <div x-cloak x-show="createOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" role="presentation" data-fp-root data-app-content>
                <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-[1px]" @click="createOpen = false" aria-hidden="true"></div>
                <section
                    x-show="createOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-y-3 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="translate-y-3 opacity-0"
                    class="relative z-10 w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="fit-proper-create-title"
                >
                    <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Fit &amp; Proper</p>
                            <h2 id="fit-proper-create-title" class="mt-1 font-display text-xl font-bold text-slate-950">Tambah Kegiatan</h2>
                            <p class="mt-1 text-sm text-slate-500">Lengkapi informasi kegiatan asesmen yang akan dilaksanakan.</p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click="createOpen = false" aria-label="Tutup form tambah kegiatan">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" /></svg>
                        </button>
                    </header>
                    <form data-fp-async method="POST" action="{{ route('fit-proper.store') }}" class="max-h-[calc(100vh-8rem)] overflow-y-auto p-5 text-slate-700 md:max-h-none md:overflow-visible">
                        @include('fit-proper._activity-form', ['activity' => null])
                    </form>
                </section>
            </div>
        </template>
    @endcan

    @can('settings', \App\Models\FitProperActivity::class)
        <template x-teleport="body">
            <div x-cloak x-show="settingsOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" role="presentation" data-app-content data-fp-settings-modal>
                <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-[1px]" @click="settingsOpen = false" aria-hidden="true"></div>
                <section
                    x-show="settingsOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-y-3 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="translate-y-3 opacity-0"
                    class="relative z-10 w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="fit-proper-settings-title"
                >
                    <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-sky-700">Fit &amp; Proper</p>
                            <h2 id="fit-proper-settings-title" class="mt-1 font-display text-xl font-bold text-slate-950">Pengaturan Standar Penilaian</h2>
                            <p class="mt-1 text-sm text-slate-500">Standar ini berlaku untuk kegiatan baru.</p>
                        </div>
                        <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" @click="settingsOpen = false" aria-label="Tutup pengaturan standar">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke-linecap="round" /></svg>
                        </button>
                    </header>

                    <form method="POST" action="{{ route('fit-proper.settings') }}" class="max-h-[calc(100vh-8rem)] overflow-y-auto p-5 md:max-h-none md:overflow-visible">
                        @csrf
                        @method('PUT')
                        <p class="mb-4 rounded-xl bg-sky-50 px-4 py-3 text-sm leading-5 text-sky-800">Bobot disimpan sebagai pecahan: 0,10 = 10%. Jumlah seluruh bobot harus 1.</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            @foreach (['weight_kpi' => 'Bobot KPI', 'weight_assessment' => 'Bobot asesmen', 'weight_fp' => 'Bobot Fit & Proper', 'max_kpi' => 'Skala maksimum KPI', 'max_assessment' => 'Skala maksimum asesmen', 'passing_grade' => 'Passing grade total'] as $key => $label)
                                <label class="text-sm font-semibold text-slate-700">
                                    {{ $label }}
                                    <input required type="number" step="any" name="{{ $key }}" value="{{ old($key, $standard[$key]) }}" class="mt-1.5 w-full rounded-xl border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                                    @error($key)<span class="mt-1 block text-xs font-medium text-red-600">{{ $message }}</span>@enderror
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-5 flex flex-col-reverse gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:justify-end">
                            <button type="button" class="ui-button ui-button-secondary" @click="settingsOpen = false">Batal</button>
                            <button type="submit" class="ui-button ui-button-primary">Simpan Standar</button>
                        </div>
                    </form>
                </section>
            </div>
        </template>
    @endcan
</div>
@endsection
