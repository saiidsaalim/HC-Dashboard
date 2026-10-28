<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('organization.view');
    }

    public function view(User $user, Section $section): bool
    {
        return $user->hasPermission('organization.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('organization.create');
    }

    public function update(User $user, Section $section): bool
    {
        return $user->hasPermission('organization.update');
    }

    public function delete(User $user, Section $section): bool
    {
        return $user->hasPermission('organization.delete');
    }
}
