<?php

namespace App\Policies;

use App\Models\FitProperActivity;
use App\Models\User;

class FitProperActivityPolicy
{
    public function create(User $user): bool
    {
        return $user->canManageRbac();
    }

    public function view(User $user, FitProperActivity $activity): bool
    {
        return $this->manage($user, $activity) || $activity->examiners()->where('user_id', $user->id)->exists();
    }

    public function manage(User $user, FitProperActivity $activity): bool
    {
        return $user->canManageRbac() || $activity->pics()->where('users.id', $user->id)->exists();
    }

    public function finalize(User $user, FitProperActivity $activity): bool
    {
        return $user->canManageRbac();
    }
}
