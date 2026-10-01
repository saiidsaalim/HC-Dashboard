<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkCalendar;

class WorkCalendarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }

    public function view(User $user, WorkCalendar $workCalendar): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }

    public function create(User $user): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }

    public function update(User $user, WorkCalendar $workCalendar): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }

    public function delete(User $user, WorkCalendar $workCalendar): bool
    {
        return $user->roleEnum()->canManageAllRoles();
    }
}
