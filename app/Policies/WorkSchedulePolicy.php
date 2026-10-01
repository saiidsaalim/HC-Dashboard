<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSchedule;

class WorkSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }

    public function view(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }

    public function create(User $user): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }

    public function update(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }

    public function delete(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }
}
