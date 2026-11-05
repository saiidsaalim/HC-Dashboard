<?php

namespace App\Http\Controllers;

use App\Exports\MutationBatchSpreadsheet;
use App\Http\Requests\ApproveDemotionBatchItemsRequest;
use App\Http\Requests\StoreDemotionBatchItemRequest;
use App\Http\Requests\StoreDemotionBatchRequest;
use App\Models\Employee;
use App\Models\EmployeeDemotion;
use App\Models\EmployeeDemotionBatch;
use App\Models\User;
use App\Services\Personnel\ApprovalService;
use App\Services\Personnel\MovementApprovalWorkflowService;
use App\Services\Personnel\PersonnelMovementFormDataService;
use App\Services\Personnel\PersonnelMovementSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DemotionBatchController extends Controller
{
    public function create(PersonnelMovementFormDataService $formDataService): View
    {
        Gate::authorize('create', EmployeeDemotion::class);

        return view('pages.mutasi.batch-create', [
            'movementLabel' => 'Demosi',
            'routePrefix' => 'demosi',
            'employeeOptions' => $formDataService->employees(),
            ...$formDataService->organization(),
        ]);
    }

    public function store(
        StoreDemotionBatchRequest $request,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $batch = DB::transaction(function () use ($validated, $user, $snapshotService): EmployeeDemotionBatch {
            $batch = EmployeeDemotionBatch::query()->create([
                'batch_number' => 'TEMP-'.Str::uuid(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'default_tmt' => $validated['default_tmt'],
                'created_by' => $user->id,
            ]);
            $batch->update([
                'batch_number' => 'DEM-'.now()->format('Ym').'-'.str_pad((string) $batch->id, 5, '0', STR_PAD_LEFT),
            ]);

            $employees = Employee::query()
                ->whereIn('id', collect($validated['items'])->pluck('employee_id'))
                ->get()
                ->keyBy('id');

            foreach ($validated['items'] as $item) {
                $employee = $employees->get((int) $item['employee_id']);
                abort_unless($employee instanceof Employee, 422);
                $batch->demotions()->create([
                    ...$snapshotService->fromEmployee($employee, $item),
                    'verification_status' => 'Draft',
                ]);
            }

            return $batch;
        });

        return to_route('demosi.batches.show', $batch)
            ->with('status', count($validated['items']).' data demosi berhasil disimpan dalam batch.');
    }

    public function show(
        EmployeeDemotionBatch $employeeDemotionBatch,
        PersonnelMovementFormDataService $formDataService,
        MovementApprovalWorkflowService $workflow,
    ): View {
        Gate::authorize('viewAny', EmployeeDemotion::class);
        $employeeDemotionBatch->load([
            'creator:id,name',
            'demotions' => fn ($query) => $query->with(['employee', 'approvals.manager'])->latest('tmt')->latest(),
        ]);

        return view('pages.mutasi.batch-show', [
            'batch' => $employeeDemotionBatch,
            'batchItems' => $employeeDemotionBatch->demotions,
            'movementLabel' => 'Demosi',
            'routePrefix' => 'demosi',
            'permissionPrefix' => 'demotions',
            'movementModelClass' => EmployeeDemotion::class,
            'itemIdsField' => 'demotion_ids',
            'addFormPartial' => 'pages.demosi._form',
            'addFormMovementKey' => 'movement',
            'pendingStatuses' => ['Pending'],
            'employeeOptions' => $formDataService->employees()
                ->reject(fn (array $employee): bool => $employeeDemotionBatch->demotions
                    ->contains('employee_id', $employee['id']))
                ->values(),
            ...$formDataService->organization(),
            'approvalConfigurationComplete' => $workflow->isConfigurationComplete(EmployeeDemotion::class),
        ]);
    }

    public function storeItem(
        StoreDemotionBatchItemRequest $request,
        EmployeeDemotionBatch $employeeDemotionBatch,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $employeeDemotionBatch->demotions()->create([
            ...$snapshotService->fromEmployee($employee, $request->validated()),
            'verification_status' => 'Draft',
        ]);

        return to_route('demosi.batches.show', $employeeDemotionBatch)
            ->with('status', 'Pegawai berhasil ditambahkan ke Batch Demosi.');
    }

    public function export(
        EmployeeDemotionBatch $employeeDemotionBatch,
        MutationBatchSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        Gate::authorize('viewAny', EmployeeDemotion::class);

        return $spreadsheet->download($employeeDemotionBatch);
    }

    public function submit(
        Request $request,
        EmployeeDemotionBatch $employeeDemotionBatch,
        MovementApprovalWorkflowService $workflow,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $eligibleDemotions = $employeeDemotionBatch->demotions()
            ->with('approvals')
            ->get()
            ->filter(fn (EmployeeDemotion $demotion): bool => $user->can('submit', $demotion));

        if ($eligibleDemotions->isEmpty()) {
            return back()->with('status', 'Tidak ada item batch yang dapat diajukan.');
        }

        DB::transaction(function () use ($eligibleDemotions, $user, $workflow): void {
            foreach ($eligibleDemotions as $demotion) {
                $workflow->submit($demotion, $user);
            }
        });

        return back()->with('status', $eligibleDemotions->count().' item batch berhasil diajukan.');
    }

    public function approveSelected(
        ApproveDemotionBatchItemsRequest $request,
        EmployeeDemotionBatch $employeeDemotionBatch,
        ApprovalService $approvalService,
    ): RedirectResponse {
        $approver = $request->user();
        abort_unless($approver instanceof User, 403);
        $demotionIds = $request->validated('demotion_ids');
        $demotions = $employeeDemotionBatch->demotions()->whereIn('id', $demotionIds)->get();
        abort_unless($demotions->count() === count($demotionIds), 422, 'Item demosi tidak termasuk dalam batch ini.');

        foreach ($demotions as $demotion) {
            Gate::authorize('approve', $demotion);
        }

        DB::transaction(function () use ($demotions, $approver, $approvalService): void {
            foreach ($demotions as $demotion) {
                $approvalService->approve($demotion, $approver);
            }
        });

        return back()->with('status', $demotions->count().' item batch berhasil disetujui.');
    }
}
