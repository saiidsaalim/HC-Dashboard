<?php

namespace App\Policies;

use App\Models\Directorate;
use App\Models\User;

class DirectoratePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('organization.view');
    }

    public function view(User $user, Directorate $directorate): bool
    {
        return $user->hasPermission('organization.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('organization.create');
    }

    public function update(User $user, Directorate $directorate): bool
    {
        return $user->hasPermission('organization.update');
    }

    public function delete(User $user, Directorate $directorate): bool
    {
        return $user->hasPermission('organization.delete');
    }
}
