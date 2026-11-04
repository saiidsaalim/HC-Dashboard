<?php

namespace App\Policies;

use App\Models\EmployeeMutation;
use App\Models\User;

class EmployeeMutationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('mutations.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmployeeMutation $employeeMutation): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('mutations.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmployeeMutation $employeeMutation): bool
    {
        return $user->hasPermission('mutations.update')
            && $employeeMutation->verification_status === 'Draft'
            && ! $employeeMutation->approvals()->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmployeeMutation $employeeMutation): bool
    {
        return $user->hasPermission('mutations.delete')
            && $employeeMutation->verification_status === 'Draft'
            && ! $employeeMutation->approvals()->exists();
    }

    public function submit(User $user, EmployeeMutation $employeeMutation): bool
    {
        return $user->hasPermission('mutations.submit')
            && $employeeMutation->verification_status === 'Draft'
            && ! $employeeMutation->approvals()->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmployeeMutation $employeeMutation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmployeeMutation $employeeMutation): bool
    {
        return false;
    }

    public function approve(User $user, EmployeeMutation $employeeMutation): bool
    {
        if (! $user->hasPermission('mutations.approve')) {
            return false;
        }

        if ($employeeMutation->approvals()->where('approval_round', $employeeMutation->approval_round)->where('manager_id', $user->id)->where('status', 'Approved')->exists()) {
            return true;
        }

        if ($employeeMutation->verification_status !== 'Pending') {
            return false;
        }

        return $employeeMutation->approvals()
            ->where('approval_round', $employeeMutation->approval_round)
            ->where('step_order', $employeeMutation->current_approval_step)
            ->where('manager_id', $user->id)
            ->where('status', 'Pending')
            ->exists();
    }

    public function print(User $user, EmployeeMutation $employeeMutation): bool
    {
        return $user->hasPermission('mutations.print') && $employeeMutation->verification_status === 'Final';
    }

    public function manageApprovalSettings(User $user): bool
    {
        return $user->hasPermission('mutations.manage-approval-settings');
    }
}
