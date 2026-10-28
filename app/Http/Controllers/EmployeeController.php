<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeSpreadsheet;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Imports\EmployeeImport;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Employee;
use App\Models\EmployeeImportBatch;
use App\Models\EmployeeImportRow;
use App\Models\Position;
use App\Models\Section;
use App\Models\Unit;
use App\Models\User;
use App\Services\Employees\EmployeeImportStagingService;
use App\Services\Employees\EmployeeOrganizationSyncService;
use App\Services\Users\StaffUserProvisioningService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeController extends Controller
{
    /** @var array<string, string> */
    private const WORKSPACE_FEATURE_VIEWS = [
        'performance-talent.data-pegawai.index' => 'performance-talent.features.data-pegawai',
    ];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $routeName = $request->route()?->getName() ?? 'data-pegawai';
        $workspaceFeatureView = self::WORKSPACE_FEATURE_VIEWS[$routeName] ?? null;

        if ($workspaceFeatureView !== null) {
            $request->session()->put('active_workspace', 'performance-talent-management');
        }

        $search = trim((string) $request->input('search', ''));
        $validatedFilters = $request->validate([
            'department_id' => ['nullable', 'integer', 'required_with:unit_id,position_id', Rule::exists(Department::class, 'id')],
            'unit_id' => ['nullable', 'integer', 'required_with:position_id', Rule::exists(Unit::class, 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists(Position::class, 'id')],
            'sort_by' => ['nullable', 'string', Rule::in(['latest', 'organic_newest', 'organic_oldest'])],
        ]);
        $departmentId = isset($validatedFilters['department_id']) ? (int) $validatedFilters['department_id'] : null;
        $unitId = isset($validatedFilters['unit_id']) ? (int) $validatedFilters['unit_id'] : null;
        $positionId = isset($validatedFilters['position_id']) ? (int) $validatedFilters['position_id'] : null;
        $sortBy = $validatedFilters['sort_by'] ?? 'latest';

        if ($unitId !== null && Unit::query()
            ->whereKey($unitId)
            ->where('department_id', $departmentId)
            ->doesntExist()) {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit yang dipilih tidak berada dalam Department tersebut.',
            ]);
        }

        if ($positionId !== null && Position::query()
            ->whereKey($positionId)
            ->where('unit_id', $unitId)
            ->doesntExist()) {
            throw ValidationException::withMessages([
                'position_id' => 'Position yang dipilih tidak berada dalam Unit tersebut.',
            ]);
        }

        $employeeQuery = Employee::query()->with(['organizationDepartment', 'unit', 'organizationPosition']);

        if ($search !== '') {
            $employeeQuery->where(function (Builder $query) use ($search): void {
                foreach (['sap', 'personal_number', 'txt_dept', 'position', 'id_number', 'email'] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if ($departmentId !== null) {
            $employeeQuery->where('department_id', $departmentId);
        }

        if ($unitId !== null) {
            $employeeQuery->where('unit_id', $unitId);
        }

        if ($positionId !== null) {
            $employeeQuery->where('position_id', $positionId);
        }

        if ($sortBy === 'organic_newest' || $sortBy === 'organic_oldest') {
            $employeeQuery
                ->orderByRaw('CASE WHEN organilk IS NULL THEN 1 ELSE 0 END')
                ->orderBy('organilk', $sortBy === 'organic_newest' ? 'desc' : 'asc')
                ->orderByDesc('id');
        } else {
            $employeeQuery->latest();
        }

        $employees = $employeeQuery->paginate(20)->withQueryString();
        $departmentIds = $employees->pluck('department_id')->filter()->merge($departmentId !== null ? [$departmentId] : [])->unique()->all();
        $unitIds = $employees->pluck('unit_id')->filter()->merge($unitId !== null ? [$unitId] : [])->unique()->all();
        $positionIds = $employees->pluck('position_id')->filter()->merge($positionId !== null ? [$positionId] : [])->unique()->all();
        $organizationDirectorates = Directorate::query()->where('active', true)->orderBy('name')->get();
        $organizationDepartments = Department::query()->where('active', true)->orWhereIn('id', $departmentIds)->orderBy('name')->get();
        $organizationUnits = Unit::query()->with('department')->where('active', true)->orWhereIn('id', $unitIds)->orderBy('name')->get();
        $organizationSections = Section::query()->with('unit')->where('active', true)->orderBy('name')->get();
        $organizationPositions = Position::query()->with(['unit.department', 'section'])->where('active', true)->orWhereIn('id', $positionIds)->orderBy('name')->get();
        $filterUnits = $departmentId === null
            ? collect()
            : $organizationUnits->where('department_id', $departmentId)->values();
        $filterPositions = $unitId === null
            ? collect()
            : $organizationPositions->where('unit_id', $unitId)->values();

        $viewData = [
            'employees' => $employees,
            'search' => $search,
            'departmentId' => (string) ($departmentId ?? ''),
            'unitId' => (string) ($unitId ?? ''),
            'positionId' => (string) ($positionId ?? ''),
            'sortBy' => $sortBy,
            'organizationDirectorates' => $organizationDirectorates,
            'organizationDepartments' => $organizationDepartments,
            'organizationUnits' => $organizationUnits,
            'organizationSections' => $organizationSections,
            'organizationPositions' => $organizationPositions,
            'filterUnits' => $filterUnits,
            'filterPositions' => $filterPositions,
            'employeeIndexRoute' => $workspaceFeatureView === null ? 'data-pegawai' : $routeName,
        ];

        if ($workspaceFeatureView !== null) {
            return view('performance-talent.show', array_merge($viewData, [
                'title' => 'Data Pegawai',
                'description' => 'Kelola data utama dan informasi pegawai.',
                'featureView' => $workspaceFeatureView,
            ]));
        }

        return view('pages.data-pegawai', $viewData);
    }

    public function print(): View
    {
        return view('pages.data-pegawai-print', [
            'employees' => Employee::query()
                ->with(['organizationDepartment', 'unit', 'organizationPosition'])
                ->orderBy('sap')
                ->get(),
            'fieldGroups' => EmployeeSpreadsheet::FIELD_GROUPS,
            'printedAt' => now(),
        ]);
    }

    public function exportExcel(EmployeeSpreadsheet $spreadsheet): BinaryFileResponse
    {
        return $spreadsheet->download();
    }

    public function organizationSyncPreview(Request $request, EmployeeOrganizationSyncService $service): View
    {
        abort_unless($request->user()?->hasPermission('employees.sync-organization'), 403);

        return view('pages.employee-organization-sync', [
            'result' => $service->audit(),
        ]);
    }

    public function organizationSyncApply(Request $request, EmployeeOrganizationSyncService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('employees.sync-organization'), 403);

        try {
            $result = $service->apply();
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'organization_sync' => $exception->getMessage(),
            ]);
        }

        $applied = $result['applied'];

        return to_route('data-pegawai.organization-sync.preview')->with(
            'status',
            sprintf(
                'Sinkronisasi selesai: %d pegawai dan %d foreign key diperbarui; %d Directorate, %d Department, %d Unit, %d Section, dan %d Position dibuat.',
                $applied['employees_updated'],
                $applied['foreign_keys_updated'],
                $applied['directorates_created'],
                $applied['departments_created'],
                $applied['units_created'],
                $applied['sections_created'],
                $applied['positions_created'],
            ),
        );
    }

    public function upload(Request $request, EmployeeImport $employeeImport): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasPermission('employees.import'), 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        $batch = $employeeImport->import($validated['file'], $actor);

        return to_route('data-pegawai')->with(
            'status',
            "File disimpan untuk review. Batch #{$batch->id}: {$batch->total_rows} baris, {$batch->invalid_rows} invalid.",
        )->with('import_batch_id', $batch->id);
    }

    public function importReviewIndex(Request $request): View
    {
        $this->authorizeImportReview($request);
        $batches = EmployeeImportBatch::query()
            ->orderByDesc('imported_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('pages.employee-import-review-index', compact('batches'));
    }

    public function importReviewShow(Request $request, EmployeeImportBatch $batch): View
    {
        $this->authorizeImportReview($request);
        $filter = $request->query('filter', 'all');
        if (! in_array($filter, ['all', 'invalid'], true)) {
            $filter = 'all';
        }

        $rowsQuery = $batch->rows()->orderBy('source_row');
        if ($filter === 'invalid') {
            $rowsQuery->where('validation_status', 'INVALID');
        }
        $rows = $rowsQuery->paginate(25)->withQueryString();
        $invalidCount = $batch->rows()->where('validation_status', 'INVALID')->count();
        $approvableCount = $batch->rows()
            ->where('validation_status', 'VALID')
            ->where('mapping_status', 'AUTO_CANDIDATE')
            ->whereNull('processed_at')
            ->count();

        return view('pages.employee-import-review-show', compact('batch', 'rows', 'filter', 'invalidCount', 'approvableCount'));
    }

    public function destroyImportBatch(Request $request, EmployeeImportBatch $batch): RedirectResponse
    {
        $this->authorizeImportReview($request);
        $batchId = $batch->getKey();

        DB::transaction(fn (): bool => $batch->delete(), 3);

        return to_route('data-pegawai.import.review.index')
            ->with('status', "Batch import #{$batchId} berhasil dihapus.");
    }

    public function showImportBatch(Request $request, EmployeeImportBatch $batch): JsonResponse
    {
        $this->authorizeImportReview($request);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'source_type' => $batch->source_type,
            'original_file_name' => $batch->displayFileName(),
            'imported_at' => $batch->imported_at,
            'total_rows' => $batch->total_rows,
            'valid_rows' => $batch->valid_rows,
            'invalid_rows' => $batch->invalid_rows,
            'approved_rows' => $batch->approved_rows,
            'processed_rows' => $batch->processed_rows,
            'rows' => $batch->rows()->orderBy('source_row')->get([
                'id', 'source_row', 'validation_status', 'mapping_status', 'validation_errors',
                'mapping_errors', 'processing_result', 'approved_at', 'processed_at',
            ]),
        ]);
    }

    public function showImportRow(Request $request, EmployeeImportRow $row): JsonResponse
    {
        $this->authorizeImportReview($request);

        return response()->json([
            'id' => $row->id,
            'batch_id' => $row->employee_import_batch_id,
            'source_row' => $row->source_row,
            'source_payload' => $row->source_payload,
            'normalized_payload' => $row->normalized_payload,
            'validation_status' => $row->validation_status,
            'mapping_status' => $row->mapping_status,
            'validation_errors' => $row->validation_errors,
            'mapping_errors' => $row->mapping_errors,
            'processing_result' => $row->processing_result,
        ]);
    }

    public function approveImportRow(Request $request, EmployeeImportRow $row, EmployeeImportStagingService $stagingService): JsonResponse
    {
        $actor = $this->authorizeImportReview($request);
        try {
            $approved = $stagingService->approve($row, $actor);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['row' => $exception->getMessage()]);
        }

        return response()->json([
            'id' => $approved->id,
            'validation_status' => $approved->validation_status,
            'mapping_status' => $approved->mapping_status,
            'approved_at' => $approved->approved_at,
        ]);
    }

    public function processImportBatch(Request $request, EmployeeImportBatch $batch, EmployeeImportStagingService $stagingService): JsonResponse
    {
        $actor = $this->authorizeImportReview($request);

        return response()->json($stagingService->process($batch, $actor));
    }

    public function approveImportBatchCandidates(Request $request, EmployeeImportBatch $batch, EmployeeImportStagingService $stagingService): JsonResponse
    {
        $actor = $this->authorizeImportReview($request);

        return response()->json([
            'approved' => $stagingService->approveAutoCandidates($batch, $actor),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::create($this->withSelectedOrganizationNames($request->validated()));

        return to_route('data-pegawai')->with('status', 'Data pegawai berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $employee = Employee::query()
            ->with(['organizationDepartment', 'unit', 'organizationPosition'])
            ->findOrFail($id);

        return view('employees.show', compact('employee'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(
        UpdateEmployeeRequest $request,
        Employee $employee,
        StaffUserProvisioningService $staffUserProvisioningService,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $employee, $staffUserProvisioningService): void {
            $employee->update($request->validated());
            $staffUserProvisioningService->synchronizeLinkedUser($employee);
        });

        return to_route('data-pegawai')->with('status', 'Data pegawai berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('employees.delete'), 403);

        $employee->delete();

        return to_route('data-pegawai')->with('status', 'Data pegawai berhasil dihapus.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('employees.delete'), 403);

        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ]);

        $deleted = Employee::query()->whereIn('id', $validated['employee_ids'])->delete();

        return to_route('data-pegawai')->with('status', "Berhasil menghapus {$deleted} data pegawai.");
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('employees.delete'), 403);

        $deleted = Employee::query()->count();
        Employee::query()->delete();

        return to_route('data-pegawai')->with('status', "Berhasil menghapus seluruh data pegawai ({$deleted} data).");
    }

    private function authorizeImportReview(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->hasPermission('employees.import'), 403);

        return $actor;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSelectedOrganizationNames(array $data): array
    {
        $organizationIds = [
            $data['directorate_id'] ?? null,
            $data['department_id'] ?? null,
            $data['unit_id'] ?? null,
            $data['section_id'] ?? null,
            $data['position_id'] ?? null,
        ];

        if (in_array(null, $organizationIds, true)) {
            return $data;
        }

        $data['txt_dir'] = Directorate::query()->findOrFail($data['directorate_id'])->name;
        $data['txt_dept'] = Department::query()->findOrFail($data['department_id'])->name;
        $data['txt_biro'] = Unit::query()->findOrFail($data['unit_id'])->name;
        $data['txt_sect'] = Section::query()->findOrFail($data['section_id'])->name;
        $data['position'] = Position::query()->findOrFail($data['position_id'])->name;

        return $data;
    }
}
