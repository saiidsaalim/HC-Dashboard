<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkSchedule;

class WorkSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('work-schedules.view');
    }

    public function view(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->hasPermission('work-schedules.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('work-schedules.create');
    }

    public function update(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->hasPermission('work-schedules.update');
    }

    public function delete(User $user, WorkSchedule $workSchedule): bool
    {
        return $user->hasPermission('work-schedules.delete');
    }
}
