<?php

namespace App\Policies;

use App\Enums\PromotionApprovalStatus;
use App\Enums\PromotionStatus;
use App\Models\EmployeePromotion;
use App\Models\PromotionApprovalConfig;
use App\Models\User;

class EmployeePromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('promotions.view');
    }

    public function view(User $user, EmployeePromotion $employeePromotion): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('promotions.create');
    }

    public function update(User $user, EmployeePromotion $employeePromotion): bool
    {
        return $user->hasPermission('promotions.update') && $employeePromotion->isEditable();
    }

    public function delete(User $user, EmployeePromotion $employeePromotion): bool
    {
        return $user->hasPermission('promotions.delete') && $employeePromotion->isEditable();
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('promotions.import');
    }

    public function submit(User $user, EmployeePromotion $employeePromotion): bool
    {
        return $user->hasPermission('promotions.submit') && $employeePromotion->statusEnum()->canBeSubmitted();
    }

    public function approve(User $user, EmployeePromotion $employeePromotion): bool
    {
        if (! $user->hasPermission('promotions.approve')) {
            return false;
        }

        if ($this->hasDecision($user, $employeePromotion, PromotionApprovalStatus::Approved)) {
            return true;
        }

        return $this->isCurrentApprover($user, $employeePromotion);
    }

    public function sendBack(User $user, EmployeePromotion $employeePromotion): bool
    {
        if (! $user->hasPermission('promotions.approve')) {
            return false;
        }

        if ($this->hasDecision($user, $employeePromotion, PromotionApprovalStatus::Returned)) {
            return true;
        }

        return $this->isCurrentApprover($user, $employeePromotion);
    }

    public function print(User $user, EmployeePromotion $employeePromotion): bool
    {
        return $user->hasPermission('promotions.print') && $employeePromotion->statusEnum() === PromotionStatus::Final;
    }

    public function manageApprovalSettings(User $user): bool
    {
        return $user->hasPermission('promotions.manage-approval-settings');
    }

    private function isCurrentApprover(User $user, EmployeePromotion $employeePromotion): bool
    {
        if ($employeePromotion->statusEnum() !== PromotionStatus::WaitingApproval
            || $employeePromotion->current_approval_step === null) {
            return false;
        }

        return PromotionApprovalConfig::query()
            ->where('step_order', $employeePromotion->current_approval_step)
            ->where('user_id', $user->id)
            ->exists();
    }

    private function hasDecision(
        User $user,
        EmployeePromotion $employeePromotion,
        PromotionApprovalStatus $status,
    ): bool {
        return $employeePromotion->approvals()
            ->where('approval_round', $employeePromotion->approval_round)
            ->where('manager_id', $user->id)
            ->where('status', $status->value)
            ->exists();
    }
}
