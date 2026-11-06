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

        $criticalPositions = CriticalPosition::query()
            ->with([
                'assessor:id,name',
                'position' => fn ($query) => $query
                    ->withCount(['employees', 'successors'])
                    ->with([
                        'unit.department.directorate',
                        'section',
                        'employees:id,position_id,name,personal_number,sap',
                        'successors:id,target_position_id,name,sap,current_position,succession_readiness',
                    ]),
            ])
            ->when($search !== '', fn (Builder $query): Builder => $query->whereHas('position', fn (Builder $query): Builder => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
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
            ->when($successorStatus === 'missing', fn (Builder $query): Builder => $query->whereDoesntHave('position.successors'))
            ->orderBy(
                Position::query()->select('name')->whereColumn('positions.id', 'critical_positions.position_id'),
            )
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'total' => CriticalPosition::query()->count(),
            'high' => CriticalPosition::query()->where('criticality', CriticalPositionCriticality::High->value)->count(),
            'vacant' => CriticalPosition::query()->whereDoesntHave('position.employees')->count(),
            'without_successor' => CriticalPosition::query()->whereDoesntHave('position.successors')->count(),
        ];

        $positionOptions = Position::query()
            ->with(['unit.department.directorate', 'section', 'criticalPosition:id,position_id'])
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query->where('active', true)->whereDoesntHave('criticalPosition'))
                ->orWhereHas('criticalPosition'))
            ->orderBy('name')
            ->get();
        $directorates = Directorate::query()->orderBy('name')->get(['id', 'name']);
        $departments = Department::query()->orderBy('name')->get(['id', 'directorate_id', 'name']);
        $jobLevels = array_keys(Position::JOB_LEVEL_PRIORITIES);

        return view('performance-talent.critical-positions.index', compact(
            'criticalPositions',
            'summary',
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
        ));
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
            'employees:id,position_id,name,personal_number,sap',
            'successors:id,target_position_id,succession_readiness',
        ])->loadCount(['employees', 'successors']);

        return response()->json([
            'id' => $position->id,
            'name' => $position->name,
            'code' => $position->code,
            'external_position_id' => $position->external_position_id,
            'job_level' => $position->job_level,
            'section' => $position->section?->name,
            'unit' => $position->unit->name,
            'department' => $position->unit->department->name,
            'directorate' => $position->unit->department->directorate?->name,
            'formation_count' => $position->formation_count,
            'coverage' => $position->coverageStatus()->label(),
            'successor_count' => $position->successorCount(),
            'incumbents' => $position->employees->map(fn (Employee $employee): array => [
                'name' => filled($employee->name) ? $employee->name : 'Nama belum tersedia',
                'personal_number' => $employee->personal_number,
                'sap' => $employee->sap,
                'position' => $position->name,
            ])->values(),
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
