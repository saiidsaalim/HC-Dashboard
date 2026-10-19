<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkCalendar;

class WorkCalendarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('work-calendars.view');
    }

    public function view(User $user, WorkCalendar $workCalendar): bool
    {
        return $user->hasPermission('work-calendars.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('work-calendars.create');
    }

    public function update(User $user, WorkCalendar $workCalendar): bool
    {
        return $user->hasPermission('work-calendars.update');
    }

    public function delete(User $user, WorkCalendar $workCalendar): bool
    {
        return $user->hasPermission('work-calendars.delete');
    }
}
