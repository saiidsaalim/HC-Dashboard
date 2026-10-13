<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\WlaAssessmentStatus;
use App\Enums\WlaParticipantStatus;
use App\Models\User;
use App\Models\WlaAssessment;
use App\Models\WlaParticipant;

class WlaParticipantPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->roleEnum() === UserRole::STAFF;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WlaParticipant $wlaParticipant): bool
    {
        return $user->roleEnum()->canManageRbac() || $this->owns($user, $wlaParticipant);
    }

    public function update(User $user, WlaParticipant $wlaParticipant): bool
    {
        return $this->owns($user, $wlaParticipant)
            && $wlaParticipant->status->isEditableByStaff()
            && $wlaParticipant->assessment->status === WlaAssessmentStatus::Draft;
    }

    public function submit(User $user, WlaParticipant $wlaParticipant): bool
    {
        return $this->update($user, $wlaParticipant);
    }

    public function review(User $user, WlaParticipant $wlaParticipant): bool
    {
        return $user->roleEnum()->canManageRbac()
            && $wlaParticipant->assessment->status === WlaAssessmentStatus::Draft
            && $wlaParticipant->status === WlaParticipantStatus::Submitted;
    }

    public function manageParticipants(User $user, WlaAssessment $wlaAssessment): bool
    {
        return $user->roleEnum()->canManageRbac()
            && $wlaAssessment->status === WlaAssessmentStatus::Draft;
    }

    public function importApproved(User $user, WlaParticipant $wlaParticipant): bool
    {
        return $user->roleEnum()->canManageRbac()
            && $wlaParticipant->assessment->status === WlaAssessmentStatus::Draft
            && $wlaParticipant->status === WlaParticipantStatus::Approved
            && $wlaParticipant->applied_at === null;
    }

    private function owns(User $user, WlaParticipant $wlaParticipant): bool
    {
        return $user->roleEnum() === UserRole::STAFF
            && $user->employee_id !== null
            && $user->employee_id === $wlaParticipant->employee_id;
    }
}
