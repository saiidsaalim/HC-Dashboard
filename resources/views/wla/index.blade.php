@extends('layouts.app')

@section('page-title', 'WLA Assessment')

@section('content')
    @php
        $finalRecords = $analyticsAssessments->map(function ($wla): array {
            $isFinal = $wla->status === \App\Enums\WlaAssessmentStatus::Final;
            $snapshot = $isFinal && is_array($wla->final_snapshot) ? $wla->final_snapshot : null;
            $snapshotActivities = data_get($snapshot, 'activities');

            return [
                'is_final' => $isFinal,
                'period' => (int) ($isFinal ? data_get($snapshot, 'period', $wla->period) : $wla->period),
                'unit' => (string) ($isFinal ? data_get($snapshot, 'unit.name', 'Unit tidak tersedia') : $wla->unit->name),
                'scope_key' => implode('|', [
                    $isFinal ? data_get($snapshot, 'period', $wla->period) : $wla->period,
                    $isFinal ? data_get($snapshot, 'department.id', data_get($snapshot, 'department.name')) : $wla->department_id,
                    $isFinal ? data_get($snapshot, 'unit.id', data_get($snapshot, 'unit.name')) : $wla->unit_id,
                    $isFinal ? data_get($snapshot, 'position.id', data_get($snapshot, 'position.name')) : $wla->position_id,
                ]),
                'workload' => $isFinal && data_get($snapshot, 'total_annual_workload') !== null
                    ? (float) data_get($snapshot, 'total_annual_workload')
                    : null,
                'fte' => $isFinal && data_get($snapshot, 'fte') !== null
                    ? (float) data_get($snapshot, 'fte')
                    : null,
                'recommended' => $isFinal && data_get($snapshot, 'recommended_employees') !== null
                    ? (int) data_get($snapshot, 'recommended_employees')
                    : null,
                'activities' => $isFinal && is_array($snapshotActivities) ? count($snapshotActivities) : null,
            ];
        })->values();
        $hasAmbiguousFinalScope = $finalRecords->groupBy('scope_key')
            ->contains(fn ($records): bool => $records->count() > 1);
        $canAggregateResults = $finalRecords->isNotEmpty()
            && ! $hasAmbiguousFinalScope
            && $finalRecords->every(fn (array $record): bool => $record['workload'] !== null
                && $record['fte'] !== null
                && $record['recommended'] !== null);
        $canAggregateActivities = $canAggregateResults
            && $finalRecords->every(fn (array $record): bool => $record['activities'] !== null);

        $totalActivities = $canAggregateActivities ? $finalRecords->sum('activities') : null;
        $totalWorkload = $canAggregateResults ? $finalRecords->sum('workload') : null;
        $totalFte = $canAggregateResults ? $finalRecords->sum('fte') : null;
        $totalRecommended = $canAggregateResults ? $finalRecords->sum('recommended') : null;

        $unitWorkloads = $canAggregateResults
            ? $finalRecords->where('workload', '>', 0)->groupBy('unit')->map->sum('workload')->sortDesc()
            : collect();
        $topUnitWorkloads = $unitWorkloads->take(5);
        $otherUnitWorkload = $unitWorkloads->slice(5)->sum();
        if ($otherUnitWorkload > 0) {
            $topUnitWorkloads->put('Lainnya', $otherUnitWorkload);
        }
        $distributionChart = [
            'categories' => $topUnitWorkloads->keys()->values()->all(),
            'series' => $topUnitWorkloads->values()->map(fn ($value): float => round((float) $value, 4))->all(),
            'total' => round((float) $unitWorkloads->sum(), 4),
        ];

        $workloadTrend = $canAggregateResults
            ? $finalRecords->groupBy('period')->map->sum('workload')->sortKeys()
            : collect();
        $trendChart = [
            'categories' => $workloadTrend->keys()->map(fn ($period): string => (string) $period)->values()->all(),
            'series' => $workloadTrend->values()->map(fn ($value): float => round((float) $value, 4))->all(),
        ];

        $unitComparison = $canAggregateResults
            ? $finalRecords->groupBy('unit')->map(fn ($records): array => [
                'fte' => round((float) $records->sum('fte'), 6),
                'recommended' => (int) $records->sum('recommended'),
            ])->sortByDesc('recommended')->take(8)
            : collect();
        $comparisonChart = [
            'categories' => $unitComparison->keys()->values()->all(),
            'fte' => $unitComparison->pluck('fte')->values()->all(),
            'recommended' => $unitComparison->pluck('recommended')->values()->all(),
        ];

        $analyticsEmptyMessage = $hasAmbiguousFinalScope
            ? 'Agregasi dinonaktifkan karena terdapat lebih dari satu WLA Final untuk periode, unit, dan position yang sama.'
            : ($finalRecords->isEmpty()
                ? 'Belum tersedia WLA Final untuk dianalisis.'
                : 'Snapshot WLA Final belum lengkap untuk membentuk analitik ini.');
    @endphp

    <div data-wla-dashboard class="space-y-5">
        <div class="flex w-full flex-col justify-end gap-2.5 sm:flex-row sm:items-center">
            <form method="GET" action="{{ route('wla') }}" class="relative w-full sm:w-auto">
                <input type="hidden" name="per_page" value="{{ $perPage }}">
                <label for="wla-year" class="sr-only">Filter tahun assessment</label>
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-blue-700" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 2v3m10-3v3M3 8h14M4 4h12a1 1 0 0 1 1 1v12H3V5a1 1 0 0 1 1-1Z" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
                <select id="wla-year" name="year" onchange="this.form.submit()" class="h-[42px] w-full appearance-none rounded-xl border-slate-300 bg-white py-2 pl-10 pr-9 text-sm font-bold text-slate-700 shadow-sm transition hover:border-blue-300 focus:border-blue-500 focus:ring-blue-500 sm:w-auto" aria-label="Tampilkan assessment berdasarkan tahun">
                    <option value="">Semua Tahun</option>
                    @foreach ($availableYears as $year)
                        <option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }}</option>
                    @endforeach
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </span>
            </form>
            @can('viewAny', \App\Models\WlaAssessment::class)
                <a href="{{ route('wla.headcount-analysis') }}" class="ui-button ui-button-secondary w-full border-slate-300 bg-white sm:w-auto">
                    <x-dashboard-icon name="chart" class="h-5 w-5" />
                    Analisis Headcount
                </a>
            @endcan
            @can('create', \App\Models\WlaAssessment::class)
                <a href="{{ route('wla.create') }}" class="ui-button ui-button-primary w-full shadow-lg shadow-blue-950/15 sm:w-auto">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M10 4v12M4 10h12" stroke-linecap="round" />
                    </svg>
                    Assessment Baru
                </a>
            @endcan
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
        @endif

        <section aria-labelledby="wla-kpi-title">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 id="wla-kpi-title" class="font-display text-lg font-bold text-slate-950">Ringkasan WLA</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Nilai hasil menggunakan snapshot assessment berstatus Final{{ $selectedYear === null ? '' : ' tahun '.$selectedYear }}.</p>
                </div>
                @if ($canAggregateResults)
                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $finalRecords->count() }} WLA Final</span>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                <article data-wla-kpi-card class="relative overflow-hidden rounded-2xl border border-blue-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="absolute inset-x-0 top-0 h-1 bg-blue-600"></div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-700"><x-dashboard-icon name="document" /></span>
                        <div class="min-w-0"><p class="text-sm font-semibold text-slate-600">Total Assessment</p><p class="mt-1 font-display text-2xl font-bold text-slate-950">{{ number_format($assessments->total(), 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">Seluruh status</p></div>
                    </div>
                </article>

                <article data-wla-kpi-card class="relative overflow-hidden rounded-2xl border border-sky-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="absolute inset-x-0 top-0 h-1 bg-sky-500"></div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-sky-50 text-sky-700"><x-dashboard-icon name="users" /></span>
                        <div class="min-w-0"><p class="text-sm font-semibold text-slate-600">Total Aktivitas</p><p class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $totalActivities === null ? '—' : number_format($totalActivities, 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">Snapshot WLA Final</p></div>
                    </div>
                </article>

                <article data-wla-kpi-card class="relative overflow-hidden rounded-2xl border border-amber-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="absolute inset-x-0 top-0 h-1 bg-amber-500"></div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-700">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </span>
                        <div class="min-w-0"><p class="text-sm font-semibold text-slate-600">Total Beban Kerja</p><p class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $totalWorkload === null ? '—' : number_format($totalWorkload, 1, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">Jam/Tahun</p></div>
                    </div>
                </article>

                <article data-wla-kpi-card class="relative overflow-hidden rounded-2xl border border-violet-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="absolute inset-x-0 top-0 h-1 bg-violet-500"></div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-violet-50 text-violet-700"><x-dashboard-icon name="chart" /></span>
                        <div class="min-w-0"><p class="text-sm font-semibold text-slate-600">Total FTE</p><p class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $totalFte === null ? '—' : number_format($totalFte, 1, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">Kebutuhan tenaga kerja</p></div>
                    </div>
                </article>

                <article data-wla-kpi-card class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:col-span-2 lg:col-span-1">
                    <div class="absolute inset-x-0 top-0 h-1 bg-emerald-500"></div>
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700"><x-dashboard-icon name="formasi" /></span>
                        <div class="min-w-0"><p class="text-sm font-semibold text-slate-600">Rekomendasi Formasi</p><p class="mt-1 font-display text-2xl font-bold text-slate-950">{{ $totalRecommended === null ? '—' : number_format($totalRecommended, 0, ',', '.') }}</p><p class="mt-1 text-xs text-slate-500">Pegawai direkomendasikan</p></div>
                    </div>
                </article>
            </div>
        </section>

        <section aria-label="Grafik analisis WLA" class="grid min-w-0 gap-4 xl:grid-cols-3">
            <article data-wla-chart-card class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div><h3 class="font-display font-bold text-slate-950">Distribusi Beban Kerja per Unit</h3><p class="mt-1 text-xs text-slate-500">Top 5 unit WLA Final dan kategori lainnya.</p></div>
                    <span class="rounded-xl bg-blue-50 p-2 text-blue-700"><x-dashboard-icon name="users" /></span>
                </div>
                @if ($distributionChart['series'] !== [])
                    <div data-wla-distribution-chart data-chart='@json($distributionChart)' class="mt-3 min-h-[300px]"></div>
                @else
                    <div class="flex min-h-[300px] items-center justify-center px-5 text-center text-sm leading-6 text-slate-500">{{ $canAggregateResults ? 'WLA Final belum memiliki beban kerja untuk didistribusikan.' : $analyticsEmptyMessage }}</div>
                @endif
            </article>

            <article data-wla-chart-card class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div><h3 class="font-display font-bold text-slate-950">Tren Total Beban Kerja</h3><p class="mt-1 text-xs text-slate-500">Perkembangan berdasarkan periode WLA Final.</p></div>
                    <span class="rounded-xl bg-sky-50 p-2 text-sky-700"><x-dashboard-icon name="chart" /></span>
                </div>
                @if (count($trendChart['categories']) >= 2)
                    <div data-wla-trend-chart data-chart='@json($trendChart)' class="mt-3 min-h-[300px]"></div>
                @else
                    <div class="flex min-h-[300px] items-center justify-center px-5 text-center text-sm leading-6 text-slate-500">{{ $canAggregateResults ? 'Minimal dua periode WLA Final diperlukan untuk menampilkan tren.' : $analyticsEmptyMessage }}</div>
                @endif
            </article>

            <article data-wla-chart-card class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div><h3 class="font-display font-bold text-slate-950">FTE vs Rekomendasi</h3><p class="mt-1 text-xs text-slate-500">Maksimal 8 unit berdasarkan rekomendasi formasi.</p></div>
                    <span class="rounded-xl bg-violet-50 p-2 text-violet-700"><x-dashboard-icon name="formasi" /></span>
                </div>
                @if ($comparisonChart['categories'] !== [])
                    <div data-wla-comparison-chart data-chart='@json($comparisonChart)' class="mt-3 min-h-[300px]"></div>
                @else
                    <div class="flex min-h-[300px] items-center justify-center px-5 text-center text-sm leading-6 text-slate-500">{{ $analyticsEmptyMessage }}</div>
                @endif
            </article>
        </section>

        <section data-wla-table class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="wla-table-title">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h3 id="wla-table-title" class="font-display text-lg font-bold text-slate-950">Daftar Assessment</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Menampilkan {{ $assessments->firstItem() ?? 0 }}–{{ $assessments->lastItem() ?? 0 }} dari {{ number_format($assessments->total(), 0, ',', '.') }} assessment{{ $selectedYear === null ? '' : ' tahun '.$selectedYear }}.</p>
                </div>
                <span class="w-fit rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Urutan periode terbaru</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1280px] text-left text-sm">
                    <thead class="bg-slate-950 text-xs uppercase tracking-wide text-slate-200">
                        <tr>
                            <th class="w-16 px-4 py-3.5">No</th><th class="px-4 py-3.5">Tanggal</th><th class="px-4 py-3.5">Department</th><th class="px-4 py-3.5">Unit</th><th class="px-4 py-3.5">Position</th><th class="px-4 py-3.5">Schedule</th><th class="px-4 py-3.5">Calendar</th><th class="px-4 py-3.5">Efficiency</th><th class="px-4 py-3.5">FTE</th><th class="px-4 py-3.5">Rekomendasi</th><th class="px-4 py-3.5">Status</th><th class="px-4 py-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($assessments as $wla)
                            @php
                                $isFinal = $wla->status === \App\Enums\WlaAssessmentStatus::Final;
                                $snapshot = $isFinal ? $wla->final_snapshot : null;
                                $efficiency = $isFinal ? data_get($snapshot, 'efficiency_factor') : $wla->efficiency_factor;
                                $fte = $isFinal ? data_get($snapshot, 'fte') : $wla->fte;
                            @endphp
                            <tr data-wla-row class="transition-colors hover:bg-slate-50/80">
                                <td class="px-4 py-3.5 font-semibold text-slate-700">{{ ($assessments->firstItem() ?? 1) + $loop->index }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-slate-600"><time datetime="{{ $wla->created_at->toDateString() }}">{{ $wla->created_at->format('d M Y') }}</time></td>
                                <td class="px-4 py-3.5 text-slate-600">{{ $isFinal ? data_get($snapshot, 'department.name', 'Snapshot tidak tersedia') : $wla->department->name }}</td>
                                <td class="px-4 py-3.5 text-slate-600">{{ $isFinal ? data_get($snapshot, 'unit.name', 'Snapshot tidak tersedia') : $wla->unit->name }}</td>
                                <td class="px-4 py-3.5 text-slate-600">{{ $isFinal ? data_get($snapshot, 'position.name', 'Snapshot tidak tersedia') : $wla->position->name }}</td>
                                <td class="px-4 py-3.5 text-slate-600">{{ $isFinal ? data_get($snapshot, 'schedule.name', 'Snapshot tidak tersedia') : $wla->workSchedule->name }}</td>
                                <td class="px-4 py-3.5 text-slate-600">{{ $isFinal ? data_get($snapshot, 'calendar.year', '—') : $wla->workCalendar->year }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 font-semibold text-slate-700">{{ $efficiency === null ? '—' : number_format((float) $efficiency * 100, 1, ',', '.').'%' }}</td>
                                <td class="px-4 py-3.5 font-semibold text-slate-700">{{ $fte === null ? '—' : number_format((float) $fte, 1, ',', '.') }}</td>
                                <td class="px-4 py-3.5 font-semibold text-slate-700">{{ $isFinal ? data_get($snapshot, 'recommended_employees', '—') : $wla->recommended_employees }}</td>
                                <td class="px-4 py-3.5"><x-ui.status-badge :tone="$isFinal ? 'success' : 'warning'">{{ ucfirst($wla->status->value) }}</x-ui.status-badge></td>
                                <td class="px-4 py-3.5">
                                    <div class="flex justify-end gap-1.5">
                                        <a class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 font-semibold text-blue-700 transition hover:bg-blue-50 hover:text-blue-900" href="{{ route('wla.show', $wla) }}"><x-dashboard-icon name="eye" class="h-4 w-4" /><span>Detail</span></a>
                                        @can('update', $wla)
                                            <a class="inline-flex h-9 items-center gap-1.5 rounded-lg px-2.5 font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900" href="{{ route('wla.edit', $wla) }}"><x-dashboard-icon name="edit" class="h-4 w-4" /><span>Edit</span></a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <x-ui.empty-state colspan="12" title="Belum ada assessment" description="Buat assessment baru untuk memulai analisis beban kerja." />
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <x-ui.per-page-selector :per-page="$perPage" id="wla-assessment-per-page" />
                @if ($assessments->hasPages())<div>{{ $assessments->links() }}</div>@endif
            </div>
        </section>
    </div>
@endsection
