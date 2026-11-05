<?php

namespace App\Http\Controllers;

use App\Exports\MutationBatchSpreadsheet;
use App\Http\Requests\ApproveMutationBatchItemsRequest;
use App\Http\Requests\StoreMutationBatchItemRequest;
use App\Http\Requests\StoreMutationBatchRequest;
use App\Models\Employee;
use App\Models\EmployeeMutation;
use App\Models\EmployeeMutationBatch;
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

class MutationBatchController extends Controller
{
    public function create(PersonnelMovementFormDataService $formDataService): View
    {
        Gate::authorize('create', EmployeeMutation::class);

        return view('pages.mutasi.batch-create', [
            'employeeOptions' => $formDataService->employees(),
            ...$formDataService->organization(),
        ]);
    }

    public function store(
        StoreMutationBatchRequest $request,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $batch = DB::transaction(function () use ($validated, $user, $snapshotService): EmployeeMutationBatch {
            $batch = EmployeeMutationBatch::query()->create([
                'batch_number' => 'TEMP-'.Str::uuid(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'default_tmt' => $validated['default_tmt'],
                'created_by' => $user->id,
            ]);
            $batch->update([
                'batch_number' => 'MUT-'.now()->format('Ym').'-'.str_pad((string) $batch->id, 5, '0', STR_PAD_LEFT),
            ]);

            $employees = Employee::query()
                ->whereIn('id', collect($validated['items'])->pluck('employee_id'))
                ->get()
                ->keyBy('id');

            foreach ($validated['items'] as $item) {
                $employee = $employees->get((int) $item['employee_id']);
                abort_unless($employee instanceof Employee, 422);

                $batch->mutations()->create([
                    ...$snapshotService->fromEmployee($employee, $item),
                    'verification_status' => 'Draft',
                ]);
            }

            return $batch;
        });

        return to_route('mutasi.batches.show', $batch)
            ->with('status', count($validated['items']).' data mutasi berhasil disimpan dalam batch.');
    }

    public function show(
        EmployeeMutationBatch $employeeMutationBatch,
        PersonnelMovementFormDataService $formDataService,
        MovementApprovalWorkflowService $workflow,
    ): View {
        Gate::authorize('viewAny', EmployeeMutation::class);
        $employeeMutationBatch->load([
            'creator:id,name',
            'mutations' => fn ($query) => $query->with(['employee', 'approvals.manager'])->latest('tmt')->latest(),
        ]);

        return view('pages.mutasi.batch-show', [
            'batch' => $employeeMutationBatch,
            'employeeOptions' => $formDataService->employees()
                ->reject(fn (array $employee): bool => $employeeMutationBatch->mutations
                    ->contains('employee_id', $employee['id']))
                ->values(),
            ...$formDataService->organization(),
            'approvalConfigurationComplete' => $workflow->isConfigurationComplete(EmployeeMutation::class),
        ]);
    }

    public function storeItem(
        StoreMutationBatchItemRequest $request,
        EmployeeMutationBatch $employeeMutationBatch,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $employeeMutationBatch->mutations()->create([
            ...$snapshotService->fromEmployee($employee, $request->validated()),
            'verification_status' => 'Draft',
        ]);

        return to_route('mutasi.batches.show', $employeeMutationBatch)
            ->with('status', 'Pegawai berhasil ditambahkan ke Batch Mutasi.');
    }

    public function export(
        EmployeeMutationBatch $employeeMutationBatch,
        MutationBatchSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        Gate::authorize('viewAny', EmployeeMutation::class);

        return $spreadsheet->download($employeeMutationBatch);
    }

    public function submit(
        Request $request,
        EmployeeMutationBatch $employeeMutationBatch,
        MovementApprovalWorkflowService $workflow,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $eligibleMutations = $employeeMutationBatch->mutations()
            ->with('approvals')
            ->get()
            ->filter(fn (EmployeeMutation $mutation): bool => $user->can('submit', $mutation));

        if ($eligibleMutations->isEmpty()) {
            return back()->with('status', 'Tidak ada item batch yang dapat diajukan.');
        }

        DB::transaction(function () use ($eligibleMutations, $user, $workflow): void {
            foreach ($eligibleMutations as $mutation) {
                $workflow->submit($mutation, $user);
            }
        });

        return back()->with('status', $eligibleMutations->count().' item batch berhasil diajukan.');
    }

    public function approveSelected(
        ApproveMutationBatchItemsRequest $request,
        EmployeeMutationBatch $employeeMutationBatch,
        ApprovalService $approvalService,
    ): RedirectResponse {
        $manager = $request->user();
        abort_unless($manager instanceof User, 403);

        $mutations = $employeeMutationBatch->mutations()
            ->whereIn('id', $request->validated('mutation_ids'))
            ->get();
        abort_unless($mutations->count() === count($request->validated('mutation_ids')), 422, 'Item mutasi tidak termasuk dalam batch ini.');

        foreach ($mutations as $mutation) {
            Gate::authorize('approve', $mutation);
        }

        DB::transaction(function () use ($mutations, $manager, $approvalService): void {
            foreach ($mutations as $mutation) {
                $approvalService->approve($mutation, $manager);
            }
        });

        return back()->with('status', $mutations->count().' item batch berhasil disetujui.');
    }
}
