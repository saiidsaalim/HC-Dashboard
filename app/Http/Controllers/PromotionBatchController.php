<?php

namespace App\Http\Controllers;

use App\Enums\PromotionStatus;
use App\Exports\MutationBatchSpreadsheet;
use App\Http\Requests\ApprovePromotionBatchItemsRequest;
use App\Http\Requests\StorePromotionBatchItemRequest;
use App\Http\Requests\StorePromotionBatchRequest;
use App\Models\Employee;
use App\Models\EmployeePromotion;
use App\Models\EmployeePromotionBatch;
use App\Models\User;
use App\Services\Personnel\PersonnelMovementFormDataService;
use App\Services\Personnel\PersonnelMovementSnapshotService;
use App\Services\Personnel\PromotionApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PromotionBatchController extends Controller
{
    public function create(PersonnelMovementFormDataService $formDataService): View
    {
        Gate::authorize('create', EmployeePromotion::class);

        return view('pages.mutasi.batch-create', [
            'movementLabel' => 'Promosi',
            'routePrefix' => 'promosi',
            'employeeOptions' => $formDataService->employees(),
            ...$formDataService->organization(),
        ]);
    }

    public function store(
        StorePromotionBatchRequest $request,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $validated = $request->validated();
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $batch = DB::transaction(function () use ($validated, $user, $snapshotService): EmployeePromotionBatch {
            $batch = EmployeePromotionBatch::query()->create([
                'batch_number' => 'TEMP-'.Str::uuid(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'default_tmt' => $validated['default_tmt'],
                'created_by' => $user->id,
            ]);
            $batch->update([
                'batch_number' => 'PRO-'.now()->format('Ym').'-'.str_pad((string) $batch->id, 5, '0', STR_PAD_LEFT),
            ]);

            $employees = Employee::query()
                ->whereIn('id', collect($validated['items'])->pluck('employee_id'))
                ->get()
                ->keyBy('id');

            foreach ($validated['items'] as $item) {
                $employee = $employees->get((int) $item['employee_id']);
                abort_unless($employee instanceof Employee, 422);
                $batch->promotions()->create([
                    ...$snapshotService->fromEmployee($employee, $item),
                    'verification_status' => PromotionStatus::Draft->value,
                    'approval_round' => 0,
                    'current_approval_step' => null,
                ]);
            }

            return $batch;
        });

        return to_route('promosi.batches.show', $batch)
            ->with('status', count($validated['items']).' data promosi berhasil disimpan dalam batch.');
    }

    public function show(
        EmployeePromotionBatch $employeePromotionBatch,
        PersonnelMovementFormDataService $formDataService,
        PromotionApprovalWorkflowService $workflow,
    ): View {
        Gate::authorize('viewAny', EmployeePromotion::class);
        $employeePromotionBatch->load([
            'creator:id,name',
            'promotions' => fn ($query) => $query->with(['employee', 'approvals.manager'])->latest('tmt')->latest(),
        ]);

        return view('pages.mutasi.batch-show', [
            'batch' => $employeePromotionBatch,
            'batchItems' => $employeePromotionBatch->promotions,
            'movementLabel' => 'Promosi',
            'routePrefix' => 'promosi',
            'permissionPrefix' => 'promotions',
            'movementModelClass' => EmployeePromotion::class,
            'itemIdsField' => 'promotion_ids',
            'addFormPartial' => 'pages.promosi._form',
            'addFormMovementKey' => 'promotion',
            'pendingStatuses' => [PromotionStatus::WaitingApproval->value],
            'employeeOptions' => $formDataService->employees()
                ->reject(fn (array $employee): bool => $employeePromotionBatch->promotions
                    ->contains('employee_id', $employee['id']))
                ->values(),
            ...$formDataService->organization(),
            'approvalConfigurationComplete' => $workflow->isConfigurationComplete(),
        ]);
    }

    public function storeItem(
        StorePromotionBatchItemRequest $request,
        EmployeePromotionBatch $employeePromotionBatch,
        PersonnelMovementSnapshotService $snapshotService,
    ): RedirectResponse {
        $employee = Employee::query()->findOrFail($request->integer('employee_id'));
        $employeePromotionBatch->promotions()->create([
            ...$snapshotService->fromEmployee($employee, $request->validated()),
            'verification_status' => PromotionStatus::Draft->value,
            'approval_round' => 0,
            'current_approval_step' => null,
        ]);

        return to_route('promosi.batches.show', $employeePromotionBatch)
            ->with('status', 'Pegawai berhasil ditambahkan ke Batch Promosi.');
    }

    public function export(
        EmployeePromotionBatch $employeePromotionBatch,
        MutationBatchSpreadsheet $spreadsheet,
    ): BinaryFileResponse {
        Gate::authorize('viewAny', EmployeePromotion::class);

        return $spreadsheet->download($employeePromotionBatch);
    }

    public function submit(
        Request $request,
        EmployeePromotionBatch $employeePromotionBatch,
        PromotionApprovalWorkflowService $workflow,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $eligiblePromotions = $employeePromotionBatch->promotions()
            ->with('approvals')
            ->get()
            ->filter(fn (EmployeePromotion $promotion): bool => $user->can('submit', $promotion));

        if ($eligiblePromotions->isEmpty()) {
            return back()->with('status', 'Tidak ada item batch yang dapat diajukan.');
        }

        DB::transaction(function () use ($eligiblePromotions, $user, $workflow): void {
            foreach ($eligiblePromotions as $promotion) {
                $workflow->submit($promotion, $user);
            }
        });

        return back()->with('status', $eligiblePromotions->count().' item batch berhasil diajukan.');
    }

    public function approveSelected(
        ApprovePromotionBatchItemsRequest $request,
        EmployeePromotionBatch $employeePromotionBatch,
        PromotionApprovalWorkflowService $workflow,
    ): RedirectResponse {
        $approver = $request->user();
        abort_unless($approver instanceof User, 403);
        $promotionIds = $request->validated('promotion_ids');
        $promotions = $employeePromotionBatch->promotions()->whereIn('id', $promotionIds)->get();
        abort_unless($promotions->count() === count($promotionIds), 422, 'Item promosi tidak termasuk dalam batch ini.');

        foreach ($promotions as $promotion) {
            Gate::authorize('approve', $promotion);
        }

        DB::transaction(function () use ($promotions, $approver, $workflow): void {
            foreach ($promotions as $promotion) {
                $workflow->approve($promotion, $approver);
            }
        });

        return back()->with('status', $promotions->count().' item batch berhasil disetujui.');
    }
}
