<?php

namespace App\Policies;

use App\Models\CriticalPosition;
use App\Models\User;

class CriticalPositionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('critical-positions.view');
    }

    public function view(User $user, CriticalPosition $criticalPosition): bool
    {
        return $user->hasPermission('critical-positions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('critical-positions.create');
    }

    public function update(User $user, CriticalPosition $criticalPosition): bool
    {
        return $user->hasPermission('critical-positions.update');
    }

    public function delete(User $user, CriticalPosition $criticalPosition): bool
    {
        return $user->hasPermission('critical-positions.delete');
    }
}
