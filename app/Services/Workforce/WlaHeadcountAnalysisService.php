<?php

namespace App\Services\Workforce;

use App\Enums\WlaAssessmentStatus;
use App\Models\Employee;
use App\Models\WlaAssessment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WlaHeadcountAnalysisService
{
    /**
     * @param  array{period?: mixed, department_id?: mixed, unit_id?: mixed, position_id?: mixed, gap_status?: mixed}  $filters
     * @return array{
     *     periods: Collection<int, int>,
     *     selectedPeriod: int|null,
     *     filters: array{period: int|null, department_id: int|null, unit_id: int|null, position_id: int|null, gap_status: string|null},
     *     departments: Collection<int, array{id: int, name: string}>,
     *     units: Collection<int, array{id: int, department_id: int, name: string}>,
     *     positions: Collection<int, array{id: int, unit_id: int, name: string}>,
     *     rows: LengthAwarePaginator,
     *     summary: array{positions_analyzed: int, total_recommended: int, total_actual: int, total_shortage: int, total_surplus: int, balanced_positions: int, incomplete_snapshots: int}
     * }
     */
    public function analyze(array $filters, int $perPage = 15): array
    {
        $periods = WlaAssessment::query()
            ->where('status', WlaAssessmentStatus::Final->value)
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->map(fn (mixed $period): int => (int) $period)
            ->values();
        $selectedPeriod = $this->selectedPeriod($filters['period'] ?? null, $periods);
        $normalizedFilters = [
            'period' => $selectedPeriod,
            'department_id' => $this->positiveInteger($filters['department_id'] ?? null),
            'unit_id' => $this->positiveInteger($filters['unit_id'] ?? null),
            'position_id' => $this->positiveInteger($filters['position_id'] ?? null),
            'gap_status' => $this->gapStatus($filters['gap_status'] ?? null),
        ];

        if ($selectedPeriod === null) {
            return [
                'periods' => $periods,
                'selectedPeriod' => null,
                'filters' => $normalizedFilters,
                'departments' => collect(),
                'units' => collect(),
                'positions' => collect(),
                'rows' => $this->paginate(collect(), $perPage),
                'summary' => $this->summary(collect()),
            ];
        }

        $snapshotRows = WlaAssessment::query()
            ->where('status', WlaAssessmentStatus::Final->value)
            ->where('period', $selectedPeriod)
            ->orderBy('id')
            ->get(['id', 'period', 'final_snapshot'])
            ->map(fn (WlaAssessment $assessment): array => $this->snapshotRow($assessment));
        $options = $this->filterOptions($snapshotRows);
        $filteredRows = $this->applyOrganizationFilters($snapshotRows, $normalizedFilters);
        $positionIds = $filteredRows
            ->where('snapshot_complete', true)
            ->pluck('position_id')
            ->filter()
            ->unique()
            ->values();
        $employeeCounts = $this->employeeCounts($positionIds);
        $analyzedRows = $filteredRows
            ->map(fn (array $row): array => $this->withHeadcount($row, $employeeCounts))
            ->when(
                $normalizedFilters['gap_status'] !== null,
                fn (Collection $rows): Collection => $rows
                    ->where('status', $normalizedFilters['gap_status'])
                    ->values(),
            );
        $sortedRows = $this->sortRows($analyzedRows);

        return [
            'periods' => $periods,
            'selectedPeriod' => $selectedPeriod,
            'filters' => $normalizedFilters,
            'departments' => $options['departments'],
            'units' => $options['units'],
            'positions' => $options['positions'],
            'rows' => $this->paginate($sortedRows, $perPage),
            'summary' => $this->summary($analyzedRows),
        ];
    }

    /** @param Collection<int, int> $periods */
    private function selectedPeriod(mixed $requestedPeriod, Collection $periods): ?int
    {
        if ($periods->isEmpty()) {
            return null;
        }

        $requested = $this->positiveInteger($requestedPeriod);

        return $requested !== null && $periods->containsStrict($requested)
            ? $requested
            : $periods->first();
    }

    /** @return array<string, mixed> */
    private function snapshotRow(WlaAssessment $assessment): array
    {
        $snapshot = is_array($assessment->final_snapshot) ? $assessment->final_snapshot : [];
        $departmentId = $this->positiveInteger(data_get($snapshot, 'department.id'));
        $unitId = $this->positiveInteger(data_get($snapshot, 'unit.id'));
        $positionId = $this->positiveInteger(data_get($snapshot, 'position.id'));
        $departmentName = $this->nonEmptyString(data_get($snapshot, 'department.name'));
        $unitName = $this->nonEmptyString(data_get($snapshot, 'unit.name'));
        $positionName = $this->nonEmptyString(data_get($snapshot, 'position.name'));
        $fte = $this->decimalString(data_get($snapshot, 'fte'));
        $recommendedEmployees = $this->nonNegativeInteger(data_get($snapshot, 'recommended_employees'));
        $snapshotComplete = $departmentId !== null
            && $unitId !== null
            && $positionId !== null
            && $departmentName !== null
            && $unitName !== null
            && $positionName !== null
            && $fte !== null
            && $recommendedEmployees !== null;

        return [
            'assessment_id' => $assessment->id,
            'period' => (int) $assessment->period,
            'department_id' => $departmentId,
            'department_name' => $departmentName ?? 'Data snapshot tidak lengkap',
            'unit_id' => $unitId,
            'unit_name' => $unitName ?? 'Data snapshot tidak lengkap',
            'position_id' => $positionId,
            'position_name' => $positionName ?? 'Data snapshot tidak lengkap',
            'fte' => $fte,
            'recommended_employees' => $recommendedEmployees,
            'snapshot_complete' => $snapshotComplete,
            'actual_employees' => null,
            'gap' => null,
            'absolute_gap' => null,
            'gap_display' => '—',
            'status' => 'unavailable',
            'status_label' => 'Tidak tersedia',
            'unavailable_reason' => $snapshotComplete ? null : 'Data snapshot tidak lengkap',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{departments: Collection<int, array{id: int, name: string}>, units: Collection<int, array{id: int, department_id: int, name: string}>, positions: Collection<int, array{id: int, unit_id: int, name: string}>}
     */
    private function filterOptions(Collection $rows): array
    {
        return [
            'departments' => $rows
                ->filter(fn (array $row): bool => $row['department_id'] !== null)
                ->map(fn (array $row): array => ['id' => $row['department_id'], 'name' => $row['department_name']])
                ->unique('id')->sortBy('name')->values(),
            'units' => $rows
                ->filter(fn (array $row): bool => $row['unit_id'] !== null && $row['department_id'] !== null)
                ->map(fn (array $row): array => [
                    'id' => $row['unit_id'],
                    'department_id' => $row['department_id'],
                    'name' => $row['unit_name'],
                ])->unique('id')->sortBy('name')->values(),
            'positions' => $rows
                ->filter(fn (array $row): bool => $row['position_id'] !== null && $row['unit_id'] !== null)
                ->map(fn (array $row): array => [
                    'id' => $row['position_id'],
                    'unit_id' => $row['unit_id'],
                    'name' => $row['position_name'],
                ])->unique('id')->sortBy('name')->values(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array{period: int|null, department_id: int|null, unit_id: int|null, position_id: int|null, gap_status: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function applyOrganizationFilters(Collection $rows, array $filters): Collection
    {
        return $rows
            ->when($filters['department_id'] !== null, fn (Collection $items): Collection => $items
                ->where('department_id', $filters['department_id']))
            ->when($filters['unit_id'] !== null, fn (Collection $items): Collection => $items
                ->where('unit_id', $filters['unit_id']))
            ->when($filters['position_id'] !== null, fn (Collection $items): Collection => $items
                ->where('position_id', $filters['position_id']))
            ->values();
    }

    /**
     * @param  Collection<int, int>  $positionIds
     * @return Collection<int, int>
     */
    private function employeeCounts(Collection $positionIds): Collection
    {
        if ($positionIds->isEmpty()) {
            return collect();
        }

        return Employee::query()
            ->select('position_id', DB::raw('COUNT(*) as employee_count'))
            ->whereIn('position_id', $positionIds->all())
            ->groupBy('position_id')
            ->pluck('employee_count', 'position_id')
            ->mapWithKeys(fn (mixed $count, mixed $positionId): array => [(int) $positionId => (int) $count]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  Collection<int, int>  $employeeCounts
     * @return array<string, mixed>
     */
    private function withHeadcount(array $row, Collection $employeeCounts): array
    {
        if (! $row['snapshot_complete']) {
            return $row;
        }

        $actualEmployees = $employeeCounts->get($row['position_id'], 0);
        $gap = $row['recommended_employees'] - $actualEmployees;
        [$status, $statusLabel] = match (true) {
            $gap > 0 => ['shortage', 'Kekurangan'],
            $gap < 0 => ['surplus', 'Kelebihan'],
            default => ['balanced', 'Seimbang'],
        };

        return [
            ...$row,
            'actual_employees' => $actualEmployees,
            'gap' => $gap,
            'absolute_gap' => abs($gap),
            'gap_display' => $gap > 0 ? "+{$gap}" : (string) $gap,
            'status' => $status,
            'status_label' => $statusLabel,
            'unavailable_reason' => null,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function sortRows(Collection $rows): Collection
    {
        $ranks = ['shortage' => 0, 'balanced' => 1, 'surplus' => 2, 'unavailable' => 3];

        return $rows->sort(function (array $left, array $right) use ($ranks): int {
            $rankComparison = $ranks[$left['status']] <=> $ranks[$right['status']];
            if ($rankComparison !== 0) {
                return $rankComparison;
            }

            if ($left['status'] === 'shortage') {
                $gapComparison = $right['gap'] <=> $left['gap'];
                if ($gapComparison !== 0) {
                    return $gapComparison;
                }
            }

            if ($left['status'] === 'surplus') {
                $gapComparison = $left['gap'] <=> $right['gap'];
                if ($gapComparison !== 0) {
                    return $gapComparison;
                }
            }

            return [$left['department_name'], $left['unit_name'], $left['position_name'], $left['assessment_id']]
                <=> [$right['department_name'], $right['unit_name'], $right['position_name'], $right['assessment_id']];
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{positions_analyzed: int, total_recommended: int, total_actual: int, total_shortage: int, total_surplus: int, balanced_positions: int, incomplete_snapshots: int}
     */
    private function summary(Collection $rows): array
    {
        $validPositions = $rows
            ->where('snapshot_complete', true)
            ->unique('position_id')
            ->values();

        return [
            'positions_analyzed' => $validPositions->count(),
            'total_recommended' => (int) $validPositions->sum('recommended_employees'),
            'total_actual' => (int) $validPositions->sum('actual_employees'),
            'total_shortage' => (int) $validPositions->where('gap', '>', 0)->sum('gap'),
            'total_surplus' => (int) $validPositions->where('gap', '<', 0)->sum('absolute_gap'),
            'balanced_positions' => $validPositions->where('gap', 0)->count(),
            'incomplete_snapshots' => $rows->where('snapshot_complete', false)->count(),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function paginate(Collection $rows, int $perPage): LengthAwarePaginator
    {
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    private function positiveInteger(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $validated === false ? null : $validated;
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        return $validated === false ? null : $validated;
    }

    private function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function decimalString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return preg_match('/^\d+(?:\.\d+)?$/', $value) === 1 ? $value : null;
    }

    private function gapStatus(mixed $value): ?string
    {
        return is_string($value) && in_array($value, ['shortage', 'balanced', 'surplus'], true)
            ? $value
            : null;
    }
}
