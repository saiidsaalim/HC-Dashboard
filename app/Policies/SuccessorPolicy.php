<?php

namespace App\Policies;

use App\Models\Successor;
use App\Models\User;

class SuccessorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('successors.view');
    }

    public function view(User $user, Successor $successor): bool
    {
        return $user->hasPermission('successors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('successors.create');
    }

    public function update(User $user, Successor $successor): bool
    {
        return $user->hasPermission('successors.update');
    }

    public function delete(User $user, Successor $successor): bool
    {
        return $user->hasPermission('successors.delete');
    }
}
