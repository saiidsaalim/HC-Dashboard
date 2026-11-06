<?php

namespace App\Http\Controllers\PerformanceTalent;

use App\Enums\CriticalPositionCoverageStatus;
use App\Enums\CriticalPositionCriticality;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCriticalPositionRequest;
use App\Http\Requests\UpdateCriticalPositionRequest;
use App\Models\CriticalPosition;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class CriticalPositionController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CriticalPosition::class);

        $search = trim((string) $request->query('search', ''));
        $directorateId = trim((string) $request->query('directorate_id', ''));
        $departmentId = trim((string) $request->query('department_id', ''));
        $jobLevel = trim((string) $request->query('job_level', ''));
        $criticality = CriticalPositionCriticality::tryFrom((string) $request->query('criticality', ''));
        $coverageStatus = CriticalPositionCoverageStatus::tryFrom((string) $request->query('coverage_status', ''));
        $successorStatus = trim((string) $request->query('successor_status', ''));
        $requestedPerPage = (int) $request->query('per_page', 20);
        $perPage = in_array($requestedPerPage, [20, 40, 60], true) ? $requestedPerPage : 20;

        $filteredQuery = $this->applyIndexFilters(
            CriticalPosition::query(),
            $search,
            $directorateId,
            $departmentId,
            $jobLevel,
            $criticality,
            $coverageStatus,
            $successorStatus,
        );

        $criticalPositions = (clone $filteredQuery)
            ->with([
                'assessor:id,name',
                'position' => fn ($query) => $query
                    ->withCount(['employees', 'successors'])
                    ->with([
                        'unit.department.directorate',
                        'section',
                        'employees:id,position_id,personal_number,sap',
                        'successors:id,target_position_id,name,sap,current_position,succession_readiness',
                    ]),
            ])
            ->orderBy(
                Position::query()->select('name')->whereColumn('positions.id', 'critical_positions.position_id'),
            )
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $criticalityCounts = (clone $filteredQuery)
            ->selectRaw('criticality, COUNT(*) as aggregate')
            ->groupBy('criticality')
            ->pluck('aggregate', 'criticality')
            ->map(fn (mixed $count): int => (int) $count);
        $coverageCounts = collect(CriticalPositionCoverageStatus::cases())
            ->mapWithKeys(fn (CriticalPositionCoverageStatus $status): array => [
                $status->value => (clone $filteredQuery)
                    ->whereHas('position', fn (Builder $query): Builder => $query->whereCoverageStatus($status))
                    ->count(),
            ]);
        $positionsWithoutSuccessor = (clone $filteredQuery)->whereDoesntHave('position.successors')->count();
        $positionsWithReadyNowSuccessor = (clone $filteredQuery)
            ->whereHas('position.successors', fn (Builder $query): Builder => $query
                ->whereRaw('LOWER(TRIM(succession_readiness)) = ?', ['ready now']))
            ->count();

        $summary = [
            'total' => (clone $filteredQuery)->count(),
            'high' => $criticalityCounts->get(CriticalPositionCriticality::High->value, 0),
            'vacant' => $coverageCounts->get(CriticalPositionCoverageStatus::Vacant->value, 0),
            'without_successor' => $positionsWithoutSuccessor,
        ];
        $chartData = [
            'criticality' => [
                'categories' => ['High', 'Medium', 'Low'],
                'series' => [
                    $criticalityCounts->get(CriticalPositionCriticality::High->value, 0),
                    $criticalityCounts->get(CriticalPositionCriticality::Medium->value, 0),
                    $criticalityCounts->get(CriticalPositionCriticality::Low->value, 0),
                ],
                'total' => $summary['total'],
            ],
            'coverage' => [
                'categories' => ['Covered', 'Partially Covered', 'Vacant', 'Over Capacity'],
                'series' => [
                    $coverageCounts->get(CriticalPositionCoverageStatus::Covered->value, 0),
                    $coverageCounts->get(CriticalPositionCoverageStatus::PartiallyCovered->value, 0),
                    $coverageCounts->get(CriticalPositionCoverageStatus::Vacant->value, 0),
                    $coverageCounts->get(CriticalPositionCoverageStatus::OverCapacity->value, 0),
                ],
            ],
            'successor' => [
                'categories' => ['Belum Ada Successor', 'Ready Now'],
                'series' => [$positionsWithoutSuccessor, $positionsWithReadyNowSuccessor],
                'total' => $summary['total'],
                'missing' => $positionsWithoutSuccessor,
            ],
        ];

        $positionOptions = Position::query()
            ->with(['unit.department.directorate', 'section', 'criticalPosition:id,position_id'])
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query->where('active', true)->whereDoesntHave('criticalPosition'))
                ->orWhereHas('criticalPosition'))
            ->orderBy('name')
            ->get();
        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name']);
        $departments = Department::query()
            ->when($directorateId !== '', fn (Builder $query): Builder => $query->where('directorate_id', $directorateId))
            ->orderBy('name')
            ->get(['id', 'directorate_id', 'name']);
        $jobLevels = array_keys(Position::JOB_LEVEL_PRIORITIES);

        return view('performance-talent.critical-positions.index', compact(
            'criticalPositions',
            'summary',
            'chartData',
            'positionOptions',
            'directorates',
            'departments',
            'jobLevels',
            'search',
            'directorateId',
            'departmentId',
            'jobLevel',
            'criticality',
            'coverageStatus',
            'successorStatus',
            'perPage',
        ));
    }

    private function applyIndexFilters(
        Builder $query,
        string $search,
        string $directorateId,
        string $departmentId,
        string $jobLevel,
        ?CriticalPositionCriticality $criticality,
        ?CriticalPositionCoverageStatus $coverageStatus,
        string $successorStatus,
    ): Builder {
        return $query
            ->when($search !== '', fn (Builder $query): Builder => $query->whereHas('position', fn (Builder $query): Builder => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('external_position_id', 'like', "%{$search}%")))
            ->when($directorateId !== '', fn (Builder $query): Builder => $query->whereHas(
                'position.unit.department',
                fn (Builder $query): Builder => $query->where('directorate_id', $directorateId),
            ))
            ->when($departmentId !== '', fn (Builder $query): Builder => $query->whereHas(
                'position.unit',
                fn (Builder $query): Builder => $query->where('department_id', $departmentId),
            ))
            ->when($jobLevel !== '', fn (Builder $query): Builder => $query->whereHas(
                'position',
                fn (Builder $query): Builder => $query->where('job_level', $jobLevel),
            ))
            ->when($criticality !== null, fn (Builder $query): Builder => $query->where('criticality', $criticality->value))
            ->when($coverageStatus !== null, fn (Builder $query): Builder => $query->whereHas(
                'position',
                fn (Builder $query): Builder => $query->whereCoverageStatus($coverageStatus),
            ))
            ->when($successorStatus === 'available', fn (Builder $query): Builder => $query->whereHas('position.successors'))
            ->when($successorStatus === 'missing', fn (Builder $query): Builder => $query->whereDoesntHave('position.successors'));
    }

    public function store(StoreCriticalPositionRequest $request): RedirectResponse
    {
        CriticalPosition::query()->create([
            ...$request->validated(),
            'assessed_by' => $request->user()?->id,
        ]);

        return to_route('performance-talent.critical-positions.index')
            ->with('status', 'Critical Position berhasil ditambahkan.');
    }

    public function update(UpdateCriticalPositionRequest $request, CriticalPosition $criticalPosition): RedirectResponse
    {
        $criticalPosition->update([
            ...$request->validated(),
            'assessed_by' => $criticalPosition->assessed_by ?? $request->user()?->id,
        ]);

        return to_route('performance-talent.critical-positions.index')
            ->with('status', 'Critical Position berhasil diperbarui.');
    }

    public function positionPreview(Request $request, Position $position): JsonResponse
    {
        Gate::authorize('viewAny', CriticalPosition::class);

        $canManage = $request->user()?->can('create', CriticalPosition::class)
            || $request->user()?->hasPermission('critical-positions.update');
        abort_unless($position->criticalPosition()->exists() || ($canManage && $position->active), 403);

        $position->load([
            'unit.department.directorate',
            'section',
            'employees:id,position_id,personal_number,sap',
            'successors:id,target_position_id,succession_readiness',
        ])->loadCount(['employees', 'successors']);

        return response()->json([
            'id' => $position->id,
            'name' => $position->name,
            'external_position_id' => $position->external_position_id,
            'job_level' => $position->job_level,
            'section' => $position->section?->name,
            'unit' => $position->unit->name,
            'department' => $position->unit->department->name,
            'directorate' => $position->unit->department->directorate?->name,
            'formation_count' => $position->formation_count,
            'coverage' => $position->coverageStatus()->label(),
            'successor_count' => $position->successorCount(),
            'incumbents' => $position->employees->map(function (Employee $employee) use ($position): array {
                $personalNumber = trim((string) $employee->personal_number);

                return [
                    'name' => $personalNumber !== '' ? $personalNumber : 'Nama belum tersedia',
                    'sap' => $employee->sap,
                    'position' => $position->name,
                ];
            })->values(),
        ]);
    }

    public function destroy(CriticalPosition $criticalPosition): RedirectResponse
    {
        Gate::authorize('delete', $criticalPosition);

        try {
            $criticalPosition->delete();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'critical_position' => 'Critical Position tidak dapat dihapus. Silakan coba kembali.',
            ]);
        }

        return to_route('performance-talent.critical-positions.index')
            ->with('status', 'Critical Position berhasil dihapus.');
    }
}
