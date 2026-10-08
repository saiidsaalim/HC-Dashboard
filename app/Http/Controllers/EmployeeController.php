<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exports\EmployeeSpreadsheet;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Imports\EmployeeImport;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeImportBatch;
use App\Models\EmployeeImportRow;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Employees\EmployeeImportStagingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $department = trim((string) $request->input('department', ''));
        $position = trim((string) $request->input('position', ''));
        $departmentId = trim((string) $request->input('department_id', ''));
        $unitId = trim((string) $request->input('unit_id', ''));
        $positionId = trim((string) $request->input('position_id', ''));
        $sortBy = $request->validate([
            'sort_by' => ['nullable', 'string', Rule::in(['latest', 'organic_newest', 'organic_oldest'])],
        ])['sort_by'] ?? 'latest';
        $employeeQuery = Employee::query()->with(['organizationDepartment', 'unit', 'organizationPosition']);

        if ($search !== '') {
            $employeeQuery->where(function (Builder $query) use ($search): void {
                foreach (['sap', 'personal_number', 'txt_dept', 'position', 'id_number', 'email'] as $column) {
                    $query->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        if ($departmentId !== '') {
            $employeeQuery->where('department_id', $departmentId);
        }

        if ($unitId !== '') {
            $employeeQuery->where('unit_id', $unitId);
        }

        if ($positionId !== '') {
            $employeeQuery->where('position_id', $positionId);
        }

        if ($department !== '') {
            $employeeQuery->where('txt_dept', $department);
        }

        if ($position !== '') {
            $employeeQuery->where('position', $position);
        }

        if ($sortBy === 'organic_newest' || $sortBy === 'organic_oldest') {
            $employeeQuery
                ->orderByRaw('CASE WHEN organilk IS NULL THEN 1 ELSE 0 END')
                ->orderBy('organilk', $sortBy === 'organic_newest' ? 'desc' : 'asc')
                ->orderByDesc('id');
        } else {
            $employeeQuery->latest();
        }

        $employees = $employeeQuery->paginate(10)->withQueryString();
        $departmentIds = $employees->pluck('department_id')->filter()->merge($departmentId !== '' ? [$departmentId] : [])->unique()->all();
        $unitIds = $employees->pluck('unit_id')->filter()->merge($unitId !== '' ? [$unitId] : [])->unique()->all();
        $positionIds = $employees->pluck('position_id')->filter()->merge($positionId !== '' ? [$positionId] : [])->unique()->all();

        return view('pages.data-pegawai', [
            'employees' => $employees,
            'search' => $search,
            'department' => $department,
            'position' => $position,
            'departmentId' => $departmentId,
            'unitId' => $unitId,
            'positionId' => $positionId,
            'sortBy' => $sortBy,
            'departments' => Employee::query()->whereNotNull('txt_dept')->where('txt_dept', '!=', '')->distinct()->orderBy('txt_dept')->pluck('txt_dept'),
            'positions' => Employee::query()->whereNotNull('position')->where('position', '!=', '')->distinct()->orderBy('position')->pluck('position'),
            'organizationDepartments' => Department::query()->where('active', true)->orWhereIn('id', $departmentIds)->orderBy('name')->get(),
            'organizationUnits' => Unit::query()->with('department')->where('active', true)->orWhereIn('id', $unitIds)->orderBy('name')->get(),
            'organizationPositions' => Position::query()->with('unit.department')->where('active', true)->orWhereIn('id', $positionIds)->orderBy('name')->get(),
            'canManageEmployees' => $request->user()?->roleEnum() === UserRole::SUPER_ADMIN,
        ]);
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

    public function upload(Request $request, EmployeeImport $employeeImport): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->roleEnum() === UserRole::SUPER_ADMIN, 403);

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

    public function showImportBatch(Request $request, EmployeeImportBatch $batch): JsonResponse
    {
        $this->authorizeImportReview($request);

        return response()->json([
            'id' => $batch->id,
            'status' => $batch->status,
            'source_type' => $batch->source_type,
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
        Employee::create($request->validated());

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
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return to_route('data-pegawai')->with('status', 'Data pegawai berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);

        $employee->delete();

        return to_route('data-pegawai')->with('status', 'Data pegawai berhasil dihapus.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);

        $validated = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ]);

        $deleted = Employee::query()->whereIn('id', $validated['employee_ids'])->delete();

        return to_route('data-pegawai')->with('status', "Berhasil menghapus {$deleted} data pegawai.");
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->roleEnum() === UserRole::SUPER_ADMIN, 403);

        $deleted = Employee::query()->count();
        Employee::query()->delete();

        return to_route('data-pegawai')->with('status', "Berhasil menghapus seluruh data pegawai ({$deleted} data).");
    }

    private function authorizeImportReview(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $actor->roleEnum() === UserRole::SUPER_ADMIN, 403);

        return $actor;
    }
}
