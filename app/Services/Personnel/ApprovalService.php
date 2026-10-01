<?php

namespace App\Services\Personnel;

use App\Models\EmployeeDemotion;
use App\Models\EmployeeMutation;
use App\Models\EmployeePromotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    private const REQUIRED_APPROVALS = 3;

    public function approve(EmployeeMutation|EmployeePromotion|EmployeeDemotion $personnelAction, User $manager): bool
    {
        return DB::transaction(function () use ($personnelAction, $manager): bool {
            $action = $personnelAction::query()->lockForUpdate()->findOrFail($personnelAction->getKey());

            if ($action->verification_status === 'Final') {
                return true;
            }

            $approval = $action->approvals()->firstOrCreate(
                ['manager_id' => $manager->id],
                [
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

            if ($approvedCount >= self::REQUIRED_APPROVALS) {
                $action->update([
                    'verification_status' => 'Final',
                    'finalized_at' => now(),
                ]);
            }

            return false;
        });
    }
}
