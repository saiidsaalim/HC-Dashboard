<?php

namespace App\Policies;

use App\Models\FitProperActivity;
use App\Models\User;

class FitProperActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fit-proper.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fit-proper.create');
    }

    public function view(User $user, FitProperActivity $activity): bool
    {
        return $this->viewAny($user)
            && ($this->manage($user, $activity) || $activity->examiners()->where('user_id', $user->id)->exists());
    }

    public function manage(User $user, FitProperActivity $activity): bool
    {
        return $user->hasPermission('fit-proper.update')
            && ($user->isSuperAdmin()
                || $activity->created_by === $user->id
                || $activity->pics()->where('users.id', $user->id)->exists());
    }

    public function finalize(User $user, FitProperActivity $activity): bool
    {
        return $user->hasPermission('fit-proper.finalize') && $this->manage($user, $activity);
    }

    public function delete(User $user, FitProperActivity $activity): bool
    {
        return $user->hasPermission('fit-proper.delete');
    }
}
