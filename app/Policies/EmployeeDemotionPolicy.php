<?php

namespace App\Policies;

use App\Models\DemotionApprovalConfig;
use App\Models\EmployeeDemotion;
use App\Models\User;

class EmployeeDemotionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('demotions.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmployeeDemotion $employeeDemotion): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('demotions.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmployeeDemotion $employeeDemotion): bool
    {
        return $user->hasPermission('demotions.update') && $employeeDemotion->verification_status !== 'Final';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmployeeDemotion $employeeDemotion): bool
    {
        return $user->hasPermission('demotions.delete') && $employeeDemotion->verification_status !== 'Final';
    }

    public function submit(User $user, EmployeeDemotion $employeeDemotion): bool
    {
        return $user->hasPermission('demotions.submit') && $employeeDemotion->verification_status === 'Draft';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmployeeDemotion $employeeDemotion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmployeeDemotion $employeeDemotion): bool
    {
        return false;
    }

    public function approve(User $user, EmployeeDemotion $employeeDemotion): bool
    {
        if (! $user->hasPermission('demotions.approve')) {
            return false;
        }

        if ($employeeDemotion->approvals()->where('manager_id', $user->id)->exists()) {
            return true;
        }

        if ($employeeDemotion->verification_status !== 'Pending') {
            return false;
        }

        $activeStep = $employeeDemotion->approvals()
            ->where('status', 'Approved')
            ->distinct('manager_id')
            ->count('manager_id') + 1;

        return DemotionApprovalConfig::query()
            ->where('step_order', $activeStep)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function print(User $user, EmployeeDemotion $employeeDemotion): bool
    {
        return $user->hasPermission('demotions.print') && $employeeDemotion->verification_status === 'Final';
    }

    public function manageApprovalSettings(User $user): bool
    {
        return $user->hasPermission('demotions.manage-approval-settings');
    }
}
