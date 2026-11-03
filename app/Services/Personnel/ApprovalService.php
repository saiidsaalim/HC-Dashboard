<?php

namespace App\Services\Personnel;

use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        private readonly EmployeeMovementFinalizationService $finalizationService,
        private readonly MovementApprovalWorkflowService $workflow,
    ) {}

    public function approve(EmployeeMutation|EmployeeDemotion $personnelAction, User $manager): bool
    {
        return DB::transaction(function () use ($personnelAction, $manager): bool {
            $action = $personnelAction::query()->lockForUpdate()->findOrFail($personnelAction->getKey());

            if ($action->verification_status === 'Final') {
                return true;
            }

            if ($action->verification_status !== 'Pending') {
                throw new AuthorizationException('Pengajuan belum dikirim ke alur persetujuan.');
            }

            if ($action->approvals()->where('manager_id', $manager->id)->exists()) {
                return true;
            }

            $configuration = $this->workflow->validConfiguration($action::class, lockForUpdate: true);
            $step = $this->workflow->activeStep($action);
            if ($configuration->get($step)?->user_id !== $manager->id) {
                throw new AuthorizationException('Anda bukan approver aktif untuk tahap ini.');
            }

            $approval = $action->approvals()->firstOrCreate(
                ['manager_id' => $manager->id],
                [
                    'step_order' => $step,
                    'status' => 'Approved',
                    'approved_at' => now(),
                ],
            );

            if (! $approval->wasRecentlyCreated) {
                return true;
            }

            $approvedCount = $action->approvals()
                ->where('status', 'Approved')
                ->distinct('manager_id')
                ->count('manager_id');

            if ($approvedCount >= MovementApprovalWorkflowService::REQUIRED_STEPS) {
                $this->finalizationService->apply($action);
                $action->update([
                    'verification_status' => 'Final',
                    'finalized_at' => now(),
                ]);
            }

            return false;
        });
    }
}
