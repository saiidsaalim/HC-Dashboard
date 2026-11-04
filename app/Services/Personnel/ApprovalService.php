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

            if ($action->approvals()
                ->where('approval_round', $action->approval_round)
                ->where('manager_id', $manager->id)
                ->where('status', 'Approved')
                ->exists()) {
                return true;
            }

            $step = $this->workflow->activeStep($action);
            $approval = $action->approvals()
                ->where('approval_round', $action->approval_round)
                ->where('step_order', $step)
                ->lockForUpdate()
                ->first();

            if ($approval === null || $approval->manager_id !== $manager->id || $approval->status !== 'Pending') {
                throw new AuthorizationException('Anda bukan approver aktif untuk tahap ini.');
            }

            $approval->update(['status' => 'Approved', 'approved_at' => now()]);

            if ($step >= MovementApprovalWorkflowService::REQUIRED_STEPS) {
                $action->update([
                    'verification_status' => 'Approved',
                    'current_approval_step' => null,
                    'finalized_at' => now(),
                ]);
                $this->finalizationService->applyIfDue($action);
            } else {
                $action->update(['current_approval_step' => $step + 1]);
            }

            return false;
        });
    }

    public function sendBack(
        EmployeeMutation|EmployeeDemotion $personnelAction,
        User $manager,
        string $note,
    ): bool {
        return DB::transaction(function () use ($personnelAction, $manager, $note): bool {
            $action = $personnelAction::query()->lockForUpdate()->findOrFail($personnelAction->getKey());

            if ($action->approvals()
                ->where('approval_round', $action->approval_round)
                ->where('manager_id', $manager->id)
                ->where('status', 'Returned')
                ->exists()) {
                return true;
            }

            if ($action->verification_status !== 'Pending') {
                throw new AuthorizationException('Pengajuan tidak sedang menunggu keputusan Anda.');
            }

            $step = $this->workflow->activeStep($action);
            $approval = $action->approvals()
                ->where('approval_round', $action->approval_round)
                ->where('step_order', $step)
                ->lockForUpdate()
                ->first();

            if ($approval === null || $approval->manager_id !== $manager->id || $approval->status !== 'Pending') {
                throw new AuthorizationException('Anda bukan approver aktif untuk tahap ini.');
            }

            $approval->update([
                'status' => 'Returned',
                'note' => $note,
                'approved_at' => null,
                'returned_at' => now(),
            ]);

            $action->update([
                'verification_status' => 'Returned',
                'current_approval_step' => null,
            ]);

            return false;
        });
    }
}
