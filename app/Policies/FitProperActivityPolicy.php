<?php

namespace App\Policies;

use App\Models\FitProperActivity;
use App\Models\User;

class FitProperActivityPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

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
            && ($this->hasManagementScope($user, $activity)
                || $activity->examiners()->where('user_id', $user->id)->exists());
    }

    public function update(User $user, FitProperActivity $activity): bool
    {
        return $user->hasPermission('fit-proper.update')
            && $this->hasManagementScope($user, $activity);
    }

    public function manage(User $user, FitProperActivity $activity): bool
    {
        return $this->update($user, $activity);
    }

    public function finalize(User $user, FitProperActivity $activity): bool
    {
        return $user->hasPermission('fit-proper.finalize') && $this->hasManagementScope($user, $activity);
    }

    public function delete(User $user, FitProperActivity $activity): bool
    {
        return $user->hasPermission('fit-proper.delete') && $this->hasManagementScope($user, $activity);
    }

    public function export(User $user, FitProperActivity $activity): bool
    {
        return $user->hasPermission('fit-proper.export') && $this->view($user, $activity);
    }

    public function settings(User $user): bool
    {
        return $user->hasPermission('fit-proper.settings');
    }

    private function hasManagementScope(User $user, FitProperActivity $activity): bool
    {
        return $user->hasPermission('fit-proper.view-all')
            || $activity->created_by === $user->id
            || $activity->pics()->where('users.id', $user->id)->exists();
    }
}
