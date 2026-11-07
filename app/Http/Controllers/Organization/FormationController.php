<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as FilterValidator;
use Illuminate\View\View;

class FormationController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('formation.view');

        $request->session()->put('active_workspace', 'hc-od-career-management');
        $jobLevels = $this->jobLevels();
        $validator = $this->filterValidator($request, jobLevels: $jobLevels);
        if ($validator->fails()) {
            return to_route('formasi')->withErrors($validator);
        }

        $filters = $validator->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = $this->perPage($request);
        $employeeCount = '(select count(*) from employees where employees.position_id = positions.id)';
        $query = Position::query()
            ->where('active', true)
            ->with(['unit.department.directorate', 'section'])
            ->withCount('employees')
            ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('external_position_id', 'like', "%{$search}%")));

        foreach (['unit_id', 'section_id'] as $field) {
            $query->when($filters[$field] ?? null, fn (Builder $query, mixed $id): Builder => $query->where($field, $id));
        }

        $query
            ->when($filters['department_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query
                ->whereHas('unit', fn (Builder $query): Builder => $query->where('department_id', $id)))
            ->when($filters['directorate_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query
                ->whereHas('unit.department', fn (Builder $query): Builder => $query->where('directorate_id', $id)))
            ->when(($filters['job_level'] ?? '') !== '', fn (Builder $query): Builder => $query
                ->where('job_level', $filters['job_level']))
            ->when($filters['confirmation'] ?? null, fn (Builder $query, string $confirmation): Builder => $query
                ->where('formation_is_confirmed', $confirmation === 'confirmed'));

        if (! empty($filters['gap_status'])) {
            $query->where('formation_is_confirmed', true);

            match ($filters['gap_status']) {
                'shortage' => $query->whereRaw("{$employeeCount} > 0 AND {$employeeCount} < positions.formation_count"),
                'fulfilled' => $query->whereRaw("{$employeeCount} = positions.formation_count"),
                'excess' => $query->whereRaw("{$employeeCount} > positions.formation_count"),
                'vacant' => $query->whereRaw("{$employeeCount} = 0 AND positions.formation_count > 0"),
            };
        }

        $positions = $query->orderBy('name')->orderBy('id')->paginate($perPage)->withQueryString();
        if ($positions->currentPage() > $positions->lastPage()) {
            $request->session()->reflash();

            return to_route('formasi', [...$request->query(), 'page' => $positions->lastPage()]);
        }

        return view('pages.formasi', [
            'positions' => $positions,
            'summary' => $this->summary(),
            'unmappedEmployeeCount' => Employee::query()->whereNull('position_id')->count(),
            'directorates' => Directorate::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->with('directorate:id,name')->orderBy('name')->get(),
            'units' => Unit::query()->with('department:id,name,directorate_id')->orderBy('name')->get(),
            'sections' => Section::query()->with('unit.department:id,name,directorate_id')->orderBy('name')->get(),
            'jobLevels' => $jobLevels,
            'filters' => $filters,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function structure(Request $request): View|RedirectResponse
    {
        Gate::authorize('formation.view');

        $request->session()->put('active_workspace', 'hc-od-career-management');
        $validator = $this->filterValidator($request, true);
        if ($validator->fails()) {
            return to_route('formasi.structure')->withErrors($validator);
        }

        $filters = $validator->validated();
        $departments = Department::query()
            ->with(['directorate', 'units.sections', 'units.positions.parent', 'units.positions.section'])
            ->when($filters['department_id'] ?? null, fn (Builder $query, mixed $id): Builder => $query->whereKey($id))
            ->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();
        $positionTrees = [];

        foreach ($departments as $department) {
            $positions = $department->units->flatMap(fn (Unit $unit): Collection => $unit->positions)
                ->sortBy('sort_order')->keyBy('id');
            $positionTrees[$department->id] = $this->positionTree($positions);
        }

        return view('pages.formasi-structure', [
            'departments' => $departments,
            'emptyDirectorates' => empty($filters['department_id'])
                ? Directorate::query()->whereDoesntHave('departments')->orderBy('name')->get()
                : collect(),
            'departmentOptions' => Department::query()->orderBy('name')->get(['id', 'name']),
            'departmentId' => $filters['department_id'] ?? null,
            'positionTrees' => $positionTrees,
        ]);
    }

    /** @param list<string> $jobLevels */
    private function filterValidator(Request $request, bool $structure = false, array $jobLevels = []): FilterValidator
    {
        $rules = ['department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')]];
        if (! $structure) {
            $rules += [
                'search' => ['nullable', 'string', 'max:255'],
                'job_level' => ['nullable', 'string', Rule::in($jobLevels)],
                'directorate_id' => ['nullable', 'integer', Rule::exists('directorates', 'id')],
                'unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
                'section_id' => ['nullable', 'integer', Rule::exists('sections', 'id')],
                'confirmation' => ['nullable', Rule::in(['confirmed', 'unconfirmed'])],
                'gap_status' => ['nullable', Rule::in(['shortage', 'fulfilled', 'excess', 'vacant'])],
            ];
        }

        $validator = Validator::make($request->query(), $rules, [
            'search.string' => 'Pencarian harus berupa teks.',
            'search.max' => 'Pencarian maksimal 255 karakter.',
            'job_level.string' => 'Level yang dipilih tidak valid.',
            'job_level.in' => 'Level yang dipilih tidak tersedia.',
            'department_id.integer' => 'Department yang dipilih tidak valid.',
            'department_id.exists' => 'Department yang dipilih tidak tersedia.',
            'directorate_id.integer' => 'Directorate yang dipilih tidak valid.',
            'directorate_id.exists' => 'Directorate yang dipilih tidak tersedia.',
            'unit_id.integer' => 'Unit yang dipilih tidak valid.',
            'unit_id.exists' => 'Unit yang dipilih tidak tersedia.',
            'section_id.integer' => 'Section yang dipilih tidak valid.',
            'section_id.exists' => 'Section yang dipilih tidak tersedia.',
            'confirmation.in' => 'Status penetapan tidak valid.',
            'gap_status.in' => 'Status gap tidak valid.',
        ]);
        if ($structure) {
            return $validator;
        }

        return $validator->after(function (FilterValidator $validator) use ($request): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $directorateId = $request->integer('directorate_id');
            $departmentId = $request->integer('department_id');
            $unitId = $request->integer('unit_id');
            $sectionId = $request->integer('section_id');
            if ($directorateId && $departmentId && ! Department::query()->whereKey($departmentId)->where('directorate_id', $directorateId)->exists()) {
                $validator->errors()->add('department_id', 'Department tidak berada pada Directorate yang dipilih.');
            }

            if ($unitId && ! Unit::query()->whereKey($unitId)
                ->when($departmentId, fn (Builder $query): Builder => $query->where('department_id', $departmentId))
                ->when($directorateId, fn (Builder $query): Builder => $query->whereHas('department', fn (Builder $query): Builder => $query->where('directorate_id', $directorateId)))
                ->exists()) {
                $validator->errors()->add('unit_id', 'Unit tidak berada pada organisasi yang dipilih.');
            }

            if ($sectionId && ! Section::query()->whereKey($sectionId)
                ->when($unitId, fn (Builder $query): Builder => $query->where('unit_id', $unitId))
                ->when($departmentId, fn (Builder $query): Builder => $query->whereHas('unit', fn (Builder $query): Builder => $query->where('department_id', $departmentId)))
                ->when($directorateId, fn (Builder $query): Builder => $query->whereHas('unit.department', fn (Builder $query): Builder => $query->where('directorate_id', $directorateId)))
                ->exists()) {
                $validator->errors()->add('section_id', 'Section tidak berada pada organisasi yang dipilih.');
            }

            if ($request->input('confirmation') === 'unconfirmed' && $request->filled('gap_status')) {
                $validator->errors()->add('gap_status', 'Status gap hanya berlaku untuk kebutuhan yang sudah ditetapkan.');
            }
        });
    }

    /** @return list<string> */
    private function jobLevels(): array
    {
        $storedLevels = Position::query()->whereNotNull('job_level')->where('job_level', '<>', '')
            ->distinct()->orderBy('job_level')->pluck('job_level')
            ->filter(fn (string $level): bool => trim($level) !== '')
            ->all();

        return array_values(array_unique([...array_keys(Position::JOB_LEVEL_PRIORITIES), ...$storedLevels]));
    }

    /** @return array{total: int, confirmed: int, unconfirmed: int, shortage: int, excess: int} */
    private function summary(): array
    {
        $positions = Position::query()->where('active', true)
            ->select(['id', 'formation_count', 'formation_is_confirmed'])->withCount('employees');
        $counts = DB::query()->fromSub($positions, 'formation_positions')
            ->selectRaw('COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN formation_is_confirmed = 1 THEN 1 ELSE 0 END), 0) AS confirmed,
                COALESCE(SUM(CASE WHEN formation_is_confirmed = 1 AND formation_count > employees_count THEN formation_count - employees_count ELSE 0 END), 0) AS shortage,
                COALESCE(SUM(CASE WHEN formation_is_confirmed = 1 AND employees_count > formation_count THEN employees_count - formation_count ELSE 0 END), 0) AS excess')
            ->first();

        return [
            'total' => (int) $counts->total,
            'confirmed' => (int) $counts->confirmed,
            'unconfirmed' => (int) $counts->total - (int) $counts->confirmed,
            'shortage' => (int) $counts->shortage,
            'excess' => (int) $counts->excess,
        ];
    }

    /**
     * @param  Collection<int, Position>  $positions
     * @return array{roots: Collection<int, Position>, children: Collection<int, Collection<int, Position>>, unresolved: Collection<int, Position>}
     */
    private function positionTree(Collection $positions): array
    {
        $children = $positions->groupBy('parent_position_id');
        $roots = $positions->filter(fn (Position $position): bool => $position->parent_position_id === null
            || ! $positions->has($position->parent_position_id));
        $visited = [];
        $pending = $roots->keys()->all();

        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($visited[$id])) {
                continue;
            }

            $visited[$id] = true;
            foreach ($children->get($id, collect()) as $child) {
                $pending[] = $child->id;
            }
        }

        return ['roots' => $roots, 'children' => $children, 'unresolved' => $positions->except(array_keys($visited))];
    }
}
